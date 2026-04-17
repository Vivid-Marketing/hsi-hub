<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCronToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('cron.token', '');
        if ($expected === '') {
            return response('Cron token is not configured.', 500);
        }

        $provided = (string) $request->query('token', '');
        if (! hash_equals($expected, $provided)) {
            return response('Unauthorized.', 401);
        }

        return $next($request);
    }
}

