<?php

namespace App\Http\Controllers;

use App\Models\MarketInsight;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class InsightController extends Controller
{
    private const CACHE_TTL = 86400 * 45;

    /**
     * @return array<string, string>
     */
    private function insightTypes(): array
    {
        return [
            'price_spike' => 'Price Spike',
            'price_collapse' => 'Price Collapse',
            'demand_collapse' => 'Demand Collapse',
            'liquidity_stress' => 'Liquidity Stress',
            'liquidity_surge' => 'Liquidity Surge',
            'market_freeze' => 'Market Freeze',
            'sector_outperformance' => 'Sector Outperformance',
            'momentum_reversal' => 'Momentum Reversal',
            'unexpected_hotspot' => 'Unexpected Hotspot',
        ];
    }

    public function show(string $sector): View
    {
        $normalizedSector = strtoupper($sector);
        $normalizedSector = $this->normalizeSector($normalizedSector);

        abort_unless(preg_match('/^[A-Z0-9]+$/', $normalizedSector) === 1, 404);

        $insights = Schema::hasTable('market_insights')
            ? $this->insightsForSector($normalizedSector)
            : collect();

        $salesByYear = Schema::hasTable('land_registry')
            ? $this->salesByYear($normalizedSector)
            : collect();
        $medianPriceByYear = Schema::hasTable('land_registry')
            ? $this->medianPriceByYear($normalizedSector)
            : collect();
        $rollingWindowSeries = Schema::hasTable('land_registry')
            ? $this->rollingWindowSeries($normalizedSector)
            : collect();
        $historyRows = $this->buildHistoryRows($salesByYear, $medianPriceByYear);
        $recentPriceChange = Schema::hasTable('land_registry')
            ? $this->buildRecentPriceChange($normalizedSector)
            : null;

        return view('insights.show', [
            'sector' => $normalizedSector,
            'insights' => $insights,
            'insightTypes' => $this->insightTypes(),
            'minSectorTransactions' => config('insights.min_sector_transactions'),
            'recentPriceChange' => $recentPriceChange,
            'rollingSalesChart' => [
                'labels' => $rollingWindowSeries->pluck('label')->all(),
                'values' => $rollingWindowSeries->pluck('sales')->map(fn (int $sales): int => $sales)->all(),
            ],
            'rollingPriceChart' => [
                'labels' => $rollingWindowSeries->pluck('label')->all(),
                'values' => $rollingWindowSeries->pluck('median_price')->map(fn (?float $median): ?float => $median === null ? null : round($median, 2))->all(),
            ],
            'salesChart' => [
                'labels' => $salesByYear->pluck('year')->map(fn (int $year): string => (string) $year)->all(),
                'values' => $salesByYear->pluck('sales')->map(fn (int $sales): int => $sales)->all(),
            ],
            'medianPriceChart' => [
                'labels' => $medianPriceByYear->pluck('year')->map(fn (int $year): string => (string) $year)->all(),
                'values' => $medianPriceByYear->pluck('median_price')->map(fn (?float $median): ?float => $median === null ? null : round($median, 2))->all(),
            ],
            'historyRows' => $historyRows,
        ]);
    }

    public function warmSectorCache(string $sector): void
    {
        $normalizedSector = strtoupper($sector);
        $normalizedSector = $this->normalizeSector($normalizedSector);

        if (preg_match('/^[A-Z0-9]+$/', $normalizedSector) !== 1) {
            return;
        }

        if (Schema::hasTable('market_insights')) {
            $this->insightsForSector($normalizedSector);
        }

        if (! Schema::hasTable('land_registry')) {
            return;
        }

        $salesByYear = $this->salesByYear($normalizedSector);
        $medianPriceByYear = $this->medianPriceByYear($normalizedSector);

        $this->rollingWindowSeries($normalizedSector);
        $this->buildRecentPriceChange($normalizedSector);
        $this->buildHistoryRows($salesByYear, $medianPriceByYear);
    }

    private function insightsForSector(string $sector): Collection
    {
        $cacheKey = $this->cacheKey($sector, 'insights');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($sector): Collection {
            return MarketInsight::query()
                ->whereRaw('UPPER(area_code) = ?', [$sector])
                ->orderByDesc('period_end')
                ->orderByDesc('created_at')
                ->get();
        });
    }

    private function normalizeSector(string $sector): string
    {
        return strtoupper(str_replace(' ', '', trim($sector)));
    }

    /**
     * @return Collection<int, array{year:int,sales:int}>
     */
    private function salesByYear(string $sector): Collection
    {
        $yearExpression = $this->yearExpression();
        $cacheKey = $this->cacheKey($sector, 'sales_by_year');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($sector, $yearExpression): Collection {
            $excludedYear = $this->excludedHistoricalYear();

            $query = DB::table('land_registry')
                ->selectRaw($yearExpression.' as year, COUNT(*) as sales')
                ->where('PPDCategoryType', 'A')
                ->where('NewBuild', 'N')
                ->whereRaw($this->normalizedPostcodeExpression()." LIKE (? || '%')", [$sector]);

            if ($excludedYear !== null) {
                $query->whereRaw($yearExpression.' <> ?', [$excludedYear]);
            }

            $rows = $query
                ->groupBy(DB::raw($yearExpression))
                ->orderBy('year')
                ->get();

            return collect($rows)->map(function (object $row): array {
                return [
                    'year' => (int) $row->year,
                    'sales' => (int) $row->sales,
                ];
            })->values();
        });
    }

    /**
     * @return Collection<int, array{year:int,median_price:?float}>
     */
    private function medianPriceByYear(string $sector): Collection
    {
        $yearExpression = $this->yearExpression();
        $cacheKey = $this->cacheKey($sector, 'median_price_by_year');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($sector, $yearExpression): Collection {
            $excludedYear = $this->excludedHistoricalYear();
            $query = DB::table('land_registry')
                ->where('PPDCategoryType', 'A')
                ->where('NewBuild', 'N')
                ->whereNotNull('Price')
                ->whereRaw($this->normalizedPostcodeExpression()." LIKE (? || '%')", [$sector]);

            if ($excludedYear !== null) {
                $query->whereRaw($yearExpression.' <> ?', [$excludedYear]);
            }

            if (DB::connection()->getDriverName() === 'pgsql') {
                $rows = $query
                    ->selectRaw($yearExpression.' as year, PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY "Price") as median_price')
                    ->groupBy(DB::raw($yearExpression))
                    ->orderBy('year')
                    ->get();

                return collect($rows)->map(function (object $row): array {
                    return [
                        'year' => (int) $row->year,
                        'median_price' => $row->median_price === null ? null : (float) $row->median_price,
                    ];
                })->values();
            }

            $rows = $query
                ->selectRaw($yearExpression.' as year, AVG("Price") as median_price')
                ->groupBy(DB::raw($yearExpression))
                ->orderBy('year')
                ->get();

            return collect($rows)->map(function (object $row): array {
                return [
                    'year' => (int) $row->year,
                    'median_price' => $row->median_price === null ? null : (float) $row->median_price,
                ];
            })->values();
        });
    }

    /**
     * @param  Collection<int, array{year:int,sales:int}>  $salesByYear
     * @param  Collection<int, array{year:int,median_price:?float}>  $medianPriceByYear
     * @return Collection<int, array{year:int,sales:int,median_price:?float}>
     */
    private function buildHistoryRows(Collection $salesByYear, Collection $medianPriceByYear): Collection
    {
        $salesLookup = $salesByYear->keyBy('year');
        $medianLookup = $medianPriceByYear->keyBy('year');

        return $salesByYear
            ->pluck('year')
            ->merge($medianPriceByYear->pluck('year'))
            ->unique()
            ->sort()
            ->values()
            ->map(function (int $year) use ($salesLookup, $medianLookup): array {
                return [
                    'year' => $year,
                    'sales' => (int) data_get($salesLookup->get($year), 'sales', 0),
                    'median_price' => data_get($medianLookup->get($year), 'median_price'),
                ];
            });
    }

    /**
     * @return array{current_start:Carbon,current_end:Carbon,previous_start:Carbon,previous_end:Carbon,current_price:float,previous_price:float,growth:float,current_label:string,previous_label:string}|null
     */
    private function buildRecentPriceChange(string $sector): ?array
    {
        $cacheKey = $this->cacheKey($sector, 'recent_price_change');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($sector): ?array {
            $periods = $this->rollingPeriods();
            if ($periods === null) {
                return null;
            }

            $currentPrice = $this->medianPriceForWindow($sector, $periods['current_start'], $periods['current_end']);
            $previousPrice = $this->medianPriceForWindow($sector, $periods['previous_start'], $periods['previous_end']);

            if ($currentPrice === null || $previousPrice === null || $previousPrice <= 0) {
                return null;
            }

            $growth = (($currentPrice - $previousPrice) / $previousPrice) * 100;

            return [
                'current_start' => $periods['current_start']->copy(),
                'current_end' => $periods['current_end']->copy(),
                'previous_start' => $periods['previous_start']->copy(),
                'previous_end' => $periods['previous_end']->copy(),
                'current_label' => $this->periodLabel($periods['current_start'], $periods['current_end']),
                'previous_label' => $this->periodLabel($periods['previous_start'], $periods['previous_end']),
                'current_price' => $currentPrice,
                'previous_price' => $previousPrice,
                'growth' => round($growth, 2),
            ];
        });
    }

    private function medianPriceForWindow(string $sector, Carbon $start, Carbon $end): ?float
    {
        $query = DB::table('land_registry')
            ->where('PPDCategoryType', 'A')
            ->where('NewBuild', 'N')
            ->whereNotNull('Price')
            ->whereRaw($this->normalizedPostcodeExpression()." LIKE (? || '%')", [$sector])
            ->whereBetween('Date', [$start->toDateString(), $end->toDateString()]);

        $medianPrice = DB::connection()->getDriverName() === 'pgsql'
            ? $query->selectRaw('PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY "Price") as median_price')->value('median_price')
            : $query->selectRaw('AVG("Price") as median_price')->value('median_price');

        return is_numeric($medianPrice) ? (float) $medianPrice : null;
    }

    /**
     * @return Collection<int, array{label:string,sales:int,median_price:?float,start:Carbon,end:Carbon}>
     */
    private function rollingWindowSeries(string $sector, int $windows = 10): Collection
    {
        $cacheKey = $this->cacheKey($sector, 'rolling_window_series');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($sector, $windows): Collection {
            $periods = $this->rollingPeriods();
            if ($periods === null) {
                return collect();
            }

            $series = collect();

            for ($offset = $windows - 1; $offset >= 0; $offset--) {
                $windowStart = $periods['current_start']->copy()->subYears($offset);
                $windowEnd = $periods['current_end']->copy()->subYears($offset);

                $series->push([
                    'label' => $windowEnd->format('Y'),
                    'sales' => $this->salesCountForWindow($sector, $windowStart, $windowEnd),
                    'median_price' => $this->medianPriceForWindow($sector, $windowStart, $windowEnd),
                    'start' => $windowStart,
                    'end' => $windowEnd,
                ]);
            }

            return $series;
        });
    }

    private function salesCountForWindow(string $sector, Carbon $start, Carbon $end): int
    {
        return (int) DB::table('land_registry')
            ->where('PPDCategoryType', 'A')
            ->where('NewBuild', 'N')
            ->whereRaw($this->normalizedPostcodeExpression()." LIKE (? || '%')", [$sector])
            ->whereBetween('Date', [$start->toDateString(), $end->toDateString()])
            ->count();
    }

    /**
     * @return array{current_start: Carbon, current_end: Carbon, previous_start: Carbon, previous_end: Carbon}|null
     */
    private function rollingPeriods(): ?array
    {
        $latestDate = DB::table('land_registry')
            ->whereNotNull('Date')
            ->max('Date');

        if (! is_string($latestDate) || $latestDate === '') {
            return null;
        }

        $currentEnd = Carbon::parse($latestDate)->startOfDay();
        $currentStart = $currentEnd->copy()->addDay()->subYear()->startOfDay();
        $previousEnd = $currentStart->copy()->subDay()->startOfDay();
        $previousStart = $currentStart->copy()->subYear()->startOfDay();

        return [
            'current_start' => $currentStart,
            'current_end' => $currentEnd,
            'previous_start' => $previousStart,
            'previous_end' => $previousEnd,
        ];
    }

    private function excludedHistoricalYear(): ?int
    {
        $latestDate = DB::table('land_registry')
            ->whereNotNull('Date')
            ->max('Date');

        if (! is_string($latestDate) || $latestDate === '') {
            return null;
        }

        $latest = Carbon::parse($latestDate);
        $latestYear = (int) $latest->format('Y');
        $yearExpression = $this->yearExpression();
        $monthExpression = $this->monthExpression();

        $monthCount = DB::table('land_registry')
            ->whereNotNull('Date')
            ->whereRaw($yearExpression.' = ?', [$latestYear])
            ->selectRaw('COUNT(DISTINCT '.$monthExpression.') as month_count')
            ->value('month_count');

        if ((int) $monthCount < 12) {
            return $latestYear;
        }

        return null;
    }

    private function monthExpression(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return 'CAST(strftime(\'%m\', "Date") AS INTEGER)';
        }

        return 'EXTRACT(MONTH FROM "Date")::int';
    }

    private function periodLabel(Carbon $start, Carbon $end): string
    {
        return $start->format('d M Y').' to '.$end->format('d M Y');
    }

    private function yearExpression(): string
    {
        if (Schema::hasColumn('land_registry', 'YearDate')) {
            return '"YearDate"';
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            return 'CAST(strftime(\'%Y\', "Date") AS INTEGER)';
        }

        return 'EXTRACT(YEAR FROM "Date")::int';
    }

    private function normalizedPostcodeExpression(): string
    {
        return 'REPLACE("Postcode", \' \', \'\')';
    }

    private function cacheKey(string $sector, string $suffix): string
    {
        return 'insights:v'.$this->cacheVersion().':'.$sector.':'.$suffix;
    }

    private function cacheVersion(): int
    {
        return (int) Cache::get('insights:cache_version', 1);
    }
}
