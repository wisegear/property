<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class EpcPostcodeController extends Controller
{
    private const CACHE_TTL_DAYS = 45;

    private const CACHE_VERSION = 5;

    public static function cacheVersion(): int
    {
        return self::CACHE_VERSION;
    }

    public static function cacheKey(string $regime, string $postcode): string
    {
        return 'epc:v'.self::CACHE_VERSION.":{$regime}:postcode:{$postcode}";
    }

    public function englandWales(string $postcode)
    {
        return $this->handlePostcode('england_wales', $postcode);
    }

    public function scotland(string $postcode)
    {
        return $this->handlePostcode('scotland', $postcode);
    }

    protected function handlePostcode(string $regime, string $postcode)
    {
        $canonicalPostcode = $this->canonicalisePostcode($postcode);

        $indexPath = public_path('data/epc-postcodes.json');
        if (! File::exists($indexPath)) {
            abort(404);
        }

        $payload = json_decode((string) File::get($indexPath), true);
        if (! is_array($payload)) {
            abort(404);
        }

        $regimePostcodes = data_get($payload, 'postcodes.'.$regime, []);
        if (! is_array($regimePostcodes) || ! in_array($canonicalPostcode, $regimePostcodes, true)) {
            abort(404);
        }

        $data = $this->warmPostcodeCache($regime, $canonicalPostcode);

        return view('epc.postcode', $data);
    }

    public function warmPostcodeCache(string $regime, string $postcode): array
    {
        $canonicalPostcode = $this->canonicalisePostcode($postcode);
        $cacheKey = self::cacheKey($regime, $canonicalPostcode);

        $data = Cache::remember(
            $cacheKey,
            now()->addDays(self::CACHE_TTL_DAYS),
            fn (): array => $this->buildPostcodePayload($regime, $canonicalPostcode)
        );

        if (! $this->payloadHasPotentialDistribution($data, $regime)
            || ! $this->payloadHasEnvironmentalDistributions($data, $regime)
            || ! $this->payloadHasCertificatePotentialRatings($data)) {
            Cache::forget($cacheKey);
            $data = Cache::remember(
                $cacheKey,
                now()->addDays(self::CACHE_TTL_DAYS),
                fn (): array => $this->buildPostcodePayload($regime, $canonicalPostcode)
            );
        }

        return $data;
    }

    protected function canonicalisePostcode(string $postcode): string
    {
        return strtoupper(str_replace('-', ' ', trim($postcode)));
    }

    protected function payloadHasPotentialDistribution(array $payload, string $regime): bool
    {
        $statsKey = $regime === 'england_wales' ? 'england_wales' : 'scotland';
        $distribution = data_get($payload, $statsKey.'.potential_rating_distribution');

        return is_array($distribution);
    }

    protected function payloadHasEnvironmentalDistributions(array $payload, string $regime): bool
    {
        $statsKey = $regime === 'england_wales' ? 'england_wales' : 'scotland';
        $currentDistribution = data_get($payload, $statsKey.'.environment_rating_distribution');
        $potentialDistribution = data_get($payload, $statsKey.'.potential_environment_rating_distribution');

        return is_array($currentDistribution) && is_array($potentialDistribution);
    }

    protected function payloadHasCertificatePotentialRatings(array $payload): bool
    {
        $certificates = data_get($payload, 'certificates', []);
        if (! is_array($certificates)) {
            return false;
        }

        if ($certificates === []) {
            return true;
        }

        foreach ($certificates as $certificate) {
            if (! is_array($certificate) || ! array_key_exists('potential_rating', $certificate)) {
                return false;
            }
        }

        return true;
    }

    protected function buildPostcodePayload(string $regime, string $postcode): array
    {
        $payload = [
            'postcode' => $postcode,
            'regime' => $regime,
            'certificates' => $this->buildCertificatesForRegime($regime, $postcode),
        ];

        if ($regime === 'england_wales') {
            $payload['england_wales'] = $this->buildEnglandWalesAggregation($postcode);
        }

        if ($regime === 'scotland') {
            $payload['scotland'] = $this->buildScotlandAggregation($postcode);
        }

        return $payload;
    }

    /**
     * @return array{
     *   total_certificates:int,
     *   rating_distribution:array<string,int>,
     *   potential_rating_distribution:array<string,int>,
     *   environment_rating_distribution:array<string,int>,
     *   potential_environment_rating_distribution:array<string,int>,
     *   inspection_dates:array{earliest:string|null,latest:string|null}
     * }
     */
    protected function buildEnglandWalesAggregation(string $postcode): array
    {
        $defaultRatings = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0, 'G' => 0];
        $empty = [
            'total_certificates' => 0,
            'rating_distribution' => $defaultRatings,
            'potential_rating_distribution' => $defaultRatings,
            'environment_rating_distribution' => $defaultRatings,
            'potential_environment_rating_distribution' => $defaultRatings,
            'inspection_dates' => [
                'earliest' => null,
                'latest' => null,
            ],
        ];

        $table = 'epc_certificates';
        if (! Schema::hasTable($table)) {
            return $empty;
        }

        $postcodeColumn = $this->resolveColumn($table, ['POSTCODE', 'postcode']);
        $inspectionDateColumn = $this->resolveColumn($table, ['INSPECTION_DATE', 'inspection_date']);
        $ratingColumn = $this->resolveColumn($table, ['CURRENT_ENERGY_RATING', 'current_energy_rating']);
        $potentialRatingColumn = $this->resolveColumn($table, ['POTENTIAL_ENERGY_RATING', 'potential_energy_rating']);
        $environmentImpactCurrentColumn = $this->resolveColumn($table, ['ENVIRONMENT_IMPACT_CURRENT', 'environment_impact_current', 'ENVIRONMENTAL_IMPACT_CURRENT', 'environmental_impact_current']);
        $environmentImpactPotentialColumn = $this->resolveColumn($table, ['ENVIRONMENT_IMPACT_POTENTIAL', 'environment_impact_potential', 'ENVIRONMENTAL_IMPACT_POTENTIAL', 'environmental_impact_potential']);

        if ($postcodeColumn === null || $inspectionDateColumn === null) {
            return $empty;
        }

        $baseQuery = DB::table($table)
            ->where($postcodeColumn, $postcode)
            ->whereNotNull($inspectionDateColumn)
            ->where($inspectionDateColumn, '>=', '2015-01-01');

        $total = (clone $baseQuery)->count();
        if ($total === 0) {
            return $empty;
        }

        $ratings = $defaultRatings;
        if ($ratingColumn !== null) {
            $ratingRows = (clone $baseQuery)
                ->select($ratingColumn.' as rating')
                ->selectRaw('COUNT(*) as count')
                ->whereIn($ratingColumn, array_keys($defaultRatings))
                ->groupBy($ratingColumn)
                ->get();

            foreach ($ratingRows as $row) {
                $rating = strtoupper((string) $row->rating);
                if (array_key_exists($rating, $ratings)) {
                    $ratings[$rating] = (int) $row->count;
                }
            }
        }

        $potentialRatings = $defaultRatings;
        if ($potentialRatingColumn !== null) {
            $potentialRatingRows = (clone $baseQuery)
                ->select($potentialRatingColumn.' as rating')
                ->selectRaw('COUNT(*) as count')
                ->whereIn($potentialRatingColumn, array_keys($defaultRatings))
                ->groupBy($potentialRatingColumn)
                ->get();

            foreach ($potentialRatingRows as $row) {
                $rating = strtoupper((string) $row->rating);
                if (array_key_exists($rating, $potentialRatings)) {
                    $potentialRatings[$rating] = (int) $row->count;
                }
            }
        }

        $dateBounds = (clone $baseQuery)
            ->selectRaw('MIN('.$this->wrappedColumn($inspectionDateColumn).') as earliest')
            ->selectRaw('MAX('.$this->wrappedColumn($inspectionDateColumn).') as latest')
            ->first();

        $environmentRatings = $environmentImpactCurrentColumn
            ? $this->buildNumericBandDistribution($baseQuery, $environmentImpactCurrentColumn, $defaultRatings)
            : $defaultRatings;
        $potentialEnvironmentRatings = $environmentImpactPotentialColumn
            ? $this->buildNumericBandDistribution($baseQuery, $environmentImpactPotentialColumn, $defaultRatings)
            : $defaultRatings;

        return [
            'total_certificates' => (int) $total,
            'rating_distribution' => $ratings,
            'potential_rating_distribution' => $potentialRatings,
            'environment_rating_distribution' => $environmentRatings,
            'potential_environment_rating_distribution' => $potentialEnvironmentRatings,
            'inspection_dates' => [
                'earliest' => $dateBounds?->earliest ? (string) $dateBounds->earliest : null,
                'latest' => $dateBounds?->latest ? (string) $dateBounds->latest : null,
            ],
        ];
    }

    /**
     * @return array{
     *   total_certificates:int,
     *   rating_distribution:array<string,int>,
     *   potential_rating_distribution:array<string,int>,
     *   environment_rating_distribution:array<string,int>,
     *   potential_environment_rating_distribution:array<string,int>,
     *   inspection_dates:array{earliest:string|null,latest:string|null}
     * }
     */
    protected function buildScotlandAggregation(string $postcode): array
    {
        $defaultRatings = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0, 'G' => 0];
        $empty = [
            'total_certificates' => 0,
            'rating_distribution' => $defaultRatings,
            'potential_rating_distribution' => $defaultRatings,
            'environment_rating_distribution' => $defaultRatings,
            'potential_environment_rating_distribution' => $defaultRatings,
            'inspection_dates' => [
                'earliest' => null,
                'latest' => null,
            ],
        ];

        $table = 'epc_certificates_scotland';
        if (! Schema::hasTable($table)) {
            return $empty;
        }

        $postcodeColumn = $this->resolveColumn($table, ['POSTCODE', 'postcode']);
        $inspectionDateColumn = $this->resolveColumn($table, ['INSPECTION_DATE', 'inspection_date']);
        $ratingColumn = $this->resolveColumn($table, ['CURRENT_ENERGY_RATING', 'current_energy_rating']);
        $potentialRatingColumn = $this->resolveColumn($table, ['POTENTIAL_ENERGY_RATING', 'potential_energy_rating']);
        $environmentCurrentLetterColumn = $this->resolveColumn($table, ['CURRENT_ENVIRONMENTAL_RATING', 'current_environmental_rating']);
        $environmentPotentialLetterColumn = $this->resolveColumn($table, ['POTENTIAL_ENVIRONMENTAL_RATING', 'potential_environmental_rating']);
        $environmentImpactCurrentColumn = $this->resolveColumn($table, ['ENVIRONMENT_IMPACT_CURRENT', 'environment_impact_current', 'ENVIRONMENTAL_IMPACT_CURRENT', 'environmental_impact_current']);
        $environmentImpactPotentialColumn = $this->resolveColumn($table, ['ENVIRONMENT_IMPACT_POTENTIAL', 'environment_impact_potential', 'ENVIRONMENTAL_IMPACT_POTENTIAL', 'environmental_impact_potential']);

        if ($postcodeColumn === null || $inspectionDateColumn === null) {
            return $empty;
        }

        $baseQuery = DB::table($table)
            ->where($postcodeColumn, $postcode)
            ->whereNotNull($inspectionDateColumn)
            ->where($inspectionDateColumn, '>=', '2015-01-01');

        $total = (clone $baseQuery)->count();
        if ($total === 0) {
            return $empty;
        }

        $ratings = $defaultRatings;
        if ($ratingColumn !== null) {
            $ratingRows = (clone $baseQuery)
                ->select($ratingColumn.' as rating')
                ->selectRaw('COUNT(*) as count')
                ->whereIn($ratingColumn, array_keys($defaultRatings))
                ->groupBy($ratingColumn)
                ->get();

            foreach ($ratingRows as $row) {
                $rating = strtoupper((string) $row->rating);
                if (array_key_exists($rating, $ratings)) {
                    $ratings[$rating] = (int) $row->count;
                }
            }
        }

        $potentialRatings = $defaultRatings;
        if ($potentialRatingColumn !== null) {
            $potentialRatingRows = (clone $baseQuery)
                ->select($potentialRatingColumn.' as rating')
                ->selectRaw('COUNT(*) as count')
                ->whereIn($potentialRatingColumn, array_keys($defaultRatings))
                ->groupBy($potentialRatingColumn)
                ->get();

            foreach ($potentialRatingRows as $row) {
                $rating = strtoupper((string) $row->rating);
                if (array_key_exists($rating, $potentialRatings)) {
                    $potentialRatings[$rating] = (int) $row->count;
                }
            }
        }

        $dateBounds = (clone $baseQuery)
            ->selectRaw('MIN('.$this->wrappedColumn($inspectionDateColumn).') as earliest')
            ->selectRaw('MAX('.$this->wrappedColumn($inspectionDateColumn).') as latest')
            ->first();

        $environmentRatings = $defaultRatings;
        if ($environmentCurrentLetterColumn !== null) {
            $environmentRatings = $this->buildLetterBandDistribution($baseQuery, $environmentCurrentLetterColumn, $defaultRatings);
        } elseif ($environmentImpactCurrentColumn !== null) {
            $environmentRatings = $this->buildNumericBandDistribution($baseQuery, $environmentImpactCurrentColumn, $defaultRatings);
        }

        $potentialEnvironmentRatings = $defaultRatings;
        if ($environmentPotentialLetterColumn !== null) {
            $potentialEnvironmentRatings = $this->buildLetterBandDistribution($baseQuery, $environmentPotentialLetterColumn, $defaultRatings);
        } elseif ($environmentImpactPotentialColumn !== null) {
            $potentialEnvironmentRatings = $this->buildNumericBandDistribution($baseQuery, $environmentImpactPotentialColumn, $defaultRatings);
        }

        return [
            'total_certificates' => (int) $total,
            'rating_distribution' => $ratings,
            'potential_rating_distribution' => $potentialRatings,
            'environment_rating_distribution' => $environmentRatings,
            'potential_environment_rating_distribution' => $potentialEnvironmentRatings,
            'inspection_dates' => [
                'earliest' => $dateBounds?->earliest ? (string) $dateBounds->earliest : null,
                'latest' => $dateBounds?->latest ? (string) $dateBounds->latest : null,
            ],
        ];
    }

    /**
     * @return array<int, array{
     *   identifier:string|null,
     *   address:string|null,
     *   rating:string|null,
     *   inspection_date:string|null,
     *   url:string|null
     * }>
     */
    protected function buildCertificatesForRegime(string $regime, string $postcode): array
    {
        if ($regime === 'england_wales') {
            return $this->buildEnglandWalesCertificates($postcode);
        }
        if ($regime === 'scotland') {
            return $this->buildScotlandCertificates($postcode);
        }

        return [];
    }

    /**
     * @return array<int, array{
     *   identifier:string|null,
     *   address:string|null,
     *   rating:string|null,
     *   potential_rating:string|null,
     *   inspection_date:string|null,
     *   url:string|null
     * }>
     */
    protected function buildEnglandWalesCertificates(string $postcode): array
    {
        $table = 'epc_certificates';
        if (! Schema::hasTable($table)) {
            return [];
        }

        $postcodeColumn = $this->resolveColumn($table, ['POSTCODE', 'postcode']);
        $inspectionDateColumn = $this->resolveColumn($table, ['INSPECTION_DATE', 'inspection_date']);
        $identifierColumn = $this->resolveColumn($table, ['LMK_KEY', 'lmk_key']);
        $buildingReferenceColumn = $this->resolveColumn($table, ['BUILDING_REFERENCE_NUMBER', 'building_reference_number']);
        $ratingColumn = $this->resolveColumn($table, ['CURRENT_ENERGY_RATING', 'current_energy_rating']);
        $potentialRatingColumn = $this->resolveColumn($table, ['POTENTIAL_ENERGY_RATING', 'potential_energy_rating']);
        $addressColumn = $this->resolveColumn($table, ['ADDRESS', 'address']);
        $address1Column = $this->resolveColumn($table, ['ADDRESS1', 'address1']);
        $address2Column = $this->resolveColumn($table, ['ADDRESS2', 'address2']);
        $address3Column = $this->resolveColumn($table, ['ADDRESS3', 'address3']);

        if ($postcodeColumn === null || $inspectionDateColumn === null) {
            return [];
        }

        $rows = DB::table($table)
            ->where($postcodeColumn, $postcode)
            ->whereNotNull($inspectionDateColumn)
            ->where($inspectionDateColumn, '>=', '2015-01-01')
            ->orderBy($inspectionDateColumn, 'desc')
            ->get();

        return $rows->map(function ($row) use (
            $identifierColumn,
            $buildingReferenceColumn,
            $ratingColumn,
            $potentialRatingColumn,
            $inspectionDateColumn,
            $addressColumn,
            $address1Column,
            $address2Column,
            $address3Column
        ): array {
            $identifier = $identifierColumn ? $this->stringValue($row->{$identifierColumn} ?? null) : null;
            if ($identifier === null && $buildingReferenceColumn) {
                $identifier = $this->stringValue($row->{$buildingReferenceColumn} ?? null);
            }
            $address = $this->firstNonEmptyValue([
                $addressColumn ? $this->stringValue($row->{$addressColumn} ?? null) : null,
                $this->joinedAddressFromParts([
                    $address1Column ? $row->{$address1Column} ?? null : null,
                    $address2Column ? $row->{$address2Column} ?? null : null,
                    $address3Column ? $row->{$address3Column} ?? null : null,
                ]),
            ]);
            $rating = $ratingColumn ? $this->stringValue($row->{$ratingColumn} ?? null) : null;
            $potentialRating = $potentialRatingColumn ? $this->stringValue($row->{$potentialRatingColumn} ?? null) : null;
            $inspectionDate = $inspectionDateColumn ? $this->stringValue($row->{$inspectionDateColumn} ?? null) : null;

            return [
                'identifier' => $identifier,
                'address' => $address,
                'rating' => $rating,
                'potential_rating' => $potentialRating,
                'inspection_date' => $inspectionDate,
                'url' => $identifier ? route('epc.show', ['lmk' => $identifier], false) : null,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array{
     *   identifier:string|null,
     *   address:string|null,
     *   rating:string|null,
     *   potential_rating:string|null,
     *   inspection_date:string|null,
     *   url:string|null
     * }>
     */
    protected function buildScotlandCertificates(string $postcode): array
    {
        $table = 'epc_certificates_scotland';
        if (! Schema::hasTable($table)) {
            return [];
        }

        $postcodeColumn = $this->resolveColumn($table, ['POSTCODE', 'postcode']);
        $inspectionDateColumn = $this->resolveColumn($table, ['INSPECTION_DATE', 'inspection_date']);
        $identifierColumn = $this->resolveColumn($table, ['REPORT_REFERENCE_NUMBER', 'RRN', 'rrn']);
        $ratingColumn = $this->resolveColumn($table, ['CURRENT_ENERGY_RATING', 'current_energy_rating']);
        $potentialRatingColumn = $this->resolveColumn($table, ['POTENTIAL_ENERGY_RATING', 'potential_energy_rating']);
        $addressColumn = $this->resolveColumn($table, ['ADDRESS', 'address']);
        $address1Column = $this->resolveColumn($table, ['ADDRESS1', 'address1']);
        $address2Column = $this->resolveColumn($table, ['ADDRESS2', 'address2']);
        $address3Column = $this->resolveColumn($table, ['ADDRESS3', 'address3']);

        if ($postcodeColumn === null || $inspectionDateColumn === null) {
            return [];
        }

        $rows = DB::table($table)
            ->where($postcodeColumn, $postcode)
            ->whereNotNull($inspectionDateColumn)
            ->where($inspectionDateColumn, '>=', '2015-01-01')
            ->orderBy($inspectionDateColumn, 'desc')
            ->get();

        return $rows->map(function ($row) use (
            $identifierColumn,
            $ratingColumn,
            $potentialRatingColumn,
            $inspectionDateColumn,
            $addressColumn,
            $address1Column,
            $address2Column,
            $address3Column
        ): array {
            $identifier = $identifierColumn ? $this->stringValue($row->{$identifierColumn} ?? null) : null;
            $address = $this->firstNonEmptyValue([
                $addressColumn ? $this->stringValue($row->{$addressColumn} ?? null) : null,
                $this->joinedAddressFromParts([
                    $address1Column ? $row->{$address1Column} ?? null : null,
                    $address2Column ? $row->{$address2Column} ?? null : null,
                    $address3Column ? $row->{$address3Column} ?? null : null,
                ]),
            ]);
            $rating = $ratingColumn ? $this->stringValue($row->{$ratingColumn} ?? null) : null;
            $potentialRating = $potentialRatingColumn ? $this->stringValue($row->{$potentialRatingColumn} ?? null) : null;
            $inspectionDate = $inspectionDateColumn ? $this->stringValue($row->{$inspectionDateColumn} ?? null) : null;

            return [
                'identifier' => $identifier,
                'address' => $address,
                'rating' => $rating,
                'potential_rating' => $potentialRating,
                'inspection_date' => $inspectionDate,
                'url' => $identifier ? route('epc.scotland.show', ['rrn' => $identifier], false) : null,
            ];
        })->values()->all();
    }

    protected function resolveColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (Schema::hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function wrappedColumn(string $column): string
    {
        return DB::connection()->getQueryGrammar()->wrap($column);
    }

    protected function buildLetterBandDistribution(object $baseQuery, string $column, array $defaultRatings): array
    {
        $distribution = $defaultRatings;
        $rows = (clone $baseQuery)
            ->select($column.' as rating')
            ->selectRaw('COUNT(*) as count')
            ->whereIn($column, array_keys($defaultRatings))
            ->groupBy($column)
            ->get();

        foreach ($rows as $row) {
            $rating = strtoupper((string) $row->rating);
            if (array_key_exists($rating, $distribution)) {
                $distribution[$rating] = (int) $row->count;
            }
        }

        return $distribution;
    }

    protected function buildNumericBandDistribution(object $baseQuery, string $column, array $defaultRatings): array
    {
        $distribution = $defaultRatings;
        $rows = (clone $baseQuery)
            ->select($column.' as score')
            ->selectRaw('COUNT(*) as count')
            ->whereNotNull($column)
            ->groupBy($column)
            ->get();

        foreach ($rows as $row) {
            $score = is_numeric($row->score) ? (int) $row->score : null;
            $rating = $this->bandForScore($score);
            if ($rating !== null && array_key_exists($rating, $distribution)) {
                $distribution[$rating] += (int) $row->count;
            }
        }

        return $distribution;
    }

    protected function bandForScore(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }
        if ($score >= 92) {
            return 'A';
        }
        if ($score >= 81) {
            return 'B';
        }
        if ($score >= 69) {
            return 'C';
        }
        if ($score >= 55) {
            return 'D';
        }
        if ($score >= 39) {
            return 'E';
        }
        if ($score >= 21) {
            return 'F';
        }
        if ($score >= 1) {
            return 'G';
        }

        return null;
    }

    protected function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    protected function joinedAddressFromParts(array $parts): ?string
    {
        $segments = collect($parts)
            ->map(fn ($part) => $this->stringValue($part))
            ->filter()
            ->values();

        if ($segments->isEmpty()) {
            return null;
        }

        return $segments->implode(', ');
    }

    protected function firstNonEmptyValue(array $values): ?string
    {
        foreach ($values as $value) {
            $text = $this->stringValue($value);
            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }
}
