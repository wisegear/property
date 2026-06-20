<?php

namespace App\Http\Middleware;

use App\Services\AnalyticsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrackAnalytics
{
    public function __construct(
        private AnalyticsService $analyticsService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->analyticsService->shouldTrackRequest($request)) {
            return $next($request);
        }

        $cookieName = (string) config('analytics.cookie_name', 'pr_avid');
        $anonVisitId = (string) $request->cookie($cookieName, '');

        if ($anonVisitId === '') {
            $anonVisitId = (string) Str::uuid();
        }

        $request->attributes->set('analytics_anon_visit_id', $anonVisitId);

        $botMatch = $this->analyticsService->classifyBot(
            $request->userAgent(),
            $request->ip()
        );

        $this->analyticsService->recordVisit($request, $anonVisitId, $botMatch);

        if ($request->isMethod('get') && ! $botMatch['is_bot']) {
            $this->analyticsService->recordPageView($request, $anonVisitId);
        }

        $response = $next($request);

        if (! $request->hasCookie($cookieName)) {
            $response->headers->setCookie(cookie()->make(
                $cookieName,
                $anonVisitId,
                60 * 24 * 365,
                '/',
                null,
                app()->isProduction(),
                true,
                false,
                'lax'
            ));
        }

        return $response;
    }
}
