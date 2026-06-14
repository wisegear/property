<?php

namespace App\Http\Controllers;

use App\Models\Crime;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PropertyStreetController extends Controller
{
    private const CACHE_TTL = 60 * 60 * 24 * 45;

    private const MIN_RELIABLE_SALES = 3;

    private const ONSPD_TABLE = 'onspd_v2';

    public function show(Request $request, string $street): View
    {
        $outcode = $this->normalizeOutcode((string) $request->query('outcode', ''));

        if ($outcode === null) {
            abort(404);
        }

        $cacheKey = sprintf('property:street:v1:%s:%s', $street, Str::lower($outcode));

        $payload = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($street, $outcode): array {
            return $this->buildPayload($street, $outcode);
        });

        return view('property.street', [
            'streetName' => $payload['street_name'],
            'streetSlug' => $street,
            'outcode' => $payload['outcode'],
            'summary' => $payload['summary'],
            'yearlyMedianPrice' => $payload['yearly_median_price'],
            'yearlySalesCount' => $payload['yearly_sales_count'],
            'topSales' => $payload['top_sales'],
            'sales' => $this->paginateSales($payload['sales'], $request),
            'limitedData' => $payload['limited_data'],
            'crimeData' => $payload['crime_data'],
            'crimeTrend' => $payload['crime_trend'],
            'crimeSummary' => $payload['crime_summary'],
            'crimeDirection' => $payload['crime_direction'],
            'crimeTrendLabels' => $payload['crime_trend_labels'],
            'crimeTrendValues' => $payload['crime_trend_values'],
            'totalChange' => $payload['crime_total_change'],
            'topIncrease' => $payload['crime_top_increase'],
            'topDecrease' => $payload['crime_top_decrease'],
        ]);
    }

    /**
     * @return array{
     *     street_name:string,
     *     outcode:string,
     *     summary:array<string, int|string|null>,
     *     yearly_median_price:array<int, array{year:int, value:int}>,
     *     yearly_sales_count:array<int, array{year:int, value:int}>,
     *     top_sales:array<int, array<string, mixed>>,
     *     sales:array<int, array<string, mixed>>,
     *     limited_data:bool,
     *     crime_data:array<int, array<string, mixed>>,
     *     crime_trend:array<int, array<string, mixed>>,
     *     crime_summary:?string,
     *     crime_direction:string,
     *     crime_trend_labels:array<int, string>,
     *     crime_trend_values:array<int, int>,
     *     crime_total_change:float,
     *     crime_top_increase:?array<string, mixed>,
     *     crime_top_decrease:?array<string, mixed>
     * }
     */
    private function buildPayload(string $streetSlug, string $outcode): array
    {
        $streetName = $this->resolveStreetName($streetSlug, $outcode);

        if ($streetName === null) {
            abort(404);
        }

        $records = DB::table('land_registry')
            ->select([
                'Date',
                'Price',
                'PAON',
                'SAON',
                'Street',
                'Postcode',
                'PropertyType',
                'Duration',
                'NewBuild',
            ])
            ->where('PPDCategoryType', 'A')
            ->where('Street', $streetName)
            ->whereRaw($this->outcodeExpression().' = ?', [$outcode])
            ->orderByDesc('Date')
            ->orderByDesc('Price')
            ->get()
            ->map(function (object $row): array {
                $date = $row->Date !== null ? Carbon::parse((string) $row->Date) : null;
                $price = $row->Price !== null ? (int) $row->Price : null;
                $paon = trim((string) ($row->PAON ?? ''));
                $saon = trim((string) ($row->SAON ?? ''));

                return [
                    'date' => $date?->toDateString(),
                    'date_label' => $date?->format('d M Y'),
                    'price' => $price,
                    'price_label' => $price !== null ? '£'.number_format($price) : null,
                    'paon' => $paon,
                    'saon' => $saon,
                    'street' => trim((string) ($row->Street ?? '')),
                    'postcode' => trim((string) ($row->Postcode ?? '')),
                    'property_type' => $this->propertyTypeLabel($row->PropertyType !== null ? (string) $row->PropertyType : null),
                    'tenure' => $this->tenureLabel($row->Duration !== null ? (string) $row->Duration : null),
                    'build_status' => $this->buildStatusLabel($row->NewBuild !== null ? (string) $row->NewBuild : null),
                    'address' => $this->formatAddress($paon, $saon),
                ];
            })
            ->values()
            ->all();

        if ($records === []) {
            abort(404);
        }

        $prices = collect($records)
            ->pluck('price')
            ->filter(fn ($price): bool => is_int($price) && $price > 0)
            ->sort()
            ->values();

        $latestSaleDate = collect($records)
            ->pluck('date')
            ->filter()
            ->first();

        $summary = [
            'total_sales' => count($records),
            'median_sale_price' => $this->medianValue($prices->all()),
            'average_sale_price' => $prices->isNotEmpty() ? (int) round($prices->avg()) : null,
            'latest_sale_date' => $latestSaleDate !== null ? Carbon::parse($latestSaleDate)->format('d M Y') : null,
            'highest_sale' => $prices->isNotEmpty() ? (int) $prices->last() : null,
        ];

        $yearly = collect($records)
            ->filter(fn (array $record): bool => $record['date'] !== null)
            ->groupBy(fn (array $record): int => Carbon::parse((string) $record['date'])->year)
            ->sortKeys();

        $yearlyMedianPrice = $yearly
            ->map(function ($items, int $year): array {
                $prices = collect($items)
                    ->pluck('price')
                    ->filter(fn ($price): bool => is_int($price) && $price > 0)
                    ->sort()
                    ->values()
                    ->all();

                return [
                    'year' => $year,
                    'value' => $this->medianValue($prices) ?? 0,
                ];
            })
            ->values()
            ->all();

        $yearlySalesCount = $yearly
            ->map(fn ($items, int $year): array => [
                'year' => $year,
                'value' => count($items),
            ])
            ->values()
            ->all();

        $topSales = collect($records)
            ->filter(fn (array $record): bool => $record['price'] !== null)
            ->sortByDesc('price')
            ->take(10)
            ->values()
            ->all();

        $crimePayload = $this->buildCrimePayload($records, $streetName, $outcode);

        return [
            'street_name' => $streetName,
            'outcode' => $outcode,
            'summary' => $summary,
            'yearly_median_price' => $yearlyMedianPrice,
            'yearly_sales_count' => $yearlySalesCount,
            'top_sales' => $topSales,
            'sales' => $records,
            'limited_data' => count($records) < self::MIN_RELIABLE_SALES,
            'crime_data' => $crimePayload['crime_data'],
            'crime_trend' => $crimePayload['crime_trend'],
            'crime_summary' => $crimePayload['crime_summary'],
            'crime_direction' => $crimePayload['crime_direction'],
            'crime_trend_labels' => $crimePayload['crime_trend_labels'],
            'crime_trend_values' => $crimePayload['crime_trend_values'],
            'crime_total_change' => $crimePayload['crime_total_change'],
            'crime_top_increase' => $crimePayload['crime_top_increase'],
            'crime_top_decrease' => $crimePayload['crime_top_decrease'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array{
     *     crime_data:array<int, array<string, mixed>>,
     *     crime_trend:array<int, array<string, mixed>>,
     *     crime_summary:?string,
     *     crime_direction:string,
     *     crime_trend_labels:array<int, string>,
     *     crime_trend_values:array<int, int>,
     *     crime_total_change:float,
     *     crime_top_increase:?array<string, mixed>,
     *     crime_top_decrease:?array<string, mixed>
     * }
     */
    private function buildCrimePayload(array $records, string $streetName, string $outcode): array
    {
        $emptyPayload = [
            'crime_data' => [],
            'crime_trend' => [],
            'crime_summary' => null,
            'crime_direction' => 'stable',
            'crime_trend_labels' => [],
            'crime_trend_values' => [],
            'crime_total_change' => 0.0,
            'crime_top_increase' => null,
            'crime_top_decrease' => null,
        ];

        if (! Schema::hasTable('crime') || ! Schema::hasTable(self::ONSPD_TABLE)) {
            return $emptyPayload;
        }

        $coords = $this->streetCentroid($records);

        if ($coords === null) {
            return $emptyPayload;
        }

        $latestCrimeMonth = Crime::query()->max('month');

        if ($latestCrimeMonth === null) {
            return $emptyPayload;
        }

        $currentWindowEnd = Carbon::parse((string) $latestCrimeMonth)->startOfMonth();
        $crimeWindowStart = $currentWindowEnd->copy()->subMonths(11);
        $previousWindowStart = $crimeWindowStart->copy()->subMonths(12);

        $crimeSummaryRows = Crime::query()
            ->selectRaw('crime_type, COUNT(*) as total')
            ->whereDate('month', '>=', $crimeWindowStart->toDateString())
            ->whereBetween('latitude', [$coords['lat'] - 0.005, $coords['lat'] + 0.005])
            ->whereBetween('longitude', [$coords['lng'] - 0.005, $coords['lng'] + 0.005])
            ->whereNotNull('crime_type')
            ->groupBy('crime_type')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $crimeTrendCounts = Crime::query()
            ->selectRaw(
                'crime_type,
                SUM(CASE WHEN month >= ? THEN 1 ELSE 0 END) as current_total,
                SUM(CASE WHEN month >= ? AND month < ? THEN 1 ELSE 0 END) as previous_total',
                [
                    $crimeWindowStart->toDateString(),
                    $previousWindowStart->toDateString(),
                    $crimeWindowStart->toDateString(),
                ]
            )
            ->whereDate('month', '>=', $previousWindowStart->toDateString())
            ->whereDate('month', '<=', $currentWindowEnd->toDateString())
            ->whereBetween('latitude', [$coords['lat'] - 0.005, $coords['lat'] + 0.005])
            ->whereBetween('longitude', [$coords['lng'] - 0.005, $coords['lng'] + 0.005])
            ->whereNotNull('crime_type')
            ->groupBy('crime_type')
            ->get()
            ->map(function ($crime) {
                $currentTotal = (int) $crime->current_total;
                $previousTotal = (int) $crime->previous_total;
                $pctChange = $previousTotal > 0
                    ? round((($currentTotal - $previousTotal) * 100) / $previousTotal, 1)
                    : ($currentTotal > 0 ? 100.0 : 0.0);

                return [
                    'crime_type' => (string) $crime->crime_type,
                    'current_total' => $currentTotal,
                    'previous_total' => $previousTotal,
                    'pct_change' => $pctChange,
                ];
            })
            ->values();

        $crimeTrendSeriesRows = Crime::query()
            ->selectRaw('month, COUNT(*) as total')
            ->whereDate('month', '>=', $previousWindowStart->toDateString())
            ->whereDate('month', '<=', $currentWindowEnd->toDateString())
            ->whereBetween('latitude', [$coords['lat'] - 0.005, $coords['lat'] + 0.005])
            ->whereBetween('longitude', [$coords['lng'] - 0.005, $coords['lng'] + 0.005])
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        $crimeTrendLabels = [];
        $crimeTrendValues = [];
        $trendCursor = $previousWindowStart->copy();

        while ($trendCursor->lte($currentWindowEnd)) {
            $monthKey = $trendCursor->toDateString();
            $crimeTrendLabels[] = $trendCursor->format('M y');
            $crimeTrendValues[] = (int) ($crimeTrendSeriesRows[$monthKey] ?? 0);
            $trendCursor->addMonth();
        }

        $crimeTrendByType = $crimeTrendCounts->keyBy('crime_type');
        $crimeTotal = (int) $crimeSummaryRows->sum('total');

        $nationalCrimeTrendByType = Crime::query()
            ->selectRaw(
                'crime_type,
                SUM(CASE WHEN month >= ? THEN 1 ELSE 0 END) as current_total,
                SUM(CASE WHEN month >= ? AND month < ? THEN 1 ELSE 0 END) as previous_total',
                [
                    $crimeWindowStart->toDateString(),
                    $previousWindowStart->toDateString(),
                    $crimeWindowStart->toDateString(),
                ]
            )
            ->whereDate('month', '>=', $previousWindowStart->toDateString())
            ->whereDate('month', '<=', $currentWindowEnd->toDateString())
            ->groupBy('crime_type')
            ->get()
            ->mapWithKeys(function ($crime) {
                $currentTotal = (int) $crime->current_total;
                $previousTotal = (int) $crime->previous_total;
                $pctChange = $previousTotal > 0
                    ? round((($currentTotal - $previousTotal) * 100) / $previousTotal, 1)
                    : ($currentTotal > 0 ? 100.0 : 0.0);

                return [(string) $crime->crime_type => $pctChange];
            });

        $crimeData = $crimeSummaryRows
            ->map(function ($crime) use ($crimeTotal, $crimeTrendByType, $nationalCrimeTrendByType): array {
                $crimeType = (string) $crime->crime_type;
                $trend = $crimeTrendByType->get($crimeType);

                return [
                    'crime_type' => $crimeType,
                    'total' => (int) $crime->total,
                    'pct' => $crimeTotal > 0 ? round(((int) $crime->total * 100) / $crimeTotal, 1) : 0.0,
                    'pct_change' => (float) ($trend['pct_change'] ?? 0.0),
                    'national_pct_change' => $nationalCrimeTrendByType->get($crimeType),
                ];
            })
            ->values();

        $totalCurrent = (int) $crimeTrendCounts->sum('current_total');
        $totalPrevious = (int) $crimeTrendCounts->sum('previous_total');
        $totalChange = $totalPrevious > 0
            ? round((($totalCurrent - $totalPrevious) * 100) / $totalPrevious, 1)
            : ($totalCurrent > 0 ? 100.0 : 0.0);

        $crimeTrend = $crimeTrendCounts
            ->map(function (array $crime) use ($totalCurrent, $totalPrevious, $totalChange): array {
                $crime['total_current'] = $totalCurrent;
                $crime['total_previous'] = $totalPrevious;
                $crime['total_pct_change'] = $totalChange;

                return $crime;
            })
            ->sortByDesc('pct_change')
            ->values();

        $topIncrease = $crimeTrend
            ->first(fn (array $crime): bool => (float) $crime['pct_change'] > 0);

        if ($topIncrease === null) {
            $topIncrease = $crimeTrend->sortByDesc('pct_change')->first();
        }

        $topDecrease = $crimeTrend
            ->sortBy('pct_change')
            ->first(fn (array $crime): bool => (float) $crime['pct_change'] < 0);

        if ($topDecrease === null) {
            $topDecrease = $crimeTrend->sortBy('pct_change')->first();
        }

        $crimeDirection = 'stable';

        if ($totalChange > 10) {
            $crimeDirection = 'rising';
        } elseif ($totalChange < -10) {
            $crimeDirection = 'falling';
        }

        if ($topIncrease !== null && (int) $topIncrease['previous_total'] < 20) {
            $topIncrease['pct_change_label'] = $topIncrease['pct_change'].'% (low volume)';
        }

        $directionWord = $totalChange > 0 ? 'up' : 'down';
        $crimeSummary = 'Crime is '.$directionWord.' '.abs($totalChange).'% over the past year';

        if ($topIncrease !== null && $topDecrease !== null) {
            $crimeSummary .= ', driven by increases in '.strtolower((string) $topIncrease['crime_type']);
            $crimeSummary .= ' and offset by decreases in '.strtolower((string) $topDecrease['crime_type']).'.';
        } else {
            $crimeSummary .= '.';
        }

        return [
            'crime_data' => $crimeData->all(),
            'crime_trend' => $crimeTrend->all(),
            'crime_summary' => $crimeSummary,
            'crime_direction' => $crimeDirection,
            'crime_trend_labels' => $crimeTrendLabels,
            'crime_trend_values' => $crimeTrendValues,
            'crime_total_change' => $totalChange,
            'crime_top_increase' => $topIncrease,
            'crime_top_decrease' => $topDecrease,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array{lat:float,lng:float}|null
     */
    private function streetCentroid(array $records): ?array
    {
        $postcodes = collect($records)
            ->pluck('postcode')
            ->filter(fn ($postcode): bool => is_string($postcode) && trim($postcode) !== '')
            ->map(fn (string $postcode): string => $this->canonicalPostcode($postcode))
            ->unique()
            ->values();

        if ($postcodes->isEmpty()) {
            return null;
        }

        $rows = DB::table(self::ONSPD_TABLE)
            ->select(['pcds', 'lat', 'long', 'dointr'])
            ->whereIn('pcds', $postcodes)
            ->orderBy('pcds')
            ->orderByDesc('dointr')
            ->get();

        $coordsByPostcode = [];

        foreach ($rows as $row) {
            $pcds = (string) $row->pcds;

            if (isset($coordsByPostcode[$pcds])) {
                continue;
            }

            if ($row->lat === null || $row->long === null) {
                continue;
            }

            $coordsByPostcode[$pcds] = [
                'lat' => (float) $row->lat,
                'lng' => (float) $row->long,
            ];
        }

        if ($coordsByPostcode === []) {
            return null;
        }

        $latAverage = collect($coordsByPostcode)->avg('lat');
        $lngAverage = collect($coordsByPostcode)->avg('lng');

        if ($latAverage === null || $lngAverage === null) {
            return null;
        }

        return [
            'lat' => (float) $latAverage,
            'lng' => (float) $lngAverage,
        ];
    }

    private function resolveStreetName(string $streetSlug, string $outcode): ?string
    {
        return DB::table('land_registry')
            ->select('Street')
            ->where('PPDCategoryType', 'A')
            ->whereNotNull('Street')
            ->whereRaw($this->outcodeExpression().' = ?', [$outcode])
            ->distinct()
            ->orderBy('Street')
            ->pluck('Street')
            ->map(fn ($street) => trim((string) $street))
            ->first(fn (string $street): bool => Str::slug($street) === $streetSlug);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sales
     */
    private function paginateSales(array $sales, Request $request): LengthAwarePaginator
    {
        $perPage = 25;
        $currentPage = max(1, (int) $request->query('page', 1));
        $offset = ($currentPage - 1) * $perPage;

        return new LengthAwarePaginator(
            array_slice($sales, $offset, $perPage),
            count($sales),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    /**
     * @param  array<int, int>  $values
     */
    private function medianValue(array $values): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values, SORT_NUMERIC);

        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return $values[$middle];
        }

        return (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }

    private function formatAddress(string $paon, string $saon): string
    {
        return collect([$paon, $saon])
            ->filter(fn (string $part): bool => $part !== '')
            ->implode(', ');
    }

    private function normalizeOutcode(string $outcode): ?string
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', trim($outcode)) ?? '');

        return $normalized !== '' ? $normalized : null;
    }

    private function canonicalPostcode(string $postcode): string
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', trim($postcode)) ?? '');

        if ($normalized === '' || strlen($normalized) < 5) {
            return strtoupper(trim($postcode));
        }

        return substr($normalized, 0, -3).' '.substr($normalized, -3);
    }

    private function outcodeExpression(): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return 'UPPER(SPLIT_PART("Postcode", \' \', 1))';
        }

        return 'UPPER(TRIM(SUBSTR("Postcode", 1, CASE WHEN INSTR("Postcode", \' \') = 0 THEN LENGTH("Postcode") ELSE INSTR("Postcode", \' \') - 1 END)))';
    }

    private function propertyTypeLabel(?string $code): string
    {
        return match ($code) {
            'D' => 'Detached',
            'S' => 'Semi-detached',
            'T' => 'Terraced',
            'F' => 'Flat',
            'O' => 'Other',
            default => 'Unknown',
        };
    }

    private function tenureLabel(?string $code): string
    {
        return match ($code) {
            'F' => 'Freehold',
            'L' => 'Leasehold',
            default => 'Unknown',
        };
    }

    private function buildStatusLabel(?string $code): string
    {
        return match ($code) {
            'Y' => 'New build',
            'N' => 'Existing',
            default => 'Unknown',
        };
    }
}
