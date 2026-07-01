<?php

namespace App\Services\Hsi;

use App\Models\HsiPage;

class HsiPageChunker
{
  /**
   * @return array<int, array{chunk_index: int, content: string, content_hash: string}>
   */
  public function chunkPage(HsiPage $page): array
  {
    $url = $page->canonical_url ?: ($page->fetched_url ?: $page->dedupe_key);
    $title = trim((string) ($page->title ?? 'Untitled'));
    $body = trim((string) ($page->body_text ?? ''));

    if ($body === '') {
      return [];
    }

    $maxChars = (int) config('hsi_ai.chunking.max_chars', 1200);
    $overlap = (int) config('hsi_ai.chunking.overlap_chars', 150);

    $paragraphs = preg_split("/\n{2,}/", $body) ?: [$body];
    $paragraphs = array_values(array_filter(array_map('trim', $paragraphs)));

    $normalizedParagraphs = [];
    foreach ($paragraphs as $paragraph) {
      foreach ($this->splitOversizedText($paragraph, $maxChars) as $part) {
        $normalizedParagraphs[] = $part;
      }
    }

    $rawChunks = [];
    $buffer = '';

    foreach ($normalizedParagraphs as $paragraph) {
      if ($buffer === '') {
        $buffer = $paragraph;
        continue;
      }

      if (mb_strlen($buffer.' '.$paragraph) <= $maxChars) {
        $buffer .= ' '.$paragraph;
        continue;
      }

      $rawChunks[] = $buffer;
      $buffer = $this->tailOverlap($buffer, $overlap).' '.$paragraph;
    }

    if ($buffer !== '') {
      $rawChunks[] = $buffer;
    }

    $chunks = [];
    foreach ($rawChunks as $index => $chunkText) {
      $chunkText = trim($chunkText);
      if ($chunkText === '') {
        continue;
      }

      $content = "Title: {$title}\nURL: {$url}\n\n{$chunkText}";
      $chunks[] = [
        'chunk_index' => $index,
        'content' => $content,
        'content_hash' => hash('sha256', $content),
      ];
    }

    return $chunks;
  }

  private function tailOverlap(string $text, int $overlap): string
  {
    if ($overlap <= 0 || mb_strlen($text) <= $overlap) {
      return $text;
    }

    return mb_substr($text, -$overlap);
  }

  /**
   * @return array<int, string>
   */
  private function splitOversizedText(string $text, int $maxChars): array
  {
    $text = trim($text);
    if ($text === '') {
      return [];
    }

    if (mb_strlen($text) <= $maxChars) {
      return [$text];
    }

    $parts = [];
    $offset = 0;
    $length = mb_strlen($text);

    while ($offset < $length) {
      $slice = mb_substr($text, $offset, $maxChars);
      if ($offset + $maxChars < $length) {
        $breakAt = max(
          (int) mb_strrpos($slice, '. '),
          (int) mb_strrpos($slice, ' '),
        );
        if ($breakAt > (int) ($maxChars * 0.5)) {
          $slice = mb_substr($slice, 0, $breakAt);
        }
      }

      $slice = trim($slice);
      if ($slice !== '') {
        $parts[] = $slice;
      }

      if ($slice === '') {
        break;
      }

      $offset += max(1, mb_strlen($slice));
    }

    return $parts;
  }
}
