<?php

namespace App\Http\Controllers\Concerns;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait BuildsRollingPrimeDashboardData
{
    protected function latestMonth(): Carbon
    {
        $latestDate = DB::table('land_registry')->max('Date');

        return $latestDate
            ? Carbon::parse($latestDate)->startOfMonth()
            : now()->startOfMonth();
    }

    protected function earliestMonth(): ?Carbon
    {
        $earliestDate = DB::table('land_registry')->min('Date');

        return $earliestDate
            ? Carbon::parse($earliestDate)->startOfMonth()
            : null;
    }

    protected function rollingEndMonths(Builder $baseQuery, Carbon $latestMonth): Collection
    {
        $earliestDate = (clone $baseQuery)->min('Date');

        if ($earliestDate === null) {
            return collect([$latestMonth->copy()]);
        }

        $earliestPossibleEnd = Carbon::parse($earliestDate)->startOfMonth()->addMonths(11);
        $firstEnd = $latestMonth->copy()->year($earliestPossibleEnd->year)->startOfMonth();

        if ($firstEnd->lt($earliestPossibleEnd)) {
            $firstEnd->addYear();
        }

        $endMonths = collect();
        $cursor = $firstEnd->copy();

        while ($cursor->lte($latestMonth)) {
            $endMonths->push($cursor->copy());
            $cursor->addYear();
        }

        return $endMonths->isNotEmpty() ? $endMonths : collect([$latestMonth->copy()]);
    }

    protected function rollingRangeForEndMonth(Carbon $endMonth): array
    {
        return [
            'year' => $endMonth->year,
            'start' => $endMonth->copy()->subMonths(11)->startOfMonth(),
            'end' => $endMonth->copy()->endOfMonth(),
        ];
    }

    protected function buildRollingMedianSeries(Builder $baseQuery, Collection $endMonths): Collection
    {
        $medianExpr = $this->medianPriceExpression($this->quotedColumn('Price'));

        return $endMonths->map(function (Carbon $endMonth) use ($baseQuery, $medianExpr) {
            $range = $this->rollingRangeForEndMonth($endMonth);
            $avgPrice = (clone $baseQuery)
                ->whereBetween('Date', [$range['start'], $range['end']])
                ->selectRaw("ROUND({$medianExpr}) as avg_price")
                ->value('avg_price');

            return (object) [
                'year' => $range['year'],
                'avg_price' => $avgPrice !== null ? (int) $avgPrice : null,
            ];
        })->values();
    }

    protected function buildRollingSalesSeries(Builder $baseQuery, Collection $endMonths): Collection
    {
        return $endMonths->map(function (Carbon $endMonth) use ($baseQuery) {
            $range = $this->rollingRangeForEndMonth($endMonth);

            return (object) [
                'year' => $range['year'],
                'sales' => (clone $baseQuery)
                    ->whereBetween('Date', [$range['start'], $range['end']])
                    ->count(),
            ];
        })->values();
    }

    protected function buildRollingPropertyTypeSeries(Builder $baseQuery, Collection $endMonths): Collection
    {
        return $endMonths->flatMap(function (Carbon $endMonth) use ($baseQuery) {
            $range = $this->rollingRangeForEndMonth($endMonth);

            return (clone $baseQuery)
                ->selectRaw($this->quotedColumn('PropertyType').' as type, COUNT(*) as count')
                ->whereBetween('Date', [$range['start'], $range['end']])
                ->groupBy('PropertyType')
                ->get()
                ->map(fn ($row) => (object) [
                    'year' => $range['year'],
                    'type' => $row->type,
                    'count' => (int) $row->count,
                ]);
        })->values();
    }

    protected function buildRollingAvgPriceByTypeSeries(Builder $baseQuery, Collection $endMonths): Collection
    {
        $medianExpr = $this->medianPriceExpression($this->quotedColumn('Price'));

        return $endMonths->flatMap(function (Carbon $endMonth) use ($baseQuery, $medianExpr) {
            $range = $this->rollingRangeForEndMonth($endMonth);

            return (clone $baseQuery)
                ->selectRaw($this->quotedColumn('PropertyType').' as type, ROUND('.$medianExpr.') as avg_price')
                ->whereBetween('Date', [$range['start'], $range['end']])
                ->whereIn('PropertyType', ['D', 'S', 'T', 'F'])
                ->whereNotNull('Price')
                ->where('Price', '>', 0)
                ->groupBy('PropertyType')
                ->get()
                ->map(fn ($row) => (object) [
                    'year' => $range['year'],
                    'type' => $row->type,
                    'avg_price' => $row->avg_price !== null ? (int) $row->avg_price : null,
                ]);
        })->values();
    }

