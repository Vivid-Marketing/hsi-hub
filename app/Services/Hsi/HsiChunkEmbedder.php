<?php

namespace App\Services\Hsi;

use App\Models\HsiChunk;
use App\Models\HsiPage;
use App\Services\Ai\OllamaClient;
use Illuminate\Support\Carbon;

class HsiChunkEmbedder
{
  public function __construct(
    private readonly HsiPageChunker $chunker,
    private readonly OllamaClient $ollama,
  ) {
  }

  public function embedPage(HsiPage $page, bool $force = false): int
  {
    if ($page->crawl_status === 'error' || trim((string) ($page->body_text ?? '')) === '') {
      return 0;
    }

    $chunks = $this->chunker->chunkPage($page);
    if ($chunks === []) {
      return 0;
    }

    $existingHash = HsiChunk::query()
      ->where('hsi_page_id', $page->id)
      ->value('content_hash');

    if (! $force && $existingHash !== null) {
      $pageHash = (string) ($page->content_hash ?? '');
      $storedPageHash = HsiChunk::query()
        ->where('hsi_page_id', $page->id)
        ->whereNotNull('embedded_at')
        ->exists();

      if ($storedPageHash && $pageHash !== '' && HsiChunk::query()->where('hsi_page_id', $page->id)->count() === count($chunks)) {
        $first = HsiChunk::query()->where('hsi_page_id', $page->id)->orderBy('chunk_index')->first();
        if ($first && hash_equals((string) $first->content_hash, (string) ($chunks[0]['content_hash'] ?? ''))) {
          return 0;
        }
      }
    }

    HsiChunk::query()->where('hsi_page_id', $page->id)->delete();

    $url = $page->canonical_url ?: ($page->fetched_url ?: $page->dedupe_key);
    $model = (string) config('hsi_ai.ollama.embed_model');
    $embedded = 0;

    foreach ($chunks as $chunk) {
      $vector = $this->ollama->embed($chunk['content']);

      HsiChunk::create([
        'hsi_page_id' => $page->id,
        'source_type' => 'page',
        'source_id' => (string) $page->id,
        'source_url' => $url,
        'source_title' => $page->title,
        'chunk_index' => $chunk['chunk_index'],
        'content' => $chunk['content'],
        'content_hash' => $chunk['content_hash'],
        'embedding_model' => $model,
        'embedding' => $vector,
        'embedded_at' => Carbon::now(),
      ]);

      $embedded++;
    }

    return $embedded;
  }
}
