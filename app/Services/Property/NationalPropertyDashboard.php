<?php

namespace App\Services\Property;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NationalPropertyDashboard
{
    public const CACHE_TTL = 86400;

    private const CATEGORY = 'A';

    /**
     * @return array<string, mixed>
     */
    public function webData(): array
    {
        $data = $this->cachedData();

        return [
            'salesByYear' => collect($data['rolling_market'])->map(fn (array $row): object => (object) [
                'year' => (int) substr($row['period'], 0, 4),
                'total' => $row['sales'],
            ]),
            'avgPriceByYear' => collect($data['rolling_market'])->map(fn (array $row): object => (object) [
                'year' => (int) substr($row['period'], 0, 4),
                'avg_price' => $row['median_price'],
            ]),
            'ewP90' => collect($data['rolling_market'])->map(fn (array $row): object => (object) [
                'year' => (int) substr($row['period'], 0, 4),
                'p90_price' => $row['percentile_90'],
            ]),
            'ewTop5' => collect($data['rolling_market'])->map(fn (array $row): object => (object) [
                'year' => (int) substr($row['period'], 0, 4),
                'top5_avg' => $row['top_5_average'],
            ]),
            'ewTopSalePerYear' => collect($data['rolling_market'])->map(fn (array $row): object => (object) [
                'year' => (int) substr($row['period'], 0, 4),
                'top_sale' => $row['largest_sale'],
            ]),
            'ewTop3PerYear' => collect($data['largest_sales'])->map(fn (array $row): object => (object) [
                'year' => (int) substr($row['period'], 0, 4),
                'Date' => $row['date'],
                'Postcode' => $row['postcode'],
                'Price' => $row['price'],
                'rn' => $row['rank'],
            ]),
            'sales24Labels' => collect($data['monthly_sales'])
                ->map(fn (array $row): string => Carbon::createFromFormat('Y-m', $row['period'])->format('M Y'))
                ->all(),
            'sales24Data' => collect($data['monthly_sales'])->pluck('value')->all(),
            'latestMonth' => Carbon::createFromFormat('Y-m', $data['metadata']['latest_month'])->startOfMonth(),
            'rollingStart' => Carbon::createFromFormat('Y-m', $data['metadata']['latest_month'])->subMonths(11)->startOfMonth(),
            'rollingEnd' => Carbon::createFromFormat('Y-m', $data['metadata']['latest_month'])->endOfMonth(),
            'rollingMeta' => [
                'latest_month' => $data['metadata']['latest_month'].'-01',
                'rolling_start' => Carbon::createFromFormat('Y-m', $data['metadata']['latest_month'])->subMonths(11)->format('Y-m-d'),
                'rolling_end' => Carbon::createFromFormat('Y-m', $data['metadata']['latest_month'])->endOfMonth()->format('Y-m-d'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cachedData(): array
    {
        $latestDate = DB::table('land_registry')
            ->where('PPDCategoryType', self::CATEGORY)
            ->max('Date');
        $latestMonth = $latestDate === null
            ? now()->startOfMonth()
            : Carbon::parse($latestDate)->startOfMonth();

        return Cache::remember(
            'property:dashboard:api:v1:'.$latestMonth->format('Ym'),
            self::CACHE_TTL,
            fn (): array => $this->build($latestMonth)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Carbon $latestMonth): array
    {
        $periods = $this->rollingEndMonths($latestMonth);
        $rollingMarket = $periods->map(fn (Carbon $period): array => $this->marketFor($period))->values();
        $largestSales = $periods->flatMap(fn (Carbon $period): Collection => $this->largestSalesFor($period))->values();
        $propertyTypes = $periods->map(fn (Carbon $period): array => $this->propertyTypesFor($period))->values();
        $stockMix = $periods->map(fn (Carbon $period): array => $this->mixFor($period, 'NewBuild', [
            'new_build' => 'Y',
            'existing' => 'N',
        ]))->values();
        $tenureMix = $periods->map(fn (Carbon $period): array => $this->mixFor($period, 'Duration', [
            'freehold' => 'F',
            'leasehold' => 'L',
        ]))->values();
        $yearOnYear = $rollingMarket->values()->map(function (array $row, int $index) use ($rollingMarket): array {
            $previous = $index > 0 ? $rollingMarket[$index - 1] : null;

            return [
                'period' => $row['period'],
                'sales' => $this->percentageChange($row['sales'], $previous['sales'] ?? null),
                'median_price' => $this->percentageChange($row['median_price'], $previous['median_price'] ?? null),
                'percentile_90' => $this->percentageChange($row['percentile_90'], $previous['percentile_90'] ?? null),
                'top_5_average' => $this->percentageChange($row['top_5_average'], $previous['top_5_average'] ?? null),
            ];
        });
        $latest = $rollingMarket->last() ?? [];
        $latestChange = $yearOnYear->last() ?? [];
        $earliestDate = DB::table('land_registry')
            ->where('PPDCategoryType', self::CATEGORY)
            ->min('Date');

        return [
            'metadata' => [
                'region' => 'England and Wales',
                'latest_month' => $latestMonth->format('Y-m'),
                'range_start' => $earliestDate === null ? null : Carbon::parse($earliestDate)->format('Y-m'),
                'rolling_window_months' => 12,
                'category' => self::CATEGORY,
                'source' => 'HM Land Registry',
                'is_provisional' => true,
                'generated_at' => now()->utc()->toIso8601String(),
            ],
            'summary' => [
                'sales' => $latest['sales'] ?? null,
                'median_price' => $latest['median_price'] ?? null,
                'median_price_change' => $latestChange['median_price'] ?? null,
                'sales_volume_change' => $latestChange['sales'] ?? null,
            ],
            'monthly_sales' => $this->monthlySales($latestMonth),
            'rolling_market' => $rollingMarket->all(),
            'largest_sales' => $largestSales->all(),
            'property_types' => $propertyTypes->all(),
            'stock_mix' => $stockMix->all(),
            'tenure_mix' => $tenureMix->all(),
            'year_on_year' => $yearOnYear->all(),
        ];
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function rollingEndMonths(Carbon $latestMonth): Collection
    {
        $earliestDate = DB::table('land_registry')
            ->where('PPDCategoryType', self::CATEGORY)
            ->min('Date');

        if ($earliestDate === null) {
            return collect([$latestMonth->copy()]);
        }

        $earliestPossibleEnd = Carbon::parse($earliestDate)->startOfMonth()->addMonths(11);
        $firstEnd = $latestMonth->copy()->year($earliestPossibleEnd->year)->startOfMonth();

        if ($firstEnd->lt($earliestPossibleEnd)) {
            $firstEnd->addYear();
        }

        $periods = collect();

        for ($cursor = $firstEnd->copy(); $cursor->lte($latestMonth); $cursor->addYear()) {
            $periods->push($cursor->copy());
        }

        return $periods->isEmpty() ? collect([$latestMonth->copy()]) : $periods;
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function range(Carbon $period): array
    {
        return [
            'start' => $period->copy()->subMonths(11)->startOfMonth(),
            'end' => $period->copy()->endOfMonth(),
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function marketFor(Carbon $period): array
    {
        $range = $this->range($period);
        $query = DB::table('land_registry')
            ->where('PPDCategoryType', self::CATEGORY)
            ->whereBetween('Date', [$range['start'], $range['end']]);
        $median = (clone $query)->where('Price', '>', 0)
            ->selectRaw('ROUND('.$this->medianExpression().') as value')->value('value');
        $ranked = (clone $query)->selectRaw('"Price", CUME_DIST() OVER (ORDER BY "Price") as cd')
            ->where('Price', '>', 0);
        $p90 = DB::query()->fromSub($ranked, 'prices')->where('cd', '>=', 0.9)->min('Price');
        $topRanked = (clone $query)
            ->selectRaw('"Price", ROW_NUMBER() OVER (ORDER BY "Price" DESC) as rn, COUNT(*) OVER () as cnt')
            ->where('Price', '>', 0);
        $top5 = DB::query()->fromSub($topRanked, 'prices')
            ->selectRaw('ROUND(AVG("Price")) as value')
            ->whereColumn('rn', '<=', DB::raw('CEIL(0.05 * cnt)'))
            ->value('value');

        return [
            'period' => $period->format('Y-m'),
            'sales' => (clone $query)->count(),
            'median_price' => $median === null ? null : (int) $median,
            'percentile_90' => $p90 === null ? null : (int) $p90,
            'top_5_average' => $top5 === null ? null : (int) $top5,
            'largest_sale' => ($largest = (clone $query)->where('Price', '>', 0)->max('Price')) === null ? null : (int) $largest,
        ];
    }

    /**
     * @return Collection<int, array<string, int|string|null>>
     */
    private function largestSalesFor(Carbon $period): Collection
    {
        $range = $this->range($period);

        return DB::table('land_registry')
            ->select(['Price', 'Postcode', 'Date'])
            ->where('PPDCategoryType', self::CATEGORY)
            ->whereBetween('Date', [$range['start'], $range['end']])
            ->where('Price', '>', 0)
            ->orderByDesc('Price')
            ->orderBy('Date')
            ->limit(3)
            ->get()
            ->values()
            ->map(fn (object $row, int $index): array => [
                'period' => $period->format('Y-m'),
                'rank' => $index + 1,
                'price' => (int) $row->Price,
                'postcode' => $row->Postcode,
                'date' => Carbon::parse($row->Date)->toDateString(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function propertyTypesFor(Carbon $period): array
    {
        $range = $this->range($period);
        $rows = DB::table('land_registry')
            ->selectRaw('"PropertyType" as type, COUNT(*) as sales, ROUND('.$this->medianExpression().') as median_price')
            ->where('PPDCategoryType', self::CATEGORY)
            ->whereBetween('Date', [$range['start'], $range['end']])
            ->whereIn('PropertyType', ['D', 'S', 'T', 'F', 'O'])
            ->groupBy('PropertyType')
            ->get()
            ->keyBy('type');
        $result = ['period' => $period->format('Y-m')];

        foreach (['detached' => 'D', 'semi_detached' => 'S', 'terraced' => 'T', 'flat' => 'F', 'other' => 'O'] as $name => $code) {
            $row = $rows->get($code);
            $result[$name] = [
                'sales' => $row === null ? null : (int) $row->sales,
                'median_price' => $row?->median_price === null ? null : (int) $row->median_price,
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, int|string|null>
     */
    private function mixFor(Carbon $period, string $column, array $values): array
    {
        $range = $this->range($period);
        $rows = DB::table('land_registry')
            ->selectRaw("\"{$column}\" as code, COUNT(*) as total")
            ->where('PPDCategoryType', self::CATEGORY)
            ->whereBetween('Date', [$range['start'], $range['end']])
            ->whereIn($column, array_values($values))
            ->groupBy($column)
            ->pluck('total', 'code');
        $result = ['period' => $period->format('Y-m')];

        foreach ($values as $name => $code) {
            $result[$name] = $rows->has($code) ? (int) $rows->get($code) : null;
        }

        return $result;
    }

    /**
     * @return array<int, array{period: string, value: int, is_provisional: bool}>
     */
    private function monthlySales(Carbon $latestMonth): array
    {
        $start = $latestMonth->copy()->subMonths(23)->startOfMonth();
        $rows = DB::table('land_registry')
            ->selectRaw($this->monthExpression().' as period, COUNT(*) as sales')
            ->where('PPDCategoryType', self::CATEGORY)
            ->whereBetween('Date', [$start, $latestMonth->copy()->endOfMonth()])
            ->groupBy('period')
            ->pluck('sales', 'period');
        $result = [];

        for ($cursor = $start->copy(); $cursor->lte($latestMonth); $cursor->addMonth()) {
            $period = $cursor->format('Y-m');
            $result[] = [
                'period' => $period,
                'value' => (int) $rows->get($period, 0),
                'is_provisional' => $cursor->gt($latestMonth->copy()->subMonths(3)),
            ];
        }

        return $result;
    }

    private function percentageChange(?int $current, ?int $previous): ?float
    {
        if ($current === null || $previous === null || $previous === 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    private function medianExpression(): string
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? 'PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY "Price")'
            : 'AVG("Price")';
    }

    private function monthExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', \"Date\")"
            : "TO_CHAR(DATE_TRUNC('month', \"Date\"), 'YYYY-MM')";
    }
}
