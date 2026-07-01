<?php

namespace App\Services\Hsi;

use App\Services\Algolia\AlgoliaSearchClient;

class HsiUnifiedSearchService
{
  public function __construct(
    private readonly HsiPageSearchService $pageSearch,
    private readonly AlgoliaSearchClient $algolia,
  ) {
  }

  /**
   * @return array<string, mixed>
   */
  public function search(string $q, int $pageLimit = 10, int $algoliaLimit = 5): array
  {
    $q = trim($q);
    if ($q === '') {
      return [
        'q' => $q,
        'pages' => [],
        'algolia' => [],
      ];
    }

    $ranked = $this->pageSearch->ranked($q)->take($pageLimit);
    $pages = $ranked->map(fn (array $row) => $this->pageSearch->toSearchResult(
      $row['page'],
      $q,
      $row['score'],
      $row['matches'],
    ))->values();

    $algolia = $this->algolia->isConfigured()
      ? $this->algolia->searchAll($q, $algoliaLimit)
      : [];

    return [
      'q' => $q,
      'pages' => $pages,
      'algolia' => $algolia,
    ];
  }
}
