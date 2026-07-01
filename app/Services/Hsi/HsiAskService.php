<?php

namespace App\Services\Hsi;

use App\Services\Ai\OllamaClient;

class HsiAskService
{
  public function __construct(
    private readonly OllamaClient $ollama,
    private readonly HsiVectorSearch $vectorSearch,
  ) {
  }

  /**
   * @return array<string, mixed>
   */
  public function ask(string $question): array
  {
    $question = trim($question);
    if ($question === '') {
      throw new \InvalidArgumentException('Question is required.');
    }

    $pageChunkLimit = (int) config('hsi_ai.retrieval.page_chunk_limit', 5);

    $queryVector = $this->ollama->embed($question);
    $vectorHits = $this->vectorSearch->search($queryVector, $pageChunkLimit, null);

    $contextBlocks = [];
    $sources = [];
    $seenUrls = [];

    foreach ($vectorHits as $hit) {
      $chunk = $hit['chunk'];
      $url = (string) ($chunk->source_url ?? '');
      $sourceType = (string) ($chunk->source_type ?? 'page');
      $contextBlocks[] = [
        'type' => $sourceType,
        'title' => $chunk->source_title,
        'url' => $url,
        'content' => $chunk->content,
        'score' => round($hit['score'], 4),
      ];

      if ($url !== '' && ! isset($seenUrls[$url])) {
        $seenUrls[$url] = true;
        $sources[] = [
          'type' => $sourceType,
          'title' => $chunk->source_title,
          'url' => $url,
        ];
      }
    }

    $contextText = $this->formatContext($contextBlocks);
    $systemPrompt = <<<'PROMPT'
You are the AI assistant built into HSI's own website. You speak as part of the HSI team.

Rules:
- Always use first-person plural: say "we", "our", and "us" when referring to HSI's products, services, team, or website. Never say "HSI offers", "according to HSI", or "HSI's website" — you ARE HSI.
- Be conversational, direct, and helpful — write for a website visitor, not a formal report.
- Answer using only the provided context. Do not invent URLs, product names, or policies.
- If the context is insufficient, say so honestly and point the visitor to a relevant page from the context.
- Do NOT include a Sources or References section — source links are shown separately in the UI.
PROMPT;

    $userPrompt = "Question:\n{$question}\n\nContext:\n{$contextText}";

    $answer = $this->ollama->chat($systemPrompt, $userPrompt);

    return [
      'question' => $question,
      'answer' => $answer,
      'sources' => $sources,
      'context_count' => count($contextBlocks),
      'used_algolia' => collect($sources)->contains(fn (array $s) => in_array($s['type'] ?? '', ['course', 'blog_news', 'blog', 'news'], true)),
    ];
  }

  /**
   * @param  array<int, array<string, mixed>>  $blocks
   */
  private function formatContext(array $blocks): string
  {
    if ($blocks === []) {
      return 'No relevant context found.';
    }

    $parts = [];
    foreach ($blocks as $i => $block) {
      $parts[] = '[#'.($i + 1)."] type={$block['type']}\n"
        .'title: '.($block['title'] ?? '')."\n"
        .'url: '.($block['url'] ?? '')."\n"
        .($block['content'] ?? '');
    }

    return implode("\n\n---\n\n", $parts);
  }
}
