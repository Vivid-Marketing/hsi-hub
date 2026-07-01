<?php

namespace App\Services\Hsi;

use App\Models\HsiChunk;
use App\Services\Ai\OllamaClient;
use App\Services\Algolia\AlgoliaSearchClient;
use Illuminate\Support\Carbon;

class HsiAlgoliaEmbedder
{
  public function __construct(
    private readonly AlgoliaSearchClient $algolia,
    private readonly OllamaClient $ollama,
  ) {
  }

  public function embedIndex(string $indexName, string $sourceType): int
  {
    $records = $this->algolia->browseAll($indexName);
    $baseUrl = rtrim((string) config('hsi_crawl.base_url', 'https://hsi.com'), '/');
    $model = (string) config('hsi_ai.ollama.embed_model');
    $maxChars = (int) config('hsi_ai.chunking.max_chars', 1200);
    $overlap = (int) config('hsi_ai.chunking.overlap_chars', 150);
    $embedded = 0;

    foreach ($records as $record) {
      $objectId = (string) ($record['objectID'] ?? '');
      if ($objectId === '') {
        continue;
      }

      $title = (string) ($record['title'] ?? '');
      $rawUrl = (string) ($record['url'] ?? '');
      $url = $rawUrl !== '' ? ($baseUrl.'/'.ltrim($rawUrl, '/')) : '';

      $body = $this->buildBody($sourceType, $record);
      if (trim($body) === '') {
        continue;
      }

      HsiChunk::query()
        ->where('source_type', $sourceType)
        ->where('source_id', $objectId)
        ->delete();

      $chunks = $this->splitIntoChunks($title, $url, $body, $maxChars, $overlap);

      foreach ($chunks as $chunkIndex => $chunkContent) {
        $vector = $this->ollama->embed($chunkContent);

        HsiChunk::create([
          'hsi_page_id' => null,
          'source_type' => $sourceType,
          'source_id' => $objectId,
          'source_url' => $url,
          'source_title' => $title,
          'chunk_index' => $chunkIndex,
          'content' => $chunkContent,
          'content_hash' => hash('sha256', $chunkContent),
          'embedding_model' => $model,
          'embedding' => $vector,
          'embedded_at' => Carbon::now(),
        ]);

        $embedded++;
      }
    }

    return $embedded;
  }

  private function buildBody(string $sourceType, array $record): string
  {
    if ($sourceType === 'course') {
      $parts = array_filter(array_map('trim', [
        (string) ($record['courseInformation'] ?? ''),
        (string) ($record['courseLearningObjectives'] ?? ''),
        (string) ($record['courseOutline'] ?? ''),
        (string) ($record['courseRegulations'] ?? ''),
      ]));

      return implode("\n\n", $parts);
    }

    // blog_news
    $parts = [];
    $excerpt = trim((string) ($record['excerpt'] ?? ''));
    if ($excerpt !== '') {
      $parts[] = $excerpt;
    }

    if (is_array($record['blogContent'] ?? null)) {
      foreach ($record['blogContent'] as $block) {
        $block = strip_tags(trim((string) $block));
        if ($block !== '') {
          $parts[] = $block;
        }
      }
    }

    return implode("\n\n", $parts);
  }

  /**
   * @return array<int, string>
   */
  private function splitIntoChunks(string $title, string $url, string $body, int $maxChars, int $overlap): array
  {
    $paragraphs = preg_split('/\n{2,}/', trim($body)) ?: [trim($body)];
    $paragraphs = array_values(array_filter(array_map('trim', $paragraphs)));

    $rawChunks = [];
    $buffer = '';

    foreach ($paragraphs as $paragraph) {
      if (mb_strlen($paragraph) > $maxChars) {
        if ($buffer !== '') {
          $rawChunks[] = $buffer;
          $buffer = '';
        }
        $offset = 0;
        $len = mb_strlen($paragraph);
        while ($offset < $len) {
          $slice = trim(mb_substr($paragraph, $offset, $maxChars));
          if ($slice !== '') {
            $rawChunks[] = $slice;
          }
          $offset += max(1, mb_strlen($slice));
        }
        continue;
      }

      if ($buffer === '') {
        $buffer = $paragraph;
        continue;
      }

      if (mb_strlen($buffer.' '.$paragraph) <= $maxChars) {
        $buffer .= ' '.$paragraph;
        continue;
      }

      $rawChunks[] = $buffer;
      $tail = mb_strlen($buffer) > $overlap ? mb_substr($buffer, -$overlap) : $buffer;
      $buffer = $tail.' '.$paragraph;
    }

    if ($buffer !== '') {
      $rawChunks[] = $buffer;
    }

    $chunks = [];
    foreach ($rawChunks as $chunkText) {
      $chunkText = trim($chunkText);
      if ($chunkText !== '') {
        $chunks[] = "Title: {$title}\nURL: {$url}\n\n{$chunkText}";
      }
    }

    return $chunks;
  }
}
