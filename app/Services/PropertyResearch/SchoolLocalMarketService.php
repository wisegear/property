<?php

namespace App\Services\PropertyResearch;

use App\Http\Controllers\PropertyStreetController;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SchoolLocalMarketService
{
    private const CACHE_VERSION = 'v1';

    private const FRESH_SECONDS = 60 * 60 * 24 * 30;

    private const STALE_SECONDS = 60 * 60 * 24 * 45;

    public static function cacheKey(string $postcode): ?string
    {
        $outcode = self::outcode($postcode);

        return $outcode !== null
            ? 'school:local-market:'.self::CACHE_VERSION.':'.strtolower($outcode)
            : null;
    }

    /**
     * @return array{outcode:string, nearby_streets:array<int, array<string, mixed>>, recent_sales:array<int, array<string, mixed>>, updated_label:?string}
     */
    public function forPostcode(string $postcode): array
    {
        $outcode = self::outcode($postcode);

        if ($outcode === null) {
            return $this->emptySnapshot('');
        }

        $cacheKey = self::cacheKey($postcode);

        if ($cacheKey === null) {
            return $this->emptySnapshot('');
        }

        return Cache::flexible(
            $cacheKey,
            [self::FRESH_SECONDS, self::STALE_SECONDS],
            fn (): array => $this->buildSnapshot($outcode),
        );
    }

    /**
     * @return array{outcode:string, nearby_streets:array<int, array<string, mixed>>, recent_sales:array<int, array<string, mixed>>, updated_label:?string}
     */
    public function warm(string $postcode, bool $refresh = false): array
    {
        $outcode = self::outcode($postcode);
        $cacheKey = self::cacheKey($postcode);

        if ($outcode === null || $cacheKey === null) {
            return $this->emptySnapshot('');
        }

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember(
            $cacheKey,
            self::STALE_SECONDS,
            fn (): array => $this->buildSnapshot($outcode),
        );
    }

    public static function outcode(string $postcode): ?string
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', trim($postcode)) ?? '');

        if (preg_match('/^([A-Z]{1,2}\d[A-Z\d]?)\d[A-Z]{2}$/', $normalized, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return array{outcode:string, nearby_streets:array<int, array<string, mixed>>, recent_sales:array<int, array<string, mixed>>, updated_label:?string}
     */
    private function buildSnapshot(string $outcode): array
    {
        $baseQuery = fn () => DB::table('land_registry')
            ->where('PPDCategoryType', 'A')
            ->where('Postcode', '>=', $outcode)
            ->where('Postcode', '<', $outcode.'~');

        $nearbyStreets = $baseQuery()
            ->selectRaw('TRIM("Street") as street')
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('ROUND(AVG("Price")) as average_price')
            ->selectRaw('MAX("Date") as latest_sale_date')
            ->whereNotNull('Street')
            ->whereRaw('TRIM("Street") <> ?', [''])
            ->whereNotNull('Price')
            ->where('Date', '>=', now()->subYears(5)->startOfDay())
            ->groupByRaw('TRIM("Street")')
            ->havingRaw('COUNT(*) >= ?', [3])
            ->orderByDesc('latest_sale_date')
            ->orderByDesc('sales_count')
            ->limit(6)
            ->get()
            ->map(function (object $row) use ($outcode): array {
                $street = trim((string) $row->street);
                $averagePrice = (int) $row->average_price;

                return [
                    'name' => Str::title(Str::lower($street)),
                    'sales_count' => (int) $row->sales_count,
                    'average_price' => $averagePrice,
                    'average_price_label' => '£'.number_format($averagePrice),
                    'url' => PropertyStreetController::streetPath($outcode, Str::slug($street)),
                ];
            })
            ->all();

        $recentSales = $baseQuery()
            ->select(['Price', 'Date', 'PAON', 'SAON', 'Street', 'Postcode', 'PropertyType'])
            ->whereNotNull('Price')
            ->orderByDesc('Date')
            ->orderByDesc('Price')
            ->limit(6)
            ->get()
            ->map(function (object $row): array {
                $price = (int) $row->Price;
                $date = $row->Date !== null ? Carbon::parse((string) $row->Date) : null;
                $address = collect([$row->SAON, $row->PAON, $row->Street])
                    ->map(fn ($part): string => trim((string) $part))
                    ->filter()
                    ->join(', ');

                return [
                    'address' => Str::title(Str::lower($address)),
                    'postcode' => strtoupper(trim((string) $row->Postcode)),
                    'price' => $price,
                    'price_label' => '£'.number_format($price),
                    'date_label' => $date?->format('j M Y'),
                    'property_type' => $this->propertyTypeLabel($row->PropertyType),
                ];
            })
            ->all();

        $latestDate = collect($recentSales)->pluck('date_label')->filter()->first();

        return [
            'outcode' => $outcode,
            'nearby_streets' => $nearbyStreets,
            'recent_sales' => $recentSales,
            'updated_label' => $latestDate,
        ];
    }

    private function propertyTypeLabel(mixed $propertyType): string
    {
        return match ((string) $propertyType) {
            'D' => 'Detached',
            'S' => 'Semi-detached',
            'T' => 'Terraced',
            'F' => 'Flat or maisonette',
            default => 'Other property',
        };
    }

    /**
     * @return array{outcode:string, nearby_streets:array<int, array<string, mixed>>, recent_sales:array<int, array<string, mixed>>, updated_label:?string}
     */
    private function emptySnapshot(string $outcode): array
    {
        return [
            'outcode' => $outcode,
            'nearby_streets' => [],
            'recent_sales' => [],
            'updated_label' => null,
        ];
    }
}
