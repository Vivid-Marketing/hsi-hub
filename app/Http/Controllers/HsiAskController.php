<?php

namespace App\Http\Controllers;

use App\Services\Hsi\HsiAskService;
use App\Services\Hsi\HsiUnifiedSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HsiAskController extends Controller
{
  public function __construct(
    private readonly HsiAskService $askService,
    private readonly HsiUnifiedSearchService $unifiedSearch,
  ) {
  }

  public function ask(Request $request): JsonResponse
  {
    $question = trim((string) $request->input('q', $request->input('question', '')));
    if ($question === '') {
      return response()->json([
        'ok' => false,
        'error' => 'Missing question parameter: q',
      ], 422);
    }

    try {
      $result = $this->askService->ask($question);
    } catch (\Throwable $e) {
      return response()->json([
        'ok' => false,
        'error' => $e->getMessage(),
      ], 503);
    }

    return response()->json([
      'ok' => true,
      'mode' => 'ai',
      ...$result,
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

    $pageLimit = max(1, min(50, (int) $request->query('page_limit', 10)));
    $algoliaLimit = max(1, min(20, (int) $request->query('algolia_limit', 5)));

    $result = $this->unifiedSearch->search($q, $pageLimit, $algoliaLimit);

    return response()->json([
      'ok' => true,
      'mode' => 'keyword',
      ...$result,
      'count' => count($result['pages']) + count($result['algolia']),
    ]);
  }
}