    protected function buildRollingNewBuildSeries(Builder $baseQuery, Collection $endMonths): Collection
    {
        return $endMonths->map(function (Carbon $endMonth) use ($baseQuery) {
            $range = $this->rollingRangeForEndMonth($endMonth);

            return (clone $baseQuery)
                ->selectRaw(
                    'ROUND(100 * SUM(CASE WHEN '.$this->quotedColumn('NewBuild')." = 'Y' THEN 1 ELSE 0 END) / COUNT(*), 1) as new_pct, ".
                    'ROUND(100 * SUM(CASE WHEN '.$this->quotedColumn('NewBuild')." = 'N' THEN 1 ELSE 0 END) / COUNT(*), 1) as existing_pct"
                )
                ->whereBetween('Date', [$range['start'], $range['end']])
                ->whereNotNull('NewBuild')
                ->whereIn('NewBuild', ['Y', 'N'])
                ->whereNotNull('Price')
                ->where('Price', '>', 0)
                ->get()
                ->map(fn ($row) => (object) [
                    'year' => $range['year'],
                    'new_pct' => $row->new_pct !== null ? (float) $row->new_pct : null,
                    'existing_pct' => $row->existing_pct !== null ? (float) $row->existing_pct : null,
                ]);
        })->flatten(1)->values();
    }

    protected function buildRollingTenureSeries(Builder $baseQuery, Collection $endMonths): Collection
    {
        return $endMonths->map(function (Carbon $endMonth) use ($baseQuery) {
            $range = $this->rollingRangeForEndMonth($endMonth);

            return (clone $baseQuery)
                ->selectRaw(
                    'ROUND(100 * SUM(CASE WHEN '.$this->quotedColumn('Duration')." = 'F' THEN 1 ELSE 0 END) / COUNT(*), 1) as free_pct, ".
                    'ROUND(100 * SUM(CASE WHEN '.$this->quotedColumn('Duration')." = 'L' THEN 1 ELSE 0 END) / COUNT(*), 1) as lease_pct"
                )
                ->whereBetween('Date', [$range['start'], $range['end']])
                ->whereNotNull('Duration')
                ->whereIn('Duration', ['F', 'L'])
                ->whereNotNull('Price')
                ->where('Price', '>', 0)
                ->get()
                ->map(fn ($row) => (object) [
                    'year' => $range['year'],
                    'free_pct' => $row->free_pct !== null ? (float) $row->free_pct : null,
                    'lease_pct' => $row->lease_pct !== null ? (float) $row->lease_pct : null,
                ]);
        })->flatten(1)->values();
    }

    protected function buildRollingP90Series(Builder $baseQuery, Collection $endMonths): Collection
    {
        $priceColumn = $this->quotedColumn('Price');

        return $endMonths->map(function (Carbon $endMonth) use ($baseQuery, $priceColumn) {
            $range = $this->rollingRangeForEndMonth($endMonth);
            $sub = (clone $baseQuery)
                ->selectRaw("{$priceColumn} as price, CUME_DIST() OVER (ORDER BY {$priceColumn}) as cd")
                ->whereBetween('Date', [$range['start'], $range['end']])
                ->whereNotNull('Price')
                ->where('Price', '>', 0);

            $p90 = DB::query()
                ->fromSub($sub, 't')
                ->where('cd', '>=', 0.9)
                ->min('price');

            return (object) [
                'year' => $range['year'],
                'p90' => $p90 !== null ? (int) $p90 : null,
            ];
        })->values();
    }

