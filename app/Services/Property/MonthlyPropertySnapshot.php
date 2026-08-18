<?php

namespace App\Services\Property;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MonthlyPropertySnapshot
{
    private const CATEGORY = 'A';

    /**
     * @return array<string, mixed>
     */
    public function cachedData(): array
    {
        $latestMonth = $this->latestMonth();

        return $this->cachedDataFor($latestMonth);
    }

    /**
     * @return array<string, mixed>
     */
    public function cachedDataFor(Carbon $month): array
    {
        $month = $month->copy()->startOfMonth();

        return Cache::remember(
            'property:monthly-snapshot:v3:'.$month->format('Ym'),
            now()->addDay(),
            fn (): array => $this->build($month),
        );
    }

    public function isAvailable(Carbon $month): bool
    {
        $month = $month->copy()->startOfMonth();

        return $month->lessThanOrEqualTo($this->latestMonth())
            && $this->monthQuery($month)->exists();
    }

    /**
     * @return array<int, Carbon>
     */
    public function availableMonthsForYear(int $year): array
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = $start->copy()->endOfYear();
        $monthExpression = DB::connection()->getDriverName() === 'pgsql'
            ? 'EXTRACT(MONTH FROM "Date")'
            : 'CAST(strftime(\'%m\', "Date") AS INTEGER)';

