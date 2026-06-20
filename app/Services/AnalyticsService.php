<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsPageView;
use App\Models\AnalyticsVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AnalyticsService
{
    public function recordVisit(Request $request, string $anonVisitId, bool $isBot): void
    {
        if (! config('analytics.enabled')) {
            return;
        }

        $now = now();
        $ipAddress = $this->resolveIpAddress($request);
        $countryCode = $this->resolveCountryCode($request);
        $userAgent = $this->truncate($request->userAgent(), 2000);
        $landingPage = $this->currentUrl($request);
        $referrer = $this->truncate($request->headers->get('referer'), 2000);

        $visit = AnalyticsVisit::query()->firstOrNew([
            'anon_visit_id' => $anonVisitId,
        ]);

        if (! $visit->exists) {
            $visit->fill([
                'ip_address' => $ipAddress,
                'country_code' => $countryCode,
                'user_agent' => $userAgent,
                'device_type' => $this->resolveDeviceType($userAgent),
                'browser' => $this->resolveBrowser($userAgent),
                'referrer' => $referrer,
                'landing_page' => $landingPage,
                'is_bot' => $isBot,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ])->save();

            return;
        }

        $visit->fill([
            'ip_address' => $ipAddress ?? $visit->ip_address,
            'country_code' => $countryCode ?? $visit->country_code,
            'user_agent' => $userAgent ?? $visit->user_agent,
            'device_type' => $this->resolveDeviceType($userAgent) ?? $visit->device_type,
            'browser' => $this->resolveBrowser($userAgent) ?? $visit->browser,
            'referrer' => $visit->referrer ?: $referrer,
            'landing_page' => $visit->landing_page ?: $landingPage,
            'is_bot' => $visit->is_bot || $isBot,
            'last_seen_at' => $now,
        ])->save();
    }

    public function recordPageView(Request $request, string $anonVisitId): void
    {
        if (! config('analytics.enabled')) {
            return;
        }

        AnalyticsPageView::query()->create([
            'anon_visit_id' => $anonVisitId,
            'ip_address' => $this->resolveIpAddress($request),
            'url' => $this->currentUrl($request),
            'route_name' => $request->route()?->getName(),
            'page_type' => $this->resolvePageType($request),
            'viewed_at' => now(),
        ]);
    }

    public function recordEvent(string $eventType, string $eventKey, array $payload = []): void
    {
        if (! config('analytics.enabled')) {
            return;
        }

        $request = app()->bound('request') ? app('request') : null;

        AnalyticsEvent::query()->create([
            'anon_visit_id' => $this->resolveAnonVisitId($request),
            'ip_address' => $request instanceof Request ? $this->resolveIpAddress($request) : null,
            'event_type' => $this->truncate(trim($eventType), 50) ?: 'event',
            'event_key' => $this->truncate(trim($eventKey), 100) ?: 'unknown',
            'payload' => $this->sanitizePayload($payload),
            'created_at' => now(),
        ]);
    }

    public function getAdminAnalyticsStats(int $days = 30): array
    {
        $days = max(7, $days);
        $ttl = max(60, (int) config('analytics.admin_dashboard_cache_ttl', 900));

        return Cache::remember("analytics:admin:stats:{$days}", now()->addSeconds($ttl), function () use ($days): array {
            return [
                'periods' => collect([7, 30, 90])->mapWithKeys(function (int $period): array {
                    $since = now()->subDays($period);

                    return [$period => [
                        'visitors' => AnalyticsVisit::query()->where('last_seen_at', '>=', $since)->count(),
                        'human_visitors' => AnalyticsVisit::query()->where('last_seen_at', '>=', $since)->where('is_bot', false)->count(),
                        'page_views' => AnalyticsPageView::query()->where('viewed_at', '>=', $since)->count(),
                        'events' => AnalyticsEvent::query()->where('created_at', '>=', $since)->count(),
                        'bot_visits' => AnalyticsVisit::query()->where('last_seen_at', '>=', $since)->where('is_bot', true)->count(),
                    ]];
                })->all(),
                'page_views_per_day' => $this->groupCountsByDay('analytics_page_views', 'viewed_at', $days),
                'events_per_day' => $this->groupCountsByDay('analytics_events', 'created_at', $days),
                'top_page_types' => AnalyticsPageView::query()
                    ->select('page_type')
                    ->selectRaw('COUNT(*) as total')
                    ->where('viewed_at', '>=', now()->subDays($days))
                    ->whereNotNull('page_type')
                    ->groupBy('page_type')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->get(),
                'top_landing_pages' => AnalyticsVisit::query()
                    ->select('landing_page')
                    ->selectRaw('COUNT(*) as total')
                    ->where('first_seen_at', '>=', now()->subDays($days))
                    ->whereNotNull('landing_page')
                    ->groupBy('landing_page')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->get(),
                'top_events' => AnalyticsEvent::query()
                    ->select('event_type', 'event_key')
                    ->selectRaw('COUNT(*) as total')
                    ->where('created_at', '>=', now()->subDays($days))
                    ->groupBy('event_type', 'event_key')
                    ->orderByDesc('total')
                    ->limit(12)
                    ->get(),
                'top_ip_addresses' => AnalyticsVisit::query()
                    ->select('ip_address')
                    ->selectRaw('COUNT(*) as total_visits')
                    ->where('last_seen_at', '>=', now()->subDays($days))
                    ->whereNotNull('ip_address')
                    ->groupBy('ip_address')
                    ->orderByDesc('total_visits')
                    ->limit(12)
                    ->get(),
                'repeat_visitors_by_ip' => AnalyticsVisit::query()
                    ->select('ip_address')
                    ->selectRaw('COUNT(DISTINCT anon_visit_id) as unique_visitors')
                    ->selectRaw('COUNT(*) as total_visits')
                    ->where('last_seen_at', '>=', now()->subDays(90))
                    ->where('is_bot', false)
                    ->whereNotNull('ip_address')
                    ->groupBy('ip_address')
                    ->havingRaw('COUNT(DISTINCT anon_visit_id) > 1')
                    ->orderByDesc('unique_visitors')
                    ->orderByDesc('total_visits')
                    ->limit(12)
                    ->get(),
                'bot_traffic_count' => AnalyticsVisit::query()
                    ->where('last_seen_at', '>=', now()->subDays($days))
                    ->where('is_bot', true)
                    ->count(),
                'recent_visits' => AnalyticsVisit::query()
                    ->orderByDesc('last_seen_at')
                    ->limit(20)
                    ->get([
                        'anon_visit_id',
                        'ip_address',
                        'country_code',
                        'device_type',
                        'browser',
                        'landing_page',
                        'is_bot',
                        'first_seen_at',
                        'last_seen_at',
                    ]),
                'suspicious_high_frequency_ips' => AnalyticsPageView::query()
                    ->select('ip_address')
                    ->selectRaw('COUNT(*) as page_view_count')
                    ->selectRaw('COUNT(DISTINCT anon_visit_id) as unique_visitors')
                    ->selectRaw('MAX(viewed_at) as last_seen_at')
                    ->where('viewed_at', '>=', now()->subDays(1))
                    ->whereNotNull('ip_address')
                    ->groupBy('ip_address')
                    ->havingRaw('COUNT(*) >= 25')
                    ->orderByDesc('page_view_count')
                    ->limit(15)
                    ->get(),
            ];
        });
    }

    public function getSponsorDashboardStats(int $days = 30): array
    {
        $days = max(30, $days);
        $ttl = max(300, (int) config('analytics.sponsor_dashboard_cache_ttl', 3600));

        return Cache::remember("analytics:sponsor:stats:{$days}", now()->addSeconds($ttl), function () use ($days): array {
            $windows = collect([30, 90])->mapWithKeys(function (int $period): array {
                $since = now()->subDays($period);
                $visits = AnalyticsVisit::query()
                    ->where('is_bot', false)
                    ->where('last_seen_at', '>=', $since);

                return [$period => [
                    'unique_visitors' => (clone $visits)->count(),
                    'page_views' => AnalyticsPageView::query()
                        ->join('analytics_visits', 'analytics_visits.anon_visit_id', '=', 'analytics_page_views.anon_visit_id')
                        ->where('analytics_visits.is_bot', false)
                        ->where('analytics_page_views.viewed_at', '>=', $since)
                        ->count(),
                ]];
            })->all();

            $since = now()->subDays($days);
            $basePageViews = AnalyticsPageView::query()
                ->join('analytics_visits', 'analytics_visits.anon_visit_id', '=', 'analytics_page_views.anon_visit_id')
                ->where('analytics_visits.is_bot', false)
                ->where('analytics_page_views.viewed_at', '>=', $since);

            $baseEvents = AnalyticsEvent::query()
                ->join('analytics_visits', 'analytics_visits.anon_visit_id', '=', 'analytics_events.anon_visit_id')
                ->where('analytics_visits.is_bot', false)
                ->where('analytics_events.created_at', '>=', $since);

            $ukVisitors = AnalyticsVisit::query()
                ->where('is_bot', false)
                ->where('last_seen_at', '>=', $since)
                ->count();

            $gbVisitors = AnalyticsVisit::query()
                ->where('is_bot', false)
                ->where('last_seen_at', '>=', $since)
                ->where('country_code', 'GB')
                ->count();

            return [
                'windows' => $windows,
                'uk_visitor_percentage' => $ukVisitors > 0 ? round(($gbVisitors / $ukVisitors) * 100, 1) : 0.0,
                'top_content_categories' => (clone $basePageViews)
                    ->select('analytics_page_views.page_type')
                    ->selectRaw('COUNT(*) as total')
                    ->whereNotNull('analytics_page_views.page_type')
                    ->groupBy('analytics_page_views.page_type')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->get(),
                'top_landing_pages' => AnalyticsVisit::query()
                    ->select('landing_page')
                    ->selectRaw('COUNT(*) as total')
                    ->where('is_bot', false)
                    ->where('first_seen_at', '>=', $since)
                    ->whereNotNull('landing_page')
                    ->groupBy('landing_page')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->get(),
                'event_totals' => [
                    'postcode_property_searches' => $this->countEvents((clone $baseEvents), 'search', ['property_search', 'property_area_search']),
                    'street_searches' => $this->countEvents((clone $baseEvents), 'search', ['street_search']),
                    'epc_lookups' => $this->countEvents((clone $baseEvents), 'lookup', ['epc_postcode', 'epc_scotland_postcode']),
                    'deprivation_lookups' => $this->countEvents((clone $baseEvents), 'lookup', ['deprivation_lookup']),
                    'mortgage_calculator_uses' => $this->countEvents((clone $baseEvents), 'calculator', ['mortgage_calculator']),
                    'stamp_duty_calculator_uses' => $this->countEvents((clone $baseEvents), 'calculator', ['stamp_duty']),
                ],
                'dataset_scale_cards' => $this->datasetScaleCards(),
            ];
        });
    }

    public function isBot(?string $userAgent): bool
    {
        if (! is_string($userAgent) || trim($userAgent) === '') {
            return false;
        }

        foreach (config('analytics.bot_user_agent_patterns', []) as $pattern) {
            if (stripos($userAgent, (string) $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    public function shouldTrackRequest(Request $request): bool
    {
        if (! config('analytics.enabled')) {
            return false;
        }

        $path = trim($request->path(), '/');
        $firstSegment = Str::before($path, '/');
        $routeName = $request->route()?->getName();

        foreach (config('analytics.skipped_route_prefixes', []) as $prefix) {
            $prefix = trim((string) $prefix, '/');

            if ($prefix === '') {
                continue;
            }

            if ($firstSegment === $prefix || Str::startsWith($path, $prefix.'/')) {
                return false;
            }
        }

        if ($request->routeIs('login', 'logout', 'register', 'password.*', 'verification.*', 'admin.*')) {
            return false;
        }

        return ! $request->expectsJson() || $request->isMethod('post');
    }

    public function resolveAnonVisitId(?Request $request): ?string
    {
        if (! $request instanceof Request) {
            return null;
        }

        $cookieName = (string) config('analytics.cookie_name', 'pr_avid');
        $candidate = $request->attributes->get('analytics_anon_visit_id', $request->cookie($cookieName));

        if (! is_string($candidate) || trim($candidate) === '') {
            return null;
        }

        return $this->truncate(trim($candidate), 36);
    }

    public function resolvePageType(Request $request): string
    {
        $routeName = (string) ($request->route()?->getName() ?? '');
        $path = trim($request->path(), '/');
        $segment = Str::before($path, '/');

        return match (true) {
            $routeName === 'home', $path === '' => 'home',
            $routeName === 'about', $segment === 'about' => 'about',
            Str::startsWith($routeName, 'property.'), $segment === 'property' => 'property',
            Str::startsWith($routeName, 'epc.'), $segment === 'epc' => 'epc',
            Str::startsWith($routeName, 'deprivation.'), $segment === 'deprivation' => 'deprivation',
            Str::startsWith($routeName, 'insights.'), $segment === 'insights' => 'insights',
            Str::startsWith($routeName, 'rental.'), $segment === 'rental' => 'rental',
            Str::startsWith($routeName, 'blog.'), $segment === 'blog' => 'blog',
            Str::startsWith($routeName, 'support.'), $segment === 'support' => 'support',
            Str::startsWith($routeName, 'profile.'), $segment === 'profile' => 'profile',
            $segment === 'mortgage-calculator' => 'mortgage',
            $segment === 'stamp-duty' => 'stamp-duty',
            $segment === 'economic-dashboard' => 'economic',
            $segment === 'hpi' || $routeName === 'hpi.home' || $routeName === 'hpi.overview' => 'hpi',
            $segment === 'interest-rates' => 'interest-rates',
            $segment === 'unemployment' => 'unemployment',
            $segment === 'inflation' => 'inflation',
            $segment === 'wage-growth' => 'wage-growth',
            $segment === 'repossessions' => 'repossessions',
            $segment === 'approvals' => 'mortgage-approvals',
            $segment === 'arrears' => 'arrears',
            default => $segment !== '' ? $segment : 'page',
        };
    }

    private function countEvents($query, string $eventType, array $eventKeys): int
    {
        return (int) $query
            ->where('analytics_events.event_type', $eventType)
            ->whereIn('analytics_events.event_key', $eventKeys)
            ->count();
    }

    private function groupCountsByDay(string $table, string $column, int $days): Collection
    {
        return DB::table($table)
            ->selectRaw("DATE({$column}) as day")
            ->selectRaw('COUNT(*) as total')
            ->where($column, '>=', now()->subDays($days))
            ->groupBy('day')
            ->orderBy('day')
            ->get();
    }

    private function currentUrl(Request $request): string
    {
        return $this->truncate($request->url(), 2000) ?? '/';
    }

    private function resolveIpAddress(Request $request): ?string
    {
        $ipAddress = $request->ip();

        if (! is_string($ipAddress) || trim($ipAddress) === '') {
            return null;
        }

        return $this->truncate(trim($ipAddress), 64);
    }

    private function resolveCountryCode(Request $request): ?string
    {
        $countryCode = strtoupper(trim((string) $request->headers->get('CF-IPCountry', '')));

        if (! preg_match('/^[A-Z]{2}$/', $countryCode) || in_array($countryCode, ['XX', 'T1'], true)) {
            return null;
        }

        return $countryCode;
    }

    private function resolveDeviceType(?string $userAgent): ?string
    {
        if (! is_string($userAgent) || $userAgent === '') {
            return null;
        }

        return match (true) {
            preg_match('/tablet|ipad/i', $userAgent) === 1 => 'tablet',
            preg_match('/mobile|iphone|android/i', $userAgent) === 1 => 'mobile',
            default => 'desktop',
        };
    }

    private function resolveBrowser(?string $userAgent): ?string
    {
        if (! is_string($userAgent) || $userAgent === '') {
            return null;
        }

        return match (true) {
            preg_match('/edg/i', $userAgent) === 1 => 'Edge',
            preg_match('/chrome|crios/i', $userAgent) === 1 => 'Chrome',
            preg_match('/firefox/i', $userAgent) === 1 => 'Firefox',
            preg_match('/safari/i', $userAgent) === 1 && preg_match('/chrome|crios/i', $userAgent) !== 1 => 'Safari',
            preg_match('/msie|trident/i', $userAgent) === 1 => 'Internet Explorer',
            default => 'Other',
        };
    }

    private function sanitizePayload(array $payload): ?array
    {
        $safePayload = [];

        foreach ($payload as $key => $value) {
            if (! is_string($key) || $key === '' || preg_match('/(email|ip|session|cookie|header|token|user_?id|identifier)/i', $key) === 1) {
                continue;
            }

            if (is_array($value)) {
                $nested = $this->sanitizePayload($value);
                if ($nested !== null) {
                    $safePayload[$key] = $nested;
                }

                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value)) {
                $safePayload[$key] = $value;

                continue;
            }

            if (is_string($value)) {
                $safePayload[$key] = $this->truncate(trim($value), 120);
            }
        }

        return $safePayload === [] ? null : $safePayload;
    }

    private function datasetScaleCards(): array
    {
        return [
            [
                'label' => 'Property transactions',
                'value' => Schema::hasTable('land_registry') ? (int) DB::table('land_registry')->count() : 0,
            ],
            [
                'label' => 'EPC records',
                'value' => (Schema::hasTable('epc_certificates') ? (int) DB::table('epc_certificates')->count() : 0)
                    + (Schema::hasTable('epc_certificates_scotland') ? (int) DB::table('epc_certificates_scotland')->count() : 0),
            ],
            [
                'label' => 'Street pages',
                'value' => $this->countJsonItems(public_path('data/property_streets.json')),
            ],
            [
                'label' => 'Postcode pages',
                'value' => $this->countJsonItems(public_path('data/epc-postcodes.json'), 'postcodes.england_wales')
                    + $this->countJsonItems(public_path('data/epc-postcodes.json'), 'postcodes.scotland'),
            ],
        ];
    }

    private function countJsonItems(string $path, ?string $dataPath = null): int
    {
        if (! File::exists($path)) {
            return 0;
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            return 0;
        }

        $target = $dataPath === null ? $decoded : data_get($decoded, $dataPath, []);

        return is_array($target) ? count($target) : 0;
    }

    private function truncate(?string $value, int $length): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, $length);
    }
}
