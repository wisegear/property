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

        $anonVisitId = app('request')->cookie('pr_avid');
        if (! is_string($anonVisitId) || trim($anonVisitId) === '') {
            return null;
        }

        return mb_substr(trim($anonVisitId), 0, 36);
    }
}
