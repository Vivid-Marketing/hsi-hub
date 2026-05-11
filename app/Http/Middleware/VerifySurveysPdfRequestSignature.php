<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySurveysPdfRequestSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $sharedSecret = (string) config('surveys_pdf.shared_secret', '');
        if ($sharedSecret === '') {
            return response('Missing shared secret configuration.', 500);
        }

        $expectedRequestedBy = (string) config('surveys_pdf.requested_by', 'hsi-surveys');
        $requestedBy = (string) $request->headers->get('x-requested-by', '');
        if ($requestedBy === '' || $requestedBy !== $expectedRequestedBy) {
            return response('Invalid requester.', 401);
        }

        $timestamp = (string) $request->headers->get('x-internal-timestamp', '');
        $signature = (string) $request->headers->get('x-internal-signature', '');

        if ($timestamp === '' || $signature === '') {
            return response('Missing signature headers.', 401);
        }

        $tsInt = (int) $timestamp;
        $maxSkew = (int) config('surveys_pdf.max_skew_seconds', 300);
        if ($tsInt <= 0 || abs(time() - $tsInt) > $maxSkew) {
            return response('Expired signature.', 401);
        }

        // Important: must use raw request bytes exactly as received.
        $rawBody = $request->getContent();
        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $sharedSecret);

        if (! hash_equals($expected, $signature)) {
            return response('Invalid signature.', 401);
        }

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        return $response;
    }
}

