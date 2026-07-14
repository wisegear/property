<?php

namespace App\Support\PropertyResearch;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SchoolSlug
{
    public static function for(string $name, string|int|null $urn = null, bool $forceUrn = false): string
    {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'school';
        }

        if ($forceUrn && $urn !== null && trim((string) $urn) !== '') {
            return $baseSlug.'-'.trim((string) $urn);
        }

        if ($urn !== null && self::nameIsDuplicated($name)) {
            return $baseSlug.'-'.trim((string) $urn);
        }

        return $baseSlug;
    }

    public static function base(string $name): string
    {
        return Str::slug($name) ?: 'school';
    }

    private static function nameIsDuplicated(string $name): bool
    {
        return DB::table('property_school_establishments')
            ->where('establishment_name', $name)
            ->limit(2)
            ->count() > 1;
    }
}
