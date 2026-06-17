<?php

namespace App\Services\Hsi;

use App\Models\HsiPage;
use Illuminate\Support\Collection;

class HsiPageSearchService
{
    public function __construct(
        private readonly HsiPageClassifier $classifier,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function tokenize(string $q): array
    {
        $q = mb_strtolower(trim($q));
        if ($q === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', $q) ?: [];

        return array_values(array_unique(array_filter($parts, fn ($p) => mb_strlen($p) >= 2)));
    }

    /**
     * @return Collection<int, array{page: HsiPage, score: int, matches: array<string, bool>}>
     */
    public function ranked(string $q, int $candidateLimit = 200): Collection
    {
        $terms = $this->tokenize($q);
        if ($terms === []) {
            return collect();
        }

        $query = HsiPage::query()
            ->where(function ($builder) {
                $builder->whereNull('crawl_status')
                    ->orWhere('crawl_status', '!=', 'error');
            });

        $query->where(function ($builder) use ($terms) {
            foreach ($terms as $term) {
                $like = '%'.$term.'%';
                $builder->where(function ($inner) use ($like) {
                    $inner
                        ->where('title', 'like', $like)
                        ->orWhere('meta_description', 'like', $like)
                        ->orWhere('body_text', 'like', $like)
                        ->orWhere('canonical_url', 'like', $like)
                        ->orWhere('ai_summary', 'like', $like);
                });
            }
        });

        $pages = $query
            ->orderByDesc('last_crawled_at')
            ->limit($candidateLimit)
            ->get();

        return $pages
            ->map(fn (HsiPage $page) => [
                'page' => $page,
                'score' => $this->score($page, $terms),
                'matches' => $this->matchFields($page, $terms),
            ])
            ->filter(fn (array $row) => $row['score'] > 0)
            ->sortByDesc('score')
            ->values();
    }

    /**
     * @param  array<int, string>  $terms
     */
    public function score(HsiPage $page, array $terms): int
    {
        $score = 0;

        $title = mb_strtolower((string) ($page->title ?? ''));
        $meta = mb_strtolower((string) ($page->meta_description ?? ''));
        $summary = mb_strtolower((string) ($page->ai_summary ?? ''));
        $body = mb_strtolower((string) ($page->body_text ?? ''));
        $h1s = array_map('mb_strtolower', (array) ($page->h1s ?? []));
        $h2s = array_map('mb_strtolower', (array) ($page->h2s ?? []));

        foreach ($terms as $term) {
            if ($title !== '' && str_contains($title, $term)) {
                $score += str_contains($title, $term) && $title === $term ? 120 : 100;
            }

            foreach ($h1s as $h1) {
                if ($h1 !== '' && str_contains($h1, $term)) {
                    $score += 70;
                }
            }

            foreach ($h2s as $h2) {
                if ($h2 !== '' && str_contains($h2, $term)) {
                    $score += 50;
                }
            }

            if ($meta !== '' && str_contains($meta, $term)) {
                $score += 35;
            }

            if ($summary !== '' && str_contains($summary, $term)) {
                $score += 30;
            }

            if ($body !== '' && str_contains($body, $term)) {
                $score += 15;
            }
        }

        return $score;
    }

    /**
     * @param  array<int, string>  $terms
     * @return array<string, bool>
     */
    public function matchFields(HsiPage $page, array $terms): array
    {
        $title = mb_strtolower((string) ($page->title ?? ''));
        $body = mb_strtolower((string) ($page->body_text ?? ''));
        $h1Text = mb_strtolower(implode(' ', (array) ($page->h1s ?? [])));
        $h2Text = mb_strtolower(implode(' ', (array) ($page->h2s ?? [])));

        $matches = [
            'title' => false,
            'h1' => false,
            'h2' => false,
            'body' => false,
        ];

        foreach ($terms as $term) {
            if (! $matches['title'] && $title !== '' && str_contains($title, $term)) {
                $matches['title'] = true;
            }
            if (! $matches['h1'] && $h1Text !== '' && str_contains($h1Text, $term)) {
                $matches['h1'] = true;
            }
            if (! $matches['h2'] && $h2Text !== '' && str_contains($h2Text, $term)) {
                $matches['h2'] = true;
            }
            if (! $matches['body'] && $body !== '' && str_contains($body, $term)) {
                $matches['body'] = true;
            }
        }

        return $matches;
    }

    public function pageUrl(HsiPage $page): string
    {
        return $page->canonical_url ?: ($page->fetched_url ?: $page->dedupe_key);
    }

    public function snippet(string $body, string $q, int $length = 240): ?string
    {
        $body = trim($body);
        if ($body === '') {
            return null;
        }

        $terms = $this->tokenize($q);
        $pos = false;
        foreach ($terms as $term) {
            $found = stripos($body, $term);
            if ($found !== false) {
                $pos = $pos === false ? $found : min($pos, $found);
            }
        }

        if ($pos === false) {
            return mb_substr($body, 0, $length);
        }

        $start = max(0, $pos - 80);

        return mb_substr($body, $start, $length);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchResult(HsiPage $page, string $q, int $score, array $matches): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'meta_description' => $page->meta_description,
            'url' => $this->pageUrl($page),
            'source_group' => $page->source_group,
            'page_type' => $page->page_type,
            'crawl_status' => $page->crawl_status,
            'http_status' => $page->http_status,
            'last_crawled_at' => optional($page->last_crawled_at)->toIso8601String(),
            'score' => $score,
            'matched_in' => array_keys(array_filter($matches)),
            'snippet' => $this->snippet((string) ($page->body_text ?? ''), $q),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toContextBlock(HsiPage $page, string $q, int $score): array
    {
        $summary = $page->ai_summary
            ?: $page->meta_description
            ?: $this->snippet((string) ($page->body_text ?? ''), $q, 320);

        $headings = array_values(array_unique(array_filter(array_merge(
            (array) ($page->h1s ?? []),
            array_slice((array) ($page->h2s ?? []), 0, 5),
        ))));

        return [
            'id' => $page->id,
            'url' => $this->pageUrl($page),
            'title' => $page->title,
            'source_group' => $page->source_group,
            'page_type' => $page->page_type,
            'summary' => $summary,
            'headings' => $headings,
            'topics' => (array) ($page->primary_topics ?? []),
            'keywords' => (array) ($page->search_keywords ?? []),
            'excerpt' => $this->snippet((string) ($page->body_text ?? ''), $q, 500),
            'score' => $score,
        ];
    }
}
