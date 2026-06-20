<?php

namespace App\Services;

use App\Models\FormEvent;
use Throwable;

class FormAnalytics
{
    public static function record(string $formKey, array $payload = []): void
    {
        try {
            $safePayload = self::sanitizePayload($payload);
            $anonVisitId = self::resolveAnonVisitId();

            FormEvent::query()->create([
                'form_key' => substr(trim($formKey), 0, 50),
                'anon_visit_id' => $anonVisitId,
                'payload' => $safePayload === [] ? null : $safePayload,
                'created_at' => now(),
            ]);

            self::recordAnalyticsEvent($formKey, $safePayload);
        } catch (Throwable) {
        }
    }

    private static function sanitizePayload(array $payload): array
    {
        $safePayload = [];
        foreach ($payload as $key => $value) {
            if (! is_string($key) || $key === '' || self::looksIdentifyingKey($key)) {
                continue;
            }

            if (is_array($value)) {
                $nested = self::sanitizePayload($value);
                if ($nested !== []) {
                    $safePayload[$key] = $nested;
                }

                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value)) {
                $safePayload[$key] = $value;

                continue;
            }

            if (is_string($value)) {
                $safePayload[$key] = mb_substr(trim($value), 0, 120);
            }
        }

        return $safePayload;
    }

    private static function looksIdentifyingKey(string $key): bool
    {
        return (bool) preg_match('/(email|ip|session|cookie|header|token|user_?id|identifier)/i', $key);
    }

    private static function resolveAnonVisitId(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = app('request');
        $cookieName = (string) config('analytics.cookie_name', 'pr_avid');
        $anonVisitId = $request->attributes->get('analytics_anon_visit_id', $request->cookie($cookieName));
        if (! is_string($anonVisitId) || trim($anonVisitId) === '') {
            return null;
        }

        return mb_substr(trim($anonVisitId), 0, 36);
    }

    private static function recordAnalyticsEvent(string $formKey, array $payload): void
    {
        $mapping = [
            'property_area_search' => ['search', 'property_area_search'],
            'property_search' => ['search', 'property_search'],
            'street_search' => ['search', 'street_search'],
            'epc_england_wales' => ['lookup', 'epc_postcode'],
            'epc_scotland' => ['lookup', 'epc_scotland_postcode'],
            '/epc/postcode/' => ['lookup', 'epc_postcode'],
            '/epc/scotland/postcode/' => ['lookup', 'epc_scotland_postcode'],
            'deprivation_lookup' => ['lookup', 'deprivation_lookup'],
            'mortgage_calculator' => ['calculator', 'mortgage_calculator'],
            'stamp_duty' => ['calculator', 'stamp_duty'],
        ];

        if (! array_key_exists($formKey, $mapping)) {
            return;
        }

        [$eventType, $eventKey] = $mapping[$formKey];

        app(AnalyticsService::class)->recordEvent($eventType, $eventKey, $payload);
    }
}
