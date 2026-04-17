<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalRequestSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigins = array_values(array_filter(array_map('trim', (array) config('course_catalog_pdf.allowed_origins', []))));
        $origin = (string) $request->headers->get('origin', '');

        // Match legacy preflight behavior.
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 200);

            if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
                $response->headers->set('Access-Control-Max-Age', '86400');
                $acrHeaders = (string) $request->headers->get('access-control-request-headers', '');
                if ($acrHeaders !== '') {
                    $response->headers->set('Access-Control-Allow-Headers', $acrHeaders);
                }
            }

            return $response;
        }

        $sharedSecret = (string) config('course_catalog_pdf.shared_secret', '');
        if ($sharedSecret === '') {
            return response('Missing shared secret configuration.', 500);
        }

        $timestamp = (string) $request->headers->get('x-internal-timestamp', '');
        $signature = (string) $request->headers->get('x-internal-signature', '');

        if ($timestamp === '' || $signature === '') {
            return response('Missing signature headers.', 401);
        }

        $tsInt = (int) $timestamp;
        if (abs(time() - $tsInt) > 300) {
            return response('Expired signature.', 401);
        }

        $rawBody = $request->getContent();
        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $sharedSecret);

        if (! hash_equals($expected, $signature)) {
            return response('Invalid signature.', 401);
        }

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        // Mirror legacy CORS header behavior for allowed origins.
        if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }
}

