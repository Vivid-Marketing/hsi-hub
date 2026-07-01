<?php

namespace App\Services\Hsi;

use App\Models\HsiChunk;

class HsiVectorSearch
{
  /**
   * @param  array<int, float>  $queryVector
   * @return array<int, array{chunk: HsiChunk, score: float}>
   */
  public function search(array $queryVector, int $limit = 5, ?string $sourceType = 'page'): array
  {
    if ($queryVector === []) {
      return [];
    }

    $query = HsiChunk::query()->whereNotNull('embedding');
    if ($sourceType !== null) {
      $query->where('source_type', $sourceType);
    }

    // Process in batches to avoid loading all embeddings into memory at once.
    // Keep a rolling top-K candidate list bounded to limit*10.
    $candidates = [];
    $bufferMax = $limit * 10;

    $query->chunkById(200, function ($batch) use ($queryVector, &$candidates, $bufferMax) {
      foreach ($batch as $chunk) {
        $vector = (array) ($chunk->embedding ?? []);
        if ($vector === []) {
          continue;
        }

        $score = $this->cosineSimilarity($queryVector, $vector);
        if ($score <= 0) {
          continue;
        }

        $candidates[] = ['chunk' => $chunk, 'score' => $score];
      }

      if (count($candidates) > $bufferMax) {
        usort($candidates, fn (array $a, array $b) => $b['score'] <=> $a['score']);
        $candidates = array_slice($candidates, 0, $bufferMax);
      }
    });

    usort($candidates, fn (array $a, array $b) => $b['score'] <=> $a['score']);

    return array_slice($candidates, 0, $limit);
  }

  /**
   * @param  array<int, float|int>  $a
   * @param  array<int, float|int>  $b
   */
  public function cosineSimilarity(array $a, array $b): float
  {
    $len = min(count($a), count($b));
    if ($len === 0) {
      return 0.0;
    }

    $dot = 0.0;
    $normA = 0.0;
    $normB = 0.0;

    for ($i = 0; $i < $len; $i++) {
      $av = (float) $a[$i];
      $bv = (float) $b[$i];
      $dot += $av * $bv;
      $normA += $av * $av;
      $normB += $bv * $bv;
    }

    if ($normA <= 0.0 || $normB <= 0.0) {
      return 0.0;
    }

    return $dot / (sqrt($normA) * sqrt($normB));
  }
}