    protected function buildRollingTop5Series(Builder $baseQuery, Collection $endMonths): Collection
    {
        $priceColumn = $this->quotedColumn('Price');

        return $endMonths->map(function (Carbon $endMonth) use ($baseQuery, $priceColumn) {
            $range = $this->rollingRangeForEndMonth($endMonth);
            $sub = (clone $baseQuery)
                ->selectRaw("{$priceColumn} as price, ROW_NUMBER() OVER (ORDER BY {$priceColumn} DESC) as rn, COUNT(*) OVER () as cnt")
                ->whereBetween('Date', [$range['start'], $range['end']])
                ->whereNotNull('Price')
                ->where('Price', '>', 0);

            $top5 = DB::query()
                ->fromSub($sub, 'r')
                ->selectRaw('ROUND(AVG(price)) as top5_avg')
                ->whereColumn('rn', '<=', DB::raw('CEIL(0.05 * cnt)'))
                ->value('top5_avg');

            return (object) [
                'year' => $range['year'],
                'top5_avg' => $top5 !== null ? (int) $top5 : null,
            ];
        })->values();
    }

    protected function buildRollingTopSaleSeries(Builder $baseQuery, Collection $endMonths): Collection
    {
        return $endMonths->map(function (Carbon $endMonth) use ($baseQuery) {
            $range = $this->rollingRangeForEndMonth($endMonth);

            return (object) [
                'year' => $range['year'],
                'top_sale' => (clone $baseQuery)
                    ->whereBetween('Date', [$range['start'], $range['end']])
                    ->whereNotNull('Price')
                    ->where('Price', '>', 0)
                    ->max('Price'),
            ];
        })->values();
    }

    protected function buildRollingTop3Series(Builder $baseQuery, Collection $endMonths): Collection
    {
        $dateColumn = $this->quotedColumn('Date');
        $postcodeColumn = $this->quotedColumn('Postcode');
        $priceColumn = $this->quotedColumn('Price');

        return $endMonths->flatMap(function (Carbon $endMonth) use ($baseQuery, $dateColumn, $postcodeColumn, $priceColumn) {
            $range = $this->rollingRangeForEndMonth($endMonth);
            $ranked = (clone $baseQuery)
                ->selectRaw("{$dateColumn} as date, {$postcodeColumn} as postcode, {$priceColumn} as price, ROW_NUMBER() OVER (ORDER BY {$priceColumn} DESC) as rn")
                ->whereBetween('Date', [$range['start'], $range['end']])
                ->whereNotNull('Price')
                ->where('Price', '>', 0);

            return DB::query()
                ->fromSub($ranked, 'r')
                ->select('date', 'postcode', 'price', 'rn')
                ->where('rn', '<=', 3)
                ->orderBy('rn')
                ->get()
                ->map(fn ($row) => (object) [
                    'year' => $range['year'],
                    'Date' => $row->date,
                    'Postcode' => $row->postcode,
                    'Price' => $row->price,
                    'rn' => $row->rn,
                ]);
        })->values();
    }

    protected function snapshotFromCharts(array $chartData): array
    {
        $salesSeries = collect($chartData['sales'] ?? [])->sortBy('year')->values();
        $priceSeries = collect($chartData['avgPrice'] ?? [])->sortBy('year')->values();

        $currentSales = $salesSeries->last();
        $previousSales = $salesSeries->count() > 1 ? $salesSeries->slice(-2, 1)->first() : null;
        $currentPrice = $priceSeries->last();
        $previousPrice = $priceSeries->count() > 1 ? $priceSeries->slice(-2, 1)->first() : null;

        return [
            'rolling_12_sales' => (int) ($currentSales->sales ?? 0),
            'rolling_12_median_price' => (int) ($currentPrice->avg_price ?? 0),
            'rolling_12_sales_yoy' => $this->percentageChange($currentSales->sales ?? null, $previousSales->sales ?? null),
            'rolling_12_price_yoy' => $this->percentageChange($currentPrice->avg_price ?? null, $previousPrice->avg_price ?? null),
        ];
    }

    protected function percentageChange(mixed $current, mixed $previous): float
    {
        if ($current === null || $previous === null || (float) $previous === 0.0) {
            return 0.0;
        }

        return round((((float) $current - (float) $previous) / (float) $previous) * 100, 1);
    }
}
