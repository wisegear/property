<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CouncilTaxEstimateService
{
    /** @var array<string, array{valuation_year: int, national_area_code: string, band_d_charge: float, bands: array<string, float|null>, ratios: array<string, float>}> */
    private const COUNTRY_RULES = [
        'E92000001' => [
            'valuation_year' => 1991,
            'national_area_code' => 'E92000001',
            'band_d_charge' => 2392.0,
            'bands' => ['A' => 40000, 'B' => 52000, 'C' => 68000, 'D' => 88000, 'E' => 120000, 'F' => 160000, 'G' => 320000, 'H' => null],
            'ratios' => ['A' => 6 / 9, 'B' => 7 / 9, 'C' => 8 / 9, 'D' => 1, 'E' => 11 / 9, 'F' => 13 / 9, 'G' => 15 / 9, 'H' => 2],
        ],
        'W92000004' => [
            'valuation_year' => 2003,
            'national_area_code' => 'W92000004',
            'band_d_charge' => 2283.0,
            'bands' => ['A' => 44000, 'B' => 65000, 'C' => 91000, 'D' => 123000, 'E' => 162000, 'F' => 223000, 'G' => 324000, 'H' => 424000, 'I' => null],
            'ratios' => ['A' => 6 / 9, 'B' => 7 / 9, 'C' => 8 / 9, 'D' => 1, 'E' => 11 / 9, 'F' => 13 / 9, 'G' => 15 / 9, 'H' => 2, 'I' => 21 / 9],
        ],
    ];

    public function forProperty(Collection $sales, string $postcode): ?array
    {
        if (! Schema::hasTable('onspd_v2') || ! Schema::hasTable('hpi_monthly')) {
            return null;
        }

        $geography = DB::table('onspd_v2')
            ->select(['ctry25cd', 'rgn25cd', 'lad25cd'])
            ->where('pcds', $this->normalisePostcode($postcode))
            ->first();
        $countryCode = (string) ($geography?->ctry25cd ?? '');
        $rules = self::COUNTRY_RULES[$countryCode] ?? null;

        if ($rules === null) {
            return null;
        }

        $valuationsByCategory = $sales
            ->filter(fn (object $sale): bool => in_array(($sale->PPDCategoryType ?? null), ['A', 'B'], true) && (float) ($sale->Price ?? 0) > 0)
            ->map(function (object $sale) use ($countryCode, $geography, $rules): ?array {
                try {
                    $saleDate = Carbon::parse((string) $sale->Date);
                } catch (\Throwable) {
                    return null;
                }

                $saleIndex = $this->saleIndex(
                    countryCode: $countryCode,
                    regionCode: (string) ($geography?->rgn25cd ?? ''),
                    saleDate: $saleDate,
                    nationalAreaCode: $rules['national_area_code'],
                );
                $baseIndex = $this->indexForMonth($rules['national_area_code'], $rules['valuation_year'], 4);

                if ($saleIndex === null || $baseIndex === null || $saleIndex <= 0) {
                    return null;
                }

                return [
                    'category' => (string) $sale->PPDCategoryType,
                    'valuation' => (float) $sale->Price * ($baseIndex / $saleIndex),
                ];
            })
            ->filter(fn (?array $valuation): bool => $valuation !== null && $valuation['valuation'] > 0)
            ->values();
        $categoryAValuations = $valuationsByCategory
            ->where('category', 'A')
            ->pluck('valuation');
        $valuations = ($categoryAValuations->isNotEmpty()
            ? $categoryAValuations
            : $valuationsByCategory->where('category', 'B')->pluck('valuation'))
            ->values()
            ->all();

        $localRate = $countryCode === 'E92000001'
            ? $this->englishAuthorityRate((string) ($geography?->lad25cd ?? ''))
            : null;

        return $this->fromValuations(
            valuations: $valuations,
            countryCode: $countryCode,
            bandDCharge: $localRate['band_d'] ?? null,
            authority: $localRate['authority'] ?? null,
        );
    }

    /**
     * @param  array<int, float|int>  $valuations
     * @return array<string, int|string|float>|null
     */
    public function fromValuations(array $valuations, string $countryCode, ?float $bandDCharge = null, ?string $authority = null): ?array
    {
        $rules = self::COUNTRY_RULES[$countryCode] ?? null;
        $valuations = array_values(array_filter(array_map('floatval', $valuations), fn (float $value): bool => $value > 0));

        if ($rules === null || $valuations === []) {
            return null;
        }

        sort($valuations);
        $middle = intdiv(count($valuations), 2);
        $centralValue = count($valuations) % 2 === 0
            ? ($valuations[$middle - 1] + $valuations[$middle]) / 2
            : $valuations[$middle];
        $uncertainty = count($valuations) >= 2 ? 0.15 : 0.20;
        $lowBand = $this->bandForValue($centralValue * (1 - $uncertainty), $rules['bands']);
        $highBand = $this->bandForValue($centralValue * (1 + $uncertainty), $rules['bands']);
        $effectiveBandDCharge = $bandDCharge ?? $rules['band_d_charge'];
        $lowCharge = $effectiveBandDCharge * $rules['ratios'][$lowBand];
        $highCharge = $effectiveBandDCharge * $rules['ratios'][$highBand];

        return [
            'low_band' => $lowBand,
            'high_band' => $highBand,
            'band_label' => $lowBand === $highBand ? "Band {$lowBand}" : "Bands {$lowBand}–{$highBand}",
            'low_annual' => $this->roundCharge(min($lowCharge, $highCharge)),
            'high_annual' => $this->roundCharge(max($lowCharge, $highCharge)),
            'valuation_year' => $rules['valuation_year'],
            'financial_year' => '2026/27',
            'sales_used' => count($valuations),
            'estimated_valuation' => round($centralValue),
            'authority' => $authority,
            'rate_basis' => $authority !== null ? "{$authority} average" : 'national average',
        ];
    }

    private function saleIndex(string $countryCode, string $regionCode, Carbon $saleDate, string $nationalAreaCode): ?float
    {
        if ($countryCode !== 'E92000001' || $regionCode === '' || $saleDate->year < 1995) {
            return $this->indexForMonth($nationalAreaCode, $saleDate->year, $saleDate->month);
        }

        $regionalSaleIndex = $this->indexForMonth($regionCode, $saleDate->year, $saleDate->month);
        $regionalBridgeIndex = $this->indexForMonth($regionCode, 1995, 1);
        $nationalBridgeIndex = $this->indexForMonth($nationalAreaCode, 1995, 1);

        if ($regionalSaleIndex === null || $regionalBridgeIndex === null || $nationalBridgeIndex === null || $regionalBridgeIndex <= 0) {
            return $this->indexForMonth($nationalAreaCode, $saleDate->year, $saleDate->month);
        }

        return $regionalSaleIndex * ($nationalBridgeIndex / $regionalBridgeIndex);
    }

    private function indexForMonth(string $areaCode, int $year, int $month): ?float
    {
        $rows = DB::table('hpi_monthly')
            ->select(['Date', 'Index'])
            ->where('AreaCode', $areaCode)
            ->whereYear('Date', $year)
            ->whereNotNull('Index')
            ->get();
        $match = $rows->first(function (object $row) use ($month): bool {
            $date = Carbon::parse((string) $row->Date);

            return $date->month === $month || ($date->month === 1 && $date->day === $month);
        });

        if ($match !== null) {
            return (float) $match->Index;
        }

        $latestAvailable = DB::table('hpi_monthly')
            ->select(['Date', 'Index'])
            ->where('AreaCode', $areaCode)
            ->whereYear('Date', '<=', $year)
            ->whereNotNull('Index')
            ->get()
            ->filter(function (object $row) use ($year, $month): bool {
                $date = Carbon::parse((string) $row->Date);
                $indexMonth = $date->month === 1 && $date->day <= 12 ? $date->day : $date->month;

                return $date->year < $year || $indexMonth <= $month;
            })
            ->sortByDesc(function (object $row): int {
                $date = Carbon::parse((string) $row->Date);
                $indexMonth = $date->month === 1 && $date->day <= 12 ? $date->day : $date->month;

                return ($date->year * 100) + $indexMonth;
            })
            ->first();

        return $latestAvailable !== null ? (float) $latestAvailable->Index : null;
    }

    /** @param array<string, float|null> $bands */
    private function bandForValue(float $value, array $bands): string
    {
        foreach ($bands as $band => $upperLimit) {
            if ($upperLimit === null || $value <= $upperLimit) {
                return $band;
            }
        }

        return array_key_last($bands);
    }

    private function roundCharge(float $charge): int
    {
        return (int) round($charge);
    }

    /** @return array{authority: string, band_d: float}|null */
    private function englishAuthorityRate(string $localAuthorityCode): ?array
    {
        static $rates = null;

        if ($localAuthorityCode === '') {
            return null;
        }

        if ($rates === null) {
            $path = resource_path('data/council-tax-england-2026-27.json');
            $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            $rates = is_array($decoded) ? $decoded : [];
        }

        $rate = $rates[$localAuthorityCode] ?? null;

        if (! is_array($rate) || ! is_numeric($rate['band_d'] ?? null) || empty($rate['authority'])) {
            return null;
        }

        return [
            'authority' => (string) $rate['authority'],
            'band_d' => (float) $rate['band_d'],
        ];
    }

    private function normalisePostcode(string $postcode): string
    {
        $postcode = strtoupper(preg_replace('/\s+/', '', trim($postcode)));

        return strlen($postcode) >= 5 ? substr($postcode, 0, -3).' '.substr($postcode, -3) : $postcode;
    }
}