        return DB::table('land_registry')
            ->where('PPDCategoryType', self::CATEGORY)
            ->whereBetween('Date', [$start, $end])
            ->selectRaw($monthExpression.' as month_number')
            ->groupByRaw($monthExpression)
            ->orderByRaw($monthExpression)
            ->pluck('month_number')
            ->map(fn (mixed $month): Carbon => Carbon::create($year, (int) $month, 1))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Carbon $month): array
    {
        $month = $month->copy()->startOfMonth();
        $query = $this->monthQuery($month);
        $headlineMetrics = $this->headlineMetrics($month) ?? [
            'sales' => 0,
            'median_price' => null,
            'p90_price' => null,
            'million_plus_share' => 0.0,
        ];
        $sales = $headlineMetrics['sales'];
        $medianPrice = $headlineMetrics['median_price'];
        $percentile90 = $headlineMetrics['p90_price'];
        $topRanked = (clone $query)
            ->where('Price', '>', 0)
            ->selectRaw('"Price", ROW_NUMBER() OVER (ORDER BY "Price" DESC) as rn, COUNT(*) OVER () as cnt');
        $top5Average = DB::query()->fromSub($topRanked, 'prices')
            ->whereColumn('rn', '<=', DB::raw('CEIL(0.05 * cnt)'))
            ->selectRaw('ROUND(AVG("Price")) as value')
            ->value('value');

        $propertyTypes = collect([
            'Detached' => 'D',
            'Semi-detached' => 'S',
            'Terraced' => 'T',
            'Flat / maisonette' => 'F',
            'Other' => 'O',
        ])->map(function (string $code, string $label) use ($query, $sales): array {
            $typeQuery = (clone $query)->where('PropertyType', $code);
            $count = (clone $typeQuery)->count();

            return [
                'label' => $label,
                'code' => $code,
                'sales' => $count,
                'share' => $sales > 0 ? round(($count / $sales) * 100, 1) : 0,
                'median_price' => $this->median($typeQuery),
            ];
        })->values()->all();
        $districts = $this->districts(clone $query);

        return [
            'month' => $month,
            'sales' => $sales,
            'medianPrice' => $medianPrice,
            'percentile90' => $percentile90 === null ? null : (int) $percentile90,
            'top5Average' => $top5Average === null ? null : (int) $top5Average,
            'comparison' => $this->comparison($month, $headlineMetrics),
            'propertyTypes' => $propertyTypes,
            'newBuildMix' => $this->mix(clone $query, 'NewBuild', ['New build' => 'Y', 'Existing' => 'N']),
            'tenureMix' => $this->mix(clone $query, 'Duration', ['Freehold' => 'F', 'Leasehold' => 'L']),
            'priceBands' => $this->priceBands(clone $query, $sales),
            'topDistricts' => $districts->sortByDesc('sales')->take(10)->values()->all(),
            'highestPriceDistricts' => $districts->where('sales', '>=', 50)->sortByDesc('median_price')->take(5)->values()->all(),
            'lowestPriceDistricts' => $districts->where('sales', '>=', 50)->sortBy('median_price')->take(5)->values()->all(),
            'districtMapPoints' => $this->districtMapPoints(clone $query, $districts),
            'notableSales' => $this->notableSales(clone $query),
            'isProvisional' => true,
        ];
    }

    public function latestMonth(): Carbon
    {
        $latestDate = DB::table('land_registry')
            ->where('PPDCategoryType', self::CATEGORY)
            ->max('Date');

        return $latestDate === null ? now()->startOfMonth() : Carbon::parse($latestDate)->startOfMonth();
    }

    private function monthQuery(Carbon $month): Builder
    {
        return DB::table('land_registry')
            ->where('PPDCategoryType', self::CATEGORY)
            ->whereBetween('Date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]);
    }

    /**
     * @return array{previous: Carbon, year_ago: Carbon}
     */
    public function comparisonMonths(Carbon $month): array
    {
        $month = $month->copy()->startOfMonth();

        return [
            'previous' => $month->copy()->subMonth(),
            'year_ago' => $month->copy()->subYear(),
        ];
    }

    public function percentageChange(int|float|null $current, int|float|null $comparison): ?float
    {
        if ($current === null || $comparison === null || (float) $comparison === 0.0) {
            return null;
        }

        return round((($current - $comparison) / $comparison) * 100, 1);
    }

    public function percentagePointChange(int|float|null $current, int|float|null $comparison): ?float
    {
        if ($current === null || $comparison === null) {
            return null;
        }

        return round($current - $comparison, 1);
    }

    /**
     * @param  array{sales: int, median_price: int|null, p90_price: int|null, million_plus_share: float}  $current
     * @return array<string, mixed>
     */
    private function comparison(Carbon $month, array $current): array
    {
        $months = $this->comparisonMonths($month);
        $previous = $this->headlineMetrics($months['previous']);
        $yearAgo = $this->headlineMetrics($months['year_ago']);

        return [
            'previous_label' => $months['previous']->format('F'),
            'year_label' => $months['year_ago']->format('F Y'),
            'sales' => $this->comparisonMetric($current['sales'], $previous['sales'] ?? null, $yearAgo['sales'] ?? null),
            'median_price' => $this->comparisonMetric($current['median_price'], $previous['median_price'] ?? null, $yearAgo['median_price'] ?? null),
            'p90_price' => $this->comparisonMetric($current['p90_price'], $previous['p90_price'] ?? null, $yearAgo['p90_price'] ?? null),
            'million_plus_share' => $this->comparisonMetric(
                $current['million_plus_share'],
                $previous['million_plus_share'] ?? null,
                $yearAgo['million_plus_share'] ?? null,
                true,
            ),
        ];
    }

    /**
     * @return array{current: int|float|null, previous: int|float|null, previous_change: float|null, year_ago: int|float|null, year_change: float|null}
     */
    private function comparisonMetric(
        int|float|null $current,
        int|float|null $previous,
        int|float|null $yearAgo,
        bool $percentagePoints = false,
    ): array {
        $change = $percentagePoints ? $this->percentagePointChange(...) : $this->percentageChange(...);

        return [
            'current' => $current,
            'previous' => $previous,
            'previous_change' => $change($current, $previous),
            'year_ago' => $yearAgo,
            'year_change' => $change($current, $yearAgo),
        ];
    }

    /**
     * @return array{sales: int, median_price: int|null, p90_price: int|null, million_plus_share: float}|null
     */
    private function headlineMetrics(Carbon $month): ?array
    {
        $query = $this->monthQuery($month);
        $sales = (clone $query)->count();

        if ($sales === 0) {
            return null;
        }

        $ranked = (clone $query)
            ->where('Price', '>', 0)
            ->selectRaw('"Price", CUME_DIST() OVER (ORDER BY "Price") as cd');
        $percentile90 = DB::query()->fromSub($ranked, 'prices')->where('cd', '>=', 0.9)->min('Price');
        $millionPlusSales = (clone $query)->where('Price', '>=', 1000000)->count();

        return [
            'sales' => $sales,
            'median_price' => $this->median(clone $query),
            'p90_price' => $percentile90 === null ? null : (int) $percentile90,
            'million_plus_share' => round(($millionPlusSales / $sales) * 100, 1),
        ];
    }

    private function median(Builder $query): ?int
    {
        $value = $query->where('Price', '>', 0)
            ->selectRaw('ROUND('.$this->medianExpression().') as value')
            ->value('value');

        return $value === null ? null : (int) $value;
    }

    /**
     * @return Collection<int, array{district: string, sales: int, median_price: int|null}>
     */
    private function districts(Builder $query): Collection
    {
        return $query
            ->whereNotNull('District')
            ->where('District', '!=', '')
            ->where('Price', '>', 0)
            ->selectRaw('"District" as district, COUNT(*) as sales, ROUND('.$this->medianExpression().') as median_price')
            ->groupBy('District')
            ->get()
            ->map(fn (object $row): array => [
                'district' => (string) $row->district,
                'sales' => (int) $row->sales,
                'median_price' => $row->median_price === null ? null : (int) $row->median_price,
            ]);
    }

    /**
     * @return array<int, array{label: string, sales: int, share: float}>
     */
    private function priceBands(Builder $query, int $sales): array
    {
        $bands = [
            ['label' => 'Under £150k', 'min' => null, 'max' => 150000],
            ['label' => '£150k–£249k', 'min' => 150000, 'max' => 250000],
            ['label' => '£250k–£399k', 'min' => 250000, 'max' => 400000],
            ['label' => '£400k–£749k', 'min' => 400000, 'max' => 750000],
            ['label' => '£750k–£999k', 'min' => 750000, 'max' => 1000000],
            ['label' => '£1m+', 'min' => 1000000, 'max' => null],
        ];

        return collect($bands)->map(function (array $band) use ($query, $sales): array {
            $bandQuery = clone $query;

            if ($band['min'] !== null) {
                $bandQuery->where('Price', '>=', $band['min']);
            }

            if ($band['max'] !== null) {
                $bandQuery->where('Price', '<', $band['max']);
            }

            $count = $bandQuery->count();

            return [
                'label' => $band['label'],
                'sales' => $count,
                'share' => $sales > 0 ? round(($count / $sales) * 100, 1) : 0,
            ];
        })->all();
    }

    /**
     * @param  Collection<int, array{district: string, sales: int, median_price: int|null}>  $districts
     * @return array<int, array{district: string, sales: int, median_price: int|null, lat: float, lng: float}>
     */
    private function districtMapPoints(Builder $query, Collection $districts): array
    {
        if (! Schema::hasTable('onspd_v2')) {
            return [];
        }

        $coordinates = $query
            ->join('onspd_v2 as o', DB::raw("REPLACE(o.pcds, ' ', '')"), '=', DB::raw("REPLACE(land_registry.\"Postcode\", ' ', '')"))
            ->whereNotNull('District')
            ->whereNotNull('o.lat')
            ->whereNotNull('o.long')
            ->selectRaw('"District" as district, AVG(o.lat) as lat, AVG(o.long) as lng, COUNT(*) as mapped_sales')
            ->groupBy('District')
            ->get()
            ->keyBy('district');

        return $districts->map(function (array $district) use ($coordinates): ?array {
            $coordinate = $coordinates->get($district['district']);

            if ($coordinate === null) {
                return null;
            }

            return [...$district, 'lat' => (float) $coordinate->lat, 'lng' => (float) $coordinate->lng];
        })->filter()->values()->all();
    }

    /**
     * @return array<int, array{price: int, property_type: string, district: string, county: string, postcode: string, date: string}>
     */
    private function notableSales(Builder $query): array
    {
        return $query
            ->where('Price', '>', 0)
            ->orderByDesc('Price')
            ->limit(3)
            ->get(['Price', 'PropertyType', 'District', 'County', 'Postcode', 'Date'])
            ->map(fn (object $row): array => [
                'price' => (int) $row->Price,
                'property_type' => (string) $row->PropertyType,
                'district' => (string) $row->District,
                'county' => (string) $row->County,
                'postcode' => (string) $row->Postcode,
                'date' => Carbon::parse($row->Date)->format('j F Y'),
            ])->all();
    }

    /**
     * @param  array<string, string>  $values
     * @return array<int, array{label: string, total: int, share: float}>
     */
    private function mix(Builder $query, string $column, array $values): array
    {
        $counts = $query
            ->whereIn($column, array_values($values))
            ->selectRaw("\"{$column}\" as code, COUNT(*) as total")
            ->groupBy($column)
            ->pluck('total', 'code');
        $total = (int) $counts->sum();

        return collect($values)->map(fn (string $code, string $label): array => [
            'label' => $label,
            'total' => (int) $counts->get($code, 0),
            'share' => $total > 0 ? round(((int) $counts->get($code, 0) / $total) * 100, 1) : 0,
        ])->values()->all();
    }

    private function medianExpression(): string
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? 'PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY "Price")'
            : 'AVG("Price")';
    }
}
