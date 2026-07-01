<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HsiAiCors
{
  /**
   * CORS for browser-based hsi.com → hub AI search API calls.
   */
  public function handle(Request $request, Closure $next): Response
  {
    $origin = (string) $request->headers->get('Origin', '');
    $allowed = array_values(array_filter(array_map('trim', (array) config('hsi_ai.allowed_origins', []))));

    if ($request->getMethod() === 'OPTIONS') {
      $response = response('', 204);
    } else {
      try {
        $response = $next($request);
      } catch (\Throwable $e) {
        $response = response()->json(['ok' => false, 'error' => $e->getMessage()], 503);
      }
    }

    if ($origin !== '' && in_array($origin, $allowed, true)) {
      $response->headers->set('Access-Control-Allow-Origin', $origin);
      $response->headers->set('Vary', 'Origin');
    }

    $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization');

    return $response;
  }
}
