<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsPageView;
use App\Models\AnalyticsVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AnalyticsService
{
    public function recordVisit(Request $request, string $anonVisitId, array $botMatch): void
    {
        if (! $this->canRecordVisits()) {
            return;
        }

        $now = now();
        $ipAddress = $this->resolveIpAddress($request);
        $countryCode = $this->resolveCountryCode($request);
        $userAgent = $this->truncate($request->userAgent(), 2000);
        $landingPage = $this->currentUrl($request);
        $referrer = $this->truncate($request->headers->get('referer'), 2000);
        $isBot = (bool) ($botMatch['is_bot'] ?? false);
        $botName = $this->truncate($botMatch['bot_name'] ?? null, 100);

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
                'bot_name' => $botName,
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
            'bot_name' => $visit->bot_name ?: $botName,
            'last_seen_at' => $now,
        ])->save();
    }

    public function recordPageView(Request $request, string $anonVisitId): void
    {
        if (! $this->canRecordPageViews()) {
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
        if (! $this->canRecordEvents()) {
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

    public function isBot(?string $userAgent): bool
    {
        return $this->classifyBot($userAgent, null)['is_bot'];
    }

    /**
     * @return array{is_bot: bool, bot_name: string|null}
     */
    public function classifyBot(?string $userAgent, ?string $ipAddress): array
    {
        $botName = $this->matchBotByUserAgent($userAgent) ?? $this->matchBotByIpAddress($ipAddress);

        return [
            'is_bot' => $botName !== null,
            'bot_name' => $botName,
        ];
    }

    public function shouldTrackRequest(Request $request): bool
    {
        if (! config('analytics.enabled') || ! $this->canRecordVisits()) {
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

    private function currentUrl(Request $request): string
    {
        return $this->truncate($request->url(), 2000) ?? '/';
    }

    private function canRecordVisits(): bool
    {
        return $this->hasTable('analytics_visits');
    }

    private function canRecordPageViews(): bool
    {
        return $this->canRecordVisits() && $this->hasTable('analytics_page_views');
    }

    private function canRecordEvents(): bool
    {
        return $this->hasTable('analytics_events');
    }

    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
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

    private function matchBotByUserAgent(?string $userAgent): ?string
    {
        if (! is_string($userAgent) || trim($userAgent) === '') {
            return null;
        }

        foreach (config('analytics.bot_user_agents', []) as $detector) {
            $pattern = (string) data_get($detector, 'pattern', '');

            if ($pattern !== '' && stripos($userAgent, $pattern) !== false) {
                return $this->truncate((string) data_get($detector, 'name'), 100);
            }
        }

        return null;
    }

    private function matchBotByIpAddress(?string $ipAddress): ?string
    {
        if (! is_string($ipAddress) || trim($ipAddress) === '') {
            return null;
        }

        foreach (config('analytics.bot_ip_ranges', []) as $range) {
            $cidr = (string) data_get($range, 'cidr', '');

            if ($cidr !== '' && $this->ipMatchesCidr($ipAddress, $cidr)) {
                return $this->truncate((string) data_get($range, 'name'), 100);
            }
        }

        return null;
    }

    private function ipMatchesCidr(string $ipAddress, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);

        if (count($parts) !== 2) {
            return false;
        }

        [$network, $prefixLength] = $parts;
        $networkBinary = @inet_pton($network);
        $ipBinary = @inet_pton($ipAddress);

        if ($networkBinary === false || $ipBinary === false || strlen($networkBinary) !== strlen($ipBinary)) {
            return false;
        }

        $prefix = (int) $prefixLength;
        $totalBits = strlen($networkBinary) * 8;

        if ($prefix < 0 || $prefix > $totalBits) {
            return false;
        }

        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($networkBinary, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($ipBinary[$fullBytes]) & $mask) === (ord($networkBinary[$fullBytes]) & $mask);
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

    private function truncate(?string $value, int $length): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, $length);
    }
}
