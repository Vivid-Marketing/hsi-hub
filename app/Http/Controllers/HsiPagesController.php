<?php

namespace App\Http\Controllers;

use App\Models\HsiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HsiPagesController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([
                'ok' => false,
                'error' => 'Missing query parameter: q',
            ], 422);
        }

        $limit = (int) $request->query('limit', 10);
        $limit = max(1, min(50, $limit));

        $rows = HsiPage::query()
            ->where('crawl_status', '!=', 'error')
            ->where(function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query
                    ->where('title', 'like', $like)
                    ->orWhere('meta_description', 'like', $like)
                    ->orWhere('body_text', 'like', $like)
                    ->orWhere('canonical_url', 'like', $like);
            })
            ->orderByDesc('last_crawled_at')
            ->limit($limit)
            ->get([
                'id',
                'title',
                'meta_description',
                'canonical_url',
                'fetched_url',
                'dedupe_key',
                'crawl_status',
                'http_status',
                'last_crawled_at',
                'body_text',
            ]);

        $results = $rows->map(function (HsiPage $p) use ($q) {
            $url = $p->canonical_url ?: ($p->fetched_url ?: $p->dedupe_key);
            $snippet = $this->makeSnippet((string) ($p->body_text ?? ''), $q);

            return [
                'id' => $p->id,
                'title' => $p->title,
                'meta_description' => $p->meta_description,
                'url' => $url,
                'crawl_status' => $p->crawl_status,
                'http_status' => $p->http_status,
                'last_crawled_at' => optional($p->last_crawled_at)->toIso8601String(),
                'snippet' => $snippet,
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'q' => $q,
            'count' => $results->count(),
            'results' => $results,
        ]);
    }

    private function makeSnippet(string $body, string $q): ?string
    {
        $body = trim($body);
        if ($body === '') {
            return null;
        }

        $pos = stripos($body, $q);
        if ($pos === false) {
            return mb_substr($body, 0, 240);
        }

        $start = max(0, $pos - 80);
        $snippet = mb_substr($body, $start, 240);
        return $snippet;
    }
}

