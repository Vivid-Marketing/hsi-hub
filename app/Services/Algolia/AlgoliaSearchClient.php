<?php

namespace App\Services\Algolia;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class AlgoliaSearchClient
{
  public function __construct(
    private readonly Client $http = new Client(),
  ) {
  }

  public function isConfigured(): bool
  {
    return $this->appId() !== '' && $this->searchKey() !== '';
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public function searchAll(string $query, int $hitsPerIndex = 3): array
  {
    if (! $this->isConfigured() || trim($query) === '') {
      return [];
    }

    $indexes = array_filter((array) config('hsi_ai.algolia.indexes', []));
    $results = [];

    // Multiple logical types may point at the same physical index — query each once.
    $byIndex = [];
    foreach ($indexes as $type => $indexName) {
      if (! is_string($indexName) || $indexName === '') {
        continue;
      }
      $byIndex[$indexName][] = (string) $type;
    }

    foreach ($byIndex as $indexName => $types) {
      $hits = $this->searchIndex($indexName, $query, $hitsPerIndex);
      foreach ($hits as $hit) {
        $results[] = $this->normalizeHit($this->inferSourceType($types, $hit), $hit);
      }
    }

    return $results;
  }

  /**
   * Browse all records in an index (no ranking, paginated via cursor).
   *
   * @return array<int, array<string, mixed>>
   */
  public function browseAll(string $indexName): array
  {
    $appId = $this->appId();
    $browseKey = $this->browseKey();
    if ($appId === '' || $browseKey === '' || $indexName === '') {
      return [];
    }

    $url = sprintf('https://%s-dsn.algolia.net/1/indexes/%s/browse', $appId, rawurlencode($indexName));
    $allHits = [];
    $body = ['query' => '', 'hitsPerPage' => 1000];

    do {
      try {
        $res = $this->http->post($url, [
          'timeout' => 60,
          'headers' => [
            'X-Algolia-Application-Id' => $appId,
            'X-Algolia-API-Key' => $browseKey,
          ],
          'json' => $body,
        ]);
      } catch (GuzzleException) {
        break;
      }

      $data = json_decode((string) $res->getBody(), true);
      if (! is_array($data)) {
        break;
      }

      if (is_array($data['hits'] ?? null)) {
        $allHits = array_merge($allHits, $data['hits']);
      }

      $cursor = $data['cursor'] ?? null;
      $body = $cursor ? ['cursor' => $cursor] : [];
    } while ($cursor !== null);

    return $allHits;
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public function searchIndex(string $indexName, string $query, int $limit = 5): array
  {
    $appId = $this->appId();
    $searchKey = $this->searchKey();
    if ($appId === '' || $searchKey === '') {
      return [];
    }

    $url = sprintf('https://%s-dsn.algolia.net/1/indexes/%s/query', $appId, rawurlencode($indexName));

    try {
      $res = $this->http->post($url, [
        'timeout' => 20,
        'headers' => [
          'X-Algolia-Application-Id' => $appId,
          'X-Algolia-API-Key' => $searchKey,
        ],
        'json' => [
          'query' => $query,
          'hitsPerPage' => $limit,
        ],
      ]);
    } catch (GuzzleException) {
      return [];
    }

    $data = json_decode((string) $res->getBody(), true);

    return is_array($data['hits'] ?? null) ? $data['hits'] : [];
  }

  /**
   * @param  array<string, mixed>  $hit
   * @return array<string, mixed>
   */
  private function normalizeHit(string $type, array $hit): array
  {
    $title = (string) ($hit['title'] ?? $hit['name'] ?? $hit['headline'] ?? 'Untitled');
    $url = (string) ($hit['url'] ?? $hit['permalink'] ?? $hit['link'] ?? '');
    if ($url !== '' && ! str_starts_with($url, 'http')) {
      $url = rtrim((string) config('hsi_crawl.base_url', 'https://hsi.com'), '/').'/'.ltrim($url, '/');
    }

    $snippet = (string) ($hit['_snippetResult']['courseInformation']['value']
      ?? $hit['_snippetResult']['body']['value']
      ?? $hit['_snippetResult']['description']['value']
      ?? $hit['courseInformation']
      ?? $hit['description']
      ?? $hit['summary']
      ?? $hit['excerpt']
      ?? '');

    // blogContent is an array of text blocks — join the first few for context
    if ($snippet === '' && is_array($hit['blogContent'] ?? null)) {
      $snippet = implode(' ', array_slice($hit['blogContent'], 0, 2));
    }

    $snippet = strip_tags(str_replace(['<em>', '</em>'], '', $snippet));

    return [
      'source_type' => $type,
      'object_id' => (string) ($hit['objectID'] ?? ''),
      'title' => $title,
      'url' => $url,
      'snippet' => trim($snippet),
      'raw' => $hit,
    ];
  }

  /**
   * @param  array<int, string>  $types
   * @param  array<string, mixed>  $hit
   */
  private function inferSourceType(array $types, array $hit): string
  {
    $url = strtolower((string) ($hit['url'] ?? $hit['permalink'] ?? $hit['link'] ?? ''));

    if (str_contains($url, '/news')) {
      return 'news';
    }

    if (str_contains($url, '/blog')) {
      return 'blog';
    }

    $contentType = strtolower((string) ($hit['contentType'] ?? $hit['type'] ?? $hit['section'] ?? ''));
    if (str_contains($contentType, 'news')) {
      return 'news';
    }
    if (str_contains($contentType, 'blog')) {
      return 'blog';
    }

    return $types[0] ?? 'blog_news';
  }

  private function appId(): string
  {
    return trim((string) config('hsi_ai.algolia.app_id', ''));
  }

  private function searchKey(): string
  {
    return trim((string) config('hsi_ai.algolia.search_api_key', ''));
  }

  private function browseKey(): string
  {
    $key = trim((string) config('hsi_ai.algolia.browse_api_key', ''));

    return $key !== '' ? $key : $this->searchKey();
  }
}
