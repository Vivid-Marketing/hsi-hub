<?php

namespace App\Http\Controllers;

use App\Models\HsiPage;
use App\Services\Hsi\HsiPageSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HsiPagesController extends Controller
{
    public function __construct(
        private readonly HsiPageSearchService $searchService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));

        $query = HsiPage::query()->orderByDesc('last_crawled_at');

        if ($request->filled('crawl_status')) {
            $query->where('crawl_status', (string) $request->query('crawl_status'));
        }

        if ($request->filled('source_group')) {
            $query->where('source_group', (string) $request->query('source_group'));
        }

        if ($request->filled('page_type')) {
            $query->where('page_type', (string) $request->query('page_type'));
        }

        $pages = $query->paginate($perPage, [
            'id',
            'title',
            'canonical_url',
            'fetched_url',
            'dedupe_key',
            'source_group',
            'page_type',
            'crawl_status',
            'http_status',
            'content_hash',
            'last_crawled_at',
            'last_error',
        ]);

        return response()->json([
            'ok' => true,
            'data' => $pages->items(),
            'meta' => [
                'current_page' => $pages->currentPage(),
                'per_page' => $pages->perPage(),
                'total' => $pages->total(),
                'last_page' => $pages->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $page = HsiPage::query()->find($id);
        if ($page === null) {
            return response()->json([
                'ok' => false,
                'error' => 'Page not found',
            ], 404);
        }

        $data = $page->toArray();
        if (! $request->boolean('include_raw_html')) {
            unset($data['raw_html']);
        }

        return response()->json([
            'ok' => true,
            'data' => $data,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([
                'ok' => false,
                'error' => 'Missing query parameter: q',
            ], 422);
        }

        $limit = max(1, min(50, (int) $request->query('limit', 10)));

        $ranked = $this->searchService->ranked($q)->take($limit);

        $results = $ranked->map(fn (array $row) => $this->searchService->toSearchResult(
            $row['page'],
            $q,
            $row['score'],
            $row['matches'],
        ))->values();

        return response()->json([
            'ok' => true,
            'q' => $q,
            'count' => $results->count(),
            'results' => $results,
        ]);
    }

    public function retrieve(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([
                'ok' => false,
                'error' => 'Missing query parameter: q',
            ], 422);
        }

        $limit = max(1, min(10, (int) $request->query('limit', 5)));

        $ranked = $this->searchService->ranked($q)->take($limit);

        $blocks = $ranked->map(fn (array $row) => $this->searchService->toContextBlock(
            $row['page'],
            $q,
            $row['score'],
        ))->values();

        return response()->json([
            'ok' => true,
            'q' => $q,
            'count' => $blocks->count(),
            'context_blocks' => $blocks,
        ]);
    }
}
