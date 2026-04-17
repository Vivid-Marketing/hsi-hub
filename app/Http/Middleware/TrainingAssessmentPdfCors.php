<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrainingAssessmentPdfCors
{
    /**
     * Minimal CORS for public PDF-generation endpoints.
     * We only set CORS headers when Origin is allow-listed.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = (string) $request->headers->get('Origin', '');
        $allowed = (array) config('training_assessment_pdf.allowed_origins', []);

        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 204);
        } else {
            $response = $next($request);
        }

        if ($origin !== '' && in_array($origin, $allowed, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Vary', 'Origin');
        }

        $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        return $response;
    }
}

