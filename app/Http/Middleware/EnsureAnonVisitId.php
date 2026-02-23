<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureAnonVisitId
{
    public function handle(Request $request, Closure $next): Response
    {
        $anonVisitId = (string) $request->cookie('pr_avid', '');
        $response = $next($request);

        if ($anonVisitId !== '') {
            return $response;
        }

        $cookie = cookie()->make(
            'pr_avid',
            (string) Str::uuid(),
            60 * 24,
            '/',
            null,
            app()->isProduction(),
            true,
            false,
            'lax'
        );

        $response->headers->setCookie($cookie);

        return $response;
    }
}
