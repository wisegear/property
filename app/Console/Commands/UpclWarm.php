<?php

namespace App\Console\Commands;

use App\Http\Controllers\Concerns\BuildsRollingPrimeDashboardData;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class UpclWarm extends Command
{
    use BuildsRollingPrimeDashboardData {
        buildRollingMedianSeries as protected buildRollingMedianSeriesFallback;
        buildRollingSalesSeries as protected buildRollingSalesSeriesFallback;
        buildRollingPropertyTypeSeries as protected buildRollingPropertyTypeSeriesFallback;
        buildRollingAvgPriceByTypeSeries as protected buildRollingAvgPriceByTypeSeriesFallback;
        buildRollingNewBuildSeries as protected buildRollingNewBuildSeriesFallback;
        buildRollingTenureSeries as protected buildRollingTenureSeriesFallback;
        buildRollingP90Series as protected buildRollingP90SeriesFallback;
        buildRollingTop5Series as protected buildRollingTop5SeriesFallback;
        buildRollingTopSaleSeries as protected buildRollingTopSaleSeriesFallback;
        buildRollingTop3Series as protected buildRollingTop3SeriesFallback;
    }

    private const CATEGORY = 'Ultra Prime';

    private const CACHE_PREFIX = 'upcl:home:rolling';

    private ?bool $monthlyPrefixMvExists = null;

    private ?array $primeDistrictPrefixes = null;

    private ?string $activeTempTable = null;

    protected $signature = 'upcl:warm {--district=} {--parallel=1}';

    protected $description = 'Warm the Ultra Prime Central London cache';

    public function handle(): int
    {
        $districtOption = strtoupper(trim((string) ($this->option('district') ?? '')));
        $parallel = max(1, (int) ($this->option('parallel') ?? 1));
        $ttl = 60 * 60 * 24 * 45;

        DB::connection()->disableQueryLog();

        $latestMonth = $this->latestMonth();
        $cachePrefix = self::CACHE_PREFIX.':'.$latestMonth->format('Ym');
        $endMonths = $this->rollingEndMonths(
            DB::table('land_registry')->where('PPDCategoryType', 'A'),
            $latestMonth
        )->all();

        if ($districtOption !== '') {
            $this->warmDistrict($districtOption, $cachePrefix, $ttl, $endMonths);
            Cache::put($cachePrefix.':last_warm', now()->toIso8601String(), $ttl);

            return self::SUCCESS;
        }

        $this->info('Starting Ultra Prime cache warm...');

        $districts = DB::table('prime_postcodes')
            ->where('category', self::CATEGORY)
            ->pluck('postcode')
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($districts)) {
            $this->warn('No Ultra Prime districts found.');

            return self::SUCCESS;
        }

        if ($parallel <= 1) {
            $this->withProgressBar($districts, function (string $district) use ($cachePrefix, $ttl, $endMonths) {
                $this->warmDistrict($district, $cachePrefix, $ttl, $endMonths);
            });
        } elseif (! $this->warmInParallel($districts, $parallel)) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Warming ALL Ultra Prime (aggregate)');

        $this->createAllDistrictTempTable();

        try {
            $this->storeSeries('ALL', $this->buildSeries($this->baseAllDistrictsQuery(), $endMonths, 'ALL'), $cachePrefix, $ttl);
        } finally {
            $this->dropAllDistrictTempTable();
        }
        Cache::put($cachePrefix.':last_warm', now()->toIso8601String(), $ttl);

        $this->newLine(2);
        $this->info('Ultra Prime cache warm complete.');

        return self::SUCCESS;
    }

    private function warmInParallel(array $districts, int $parallel): bool
    {
        $this->info("Running in parallel with up to {$parallel} workers...");

        $bar = $this->output->createProgressBar(count($districts));
        $bar->start();

        $maxWorkers = (int) min($parallel, 8);
        $queue = $districts;
        $running = [];
        $failedDistricts = [];

        $startWorker = function (string $district) use (&$running): void {
            $proc = new Process([PHP_BINARY, base_path('artisan'), 'upcl:warm', '--district='.$district]);
            $proc->setTimeout(null);
            $proc->disableOutput();
            $proc->start();
            $running[$district] = $proc;
        };

        while (! empty($queue) && count($running) < $maxWorkers) {
            $startWorker(array_shift($queue));
        }

        while (! empty($running)) {
            foreach ($running as $district => $proc) {
                if (! $proc->isRunning()) {
                    if ($proc->getExitCode() !== 0) {
                        $this->warn("Worker failed for {$district} (exit code: {$proc->getExitCode()})");
                        $failedDistricts[] = $district;
                    }

                    unset($running[$district]);
                    $bar->advance();

                    if (! empty($queue)) {
                        $startWorker(array_shift($queue));
                    }
                }
            }

            usleep(100000);
        }

        $bar->finish();
        $this->newLine();

        if (! empty($failedDistricts)) {
            $this->error('Ultra Prime warm failed for: '.implode(', ', $failedDistricts));

            return false;
        }

        return true;
    }

    private function warmDistrict(string $district, string $cachePrefix, int $ttl, array $endMonths): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->storeSeries($district, $this->buildSeries($this->baseDistrictQuery($district), $endMonths, $district), $cachePrefix, $ttl);

            return;
        }

        $this->createDistrictTempTable($district);

        try {
            $this->storeSeries($district, $this->buildSeries($this->baseDistrictQuery($district), $endMonths, $district), $cachePrefix, $ttl);
        } finally {
            $this->dropDistrictTempTable();
        }
    }

    private function buildSeries(Builder $baseQuery, array $endMonths, string $district): array
    {
        $endMonths = collect($endMonths);

        return [
            'avgPrice' => $this->buildRollingMedianSeries($baseQuery, $endMonths),
            'sales' => $this->buildRollingSalesSeriesFromMonthlyPrefixMv($baseQuery, $endMonths, $district),
            'propertyTypes' => $this->buildRollingPropertyTypeSeries($baseQuery, $endMonths),
            'avgPriceByType' => $this->buildRollingAvgPriceByTypeSeries($baseQuery, $endMonths),
            'newBuildPct' => $this->buildRollingNewBuildSeriesFromMonthlyPrefixMv($baseQuery, $endMonths, $district),
            'tenurePct' => $this->buildRollingTenureSeriesFromMonthlyPrefixMv($baseQuery, $endMonths, $district),
            'p90' => $this->buildRollingP90Series($baseQuery, $endMonths),
            'top5' => $this->buildRollingTop5Series($baseQuery, $endMonths),
            'topSalePerYear' => $this->buildRollingTopSaleSeries($baseQuery, $endMonths),
            'top3PerYear' => $this->buildRollingTop3Series($baseQuery, $endMonths),
        ];
    }

    private function storeSeries(string $district, array $series, string $cachePrefix, int $ttl): void
    {
        foreach ($series as $name => $value) {
            Cache::put($cachePrefix.':'.$district.':'.$name, $value, $ttl);
        }
    }

    private function buildRollingSalesSeriesFromMonthlyPrefixMv(Builder $baseQuery, \Illuminate\Support\Collection $endMonths, string $district): \Illuminate\Support\Collection
    {
        if (! $this->canUseMonthlyPrefixMv()) {
            return $this->buildRollingSalesSeries($baseQuery, $endMonths);
        }

        $rows = DB::query()
            ->fromSub($this->monthSeriesQuery($baseQuery, $endMonths), 'months')
            ->leftJoinSub($this->monthlyPrefixAggregateQuery($district), 'monthly', 'months.month', '=', 'monthly.month')
            ->selectRaw(
                'months.month, '.
                'SUM(COALESCE(monthly.sales, 0)) OVER (ORDER BY months.month ROWS BETWEEN 11 PRECEDING AND CURRENT ROW) as rolling_sales'
            )
            ->whereIn('months.month', $this->endMonthKeys($endMonths))
            ->get()
            ->keyBy('month');

        return $endMonths->map(fn ($endMonth) => (object) [
            'year' => $endMonth->year,
            'sales' => isset($rows[$endMonth->toDateString()]) ? (int) $rows[$endMonth->toDateString()]->rolling_sales : 0,
        ])->values();
    }

    private function buildRollingNewBuildSeriesFromMonthlyPrefixMv(Builder $baseQuery, \Illuminate\Support\Collection $endMonths, string $district): \Illuminate\Support\Collection
    {
        if (! $this->canUseMonthlyPrefixMv()) {
            return $this->buildRollingNewBuildSeries($baseQuery, $endMonths);
        }

        $rolling = DB::query()
            ->fromSub($this->monthSeriesQuery($baseQuery, $endMonths), 'months')
            ->leftJoinSub($this->monthlyPrefixAggregateQuery($district), 'monthly', 'months.month', '=', 'monthly.month')
            ->selectRaw(
                'months.month, '.
                'SUM(COALESCE(monthly.new_build_sales, 0)) OVER (ORDER BY months.month ROWS BETWEEN 11 PRECEDING AND CURRENT ROW) as rolling_new_count, '.
                'SUM(COALESCE(monthly.existing_sales, 0)) OVER (ORDER BY months.month ROWS BETWEEN 11 PRECEDING AND CURRENT ROW) as rolling_existing_count'
            );

        $rows = DB::query()
            ->fromSub($rolling, 'rolling')
            ->selectRaw(
                'month, '.
                'CASE WHEN (rolling_new_count + rolling_existing_count) > 0 THEN ROUND(100.0 * rolling_new_count / (rolling_new_count + rolling_existing_count), 1) ELSE NULL END as new_pct, '.
                'CASE WHEN (rolling_new_count + rolling_existing_count) > 0 THEN ROUND(100.0 * rolling_existing_count / (rolling_new_count + rolling_existing_count), 1) ELSE NULL END as existing_pct'
            )
            ->whereIn('month', $this->endMonthKeys($endMonths))
            ->get()
            ->keyBy('month');

        return $endMonths->map(fn ($endMonth) => (object) [
            'year' => $endMonth->year,
            'new_pct' => isset($rows[$endMonth->toDateString()]) ? (float) $rows[$endMonth->toDateString()]->new_pct : null,
            'existing_pct' => isset($rows[$endMonth->toDateString()]) ? (float) $rows[$endMonth->toDateString()]->existing_pct : null,
        ])->values();
    }

    private function buildRollingTenureSeriesFromMonthlyPrefixMv(Builder $baseQuery, \Illuminate\Support\Collection $endMonths, string $district): \Illuminate\Support\Collection
    {
        if (! $this->canUseMonthlyPrefixMv()) {
            return $this->buildRollingTenureSeries($baseQuery, $endMonths);
        }

        $rolling = DB::query()
            ->fromSub($this->monthSeriesQuery($baseQuery, $endMonths), 'months')
            ->leftJoinSub($this->monthlyPrefixAggregateQuery($district), 'monthly', 'months.month', '=', 'monthly.month')
            ->selectRaw(
                'months.month, '.
                'SUM(COALESCE(monthly.freehold_sales, 0)) OVER (ORDER BY months.month ROWS BETWEEN 11 PRECEDING AND CURRENT ROW) as rolling_freehold_count, '.
                'SUM(COALESCE(monthly.leasehold_sales, 0)) OVER (ORDER BY months.month ROWS BETWEEN 11 PRECEDING AND CURRENT ROW) as rolling_leasehold_count'
            );

        $rows = DB::query()
            ->fromSub($rolling, 'rolling')
            ->selectRaw(
                'month, '.
                'CASE WHEN (rolling_freehold_count + rolling_leasehold_count) > 0 THEN ROUND(100.0 * rolling_freehold_count / (rolling_freehold_count + rolling_leasehold_count), 1) ELSE NULL END as free_pct, '.
                'CASE WHEN (rolling_freehold_count + rolling_leasehold_count) > 0 THEN ROUND(100.0 * rolling_leasehold_count / (rolling_freehold_count + rolling_leasehold_count), 1) ELSE NULL END as lease_pct'
            )
            ->whereIn('month', $this->endMonthKeys($endMonths))
            ->get()
            ->keyBy('month');

        return $endMonths->map(fn ($endMonth) => (object) [
            'year' => $endMonth->year,
            'free_pct' => isset($rows[$endMonth->toDateString()]) ? (float) $rows[$endMonth->toDateString()]->free_pct : null,
            'lease_pct' => isset($rows[$endMonth->toDateString()]) ? (float) $rows[$endMonth->toDateString()]->lease_pct : null,
        ])->values();
    }

    private function monthlyPrefixAggregateQuery(string $district): Builder
    {
        if ($district !== 'ALL') {
            return DB::table('land_registry_monthly_prefix_mv')
                ->selectRaw(
                    'month, '.
                    'SUM(sales) as sales, '.
                    'SUM(new_build_sales) as new_build_sales, '.
                    'SUM(existing_sales) as existing_sales, '.
                    'SUM(freehold_sales) as freehold_sales, '.
                    'SUM(leasehold_sales) as leasehold_sales'
                )
                ->where('postcode_prefix', 'like', $district.'%')
                ->groupBy('month');
        }

        return DB::table('land_registry_monthly_prefix_mv as mv')
            ->selectRaw(
                'mv.month, '.
                'SUM(mv.sales) as sales, '.
                'SUM(mv.new_build_sales) as new_build_sales, '.
                'SUM(mv.existing_sales) as existing_sales, '.
                'SUM(mv.freehold_sales) as freehold_sales, '.
                'SUM(mv.leasehold_sales) as leasehold_sales'
            )
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('prime_postcodes as pp')
                    ->where('pp.category', self::CATEGORY)
                    ->whereRaw("mv.postcode_prefix LIKE (pp.postcode || '%')");
            })
            ->groupBy('mv.month');
    }

    private function canUseMonthlyPrefixMv(): bool
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return false;
        }

        if ($this->monthlyPrefixMvExists !== null) {
            return $this->monthlyPrefixMvExists;
        }

        $exists = DB::scalar("SELECT to_regclass('public.land_registry_monthly_prefix_mv') IS NOT NULL");

        return $this->monthlyPrefixMvExists = (bool) $exists;
    }

    private function primeDistrictPrefixes(): array
    {
        if ($this->primeDistrictPrefixes !== null) {
            return $this->primeDistrictPrefixes;
        }

        return $this->primeDistrictPrefixes = DB::table('prime_postcodes')
            ->where('category', self::CATEGORY)
            ->pluck('postcode')
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function buildRollingMedianSeries(Builder $baseQuery, \Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $this->buildRollingMedianSeriesFallback($baseQuery, $endMonths);
        }

        $rows = $this->joinedRollingRows($baseQuery, $endMonths)
            ->selectRaw('ranges.year, ROUND(PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY rows.price)) as avg_price')
            ->whereNotNull('rows.price')
            ->where('rows.price', '>', 0)
            ->groupBy('ranges.year')
            ->orderBy('ranges.year')
            ->get()
            ->keyBy('year');

        return $endMonths->map(fn ($endMonth) => (object) [
            'year' => $endMonth->year,
            'avg_price' => isset($rows[$endMonth->year]) ? (int) $rows[$endMonth->year]->avg_price : null,
        ])->values();
    }

    protected function buildRollingSalesSeries(Builder $baseQuery, \Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $this->buildRollingSalesSeriesFallback($baseQuery, $endMonths);
        }

        $rows = $this->salesRollingMonthlyQuery($baseQuery, $endMonths)
            ->whereIn('months.month', $this->endMonthKeys($endMonths))
            ->get()
            ->keyBy('month');

        return $endMonths->map(fn ($endMonth) => (object) [
            'year' => $endMonth->year,
            'sales' => isset($rows[$endMonth->toDateString()]) ? (int) $rows[$endMonth->toDateString()]->rolling_sales : 0,
        ])->values();
    }

    protected function buildRollingPropertyTypeSeries(Builder $baseQuery, \Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $this->buildRollingPropertyTypeSeriesFallback($baseQuery, $endMonths);
        }

        return $this->propertyTypeRollingMonthlyQuery($baseQuery, $endMonths)
            ->whereIn('months.month', $this->endMonthKeys($endMonths))
            ->get()
            ->map(fn ($row) => (object) [
                'year' => (int) date('Y', strtotime((string) $row->month)),
                'type' => $row->type,
                'count' => (int) $row->rolling_count,
            ])
            ->values();
    }

    protected function buildRollingAvgPriceByTypeSeries(Builder $baseQuery, \Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $this->buildRollingAvgPriceByTypeSeriesFallback($baseQuery, $endMonths);
        }

        return $this->joinedRollingRows($baseQuery, $endMonths)
            ->selectRaw('ranges.year, rows.property_type as type, ROUND(PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY rows.price)) as avg_price')
            ->whereIn('rows.property_type', ['D', 'S', 'T', 'F'])
            ->whereNotNull('rows.price')
            ->where('rows.price', '>', 0)
            ->groupBy('ranges.year', 'rows.property_type')
            ->orderBy('ranges.year')
            ->get()
            ->map(fn ($row) => (object) [
                'year' => (int) $row->year,
                'type' => $row->type,
                'avg_price' => $row->avg_price !== null ? (int) $row->avg_price : null,
            ])
            ->values();
    }

    protected function buildRollingNewBuildSeries(Builder $baseQuery, \Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $this->buildRollingNewBuildSeriesFallback($baseQuery, $endMonths);
        }

        $rows = $this->newBuildRollingMonthlyQuery($baseQuery, $endMonths)
            ->whereIn('month', $this->endMonthKeys($endMonths))
            ->get()
            ->keyBy('month');

        return $endMonths->map(fn ($endMonth) => (object) [
            'year' => $endMonth->year,
            'new_pct' => isset($rows[$endMonth->toDateString()]) ? (float) $rows[$endMonth->toDateString()]->new_pct : null,
            'existing_pct' => isset($rows[$endMonth->toDateString()]) ? (float) $rows[$endMonth->toDateString()]->existing_pct : null,
        ])->values();
    }

    protected function buildRollingTenureSeries(Builder $baseQuery, \Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $this->buildRollingTenureSeriesFallback($baseQuery, $endMonths);
        }

        $rows = $this->tenureRollingMonthlyQuery($baseQuery, $endMonths)
            ->whereIn('month', $this->endMonthKeys($endMonths))
            ->get()
            ->keyBy('month');

        return $endMonths->map(fn ($endMonth) => (object) [
            'year' => $endMonth->year,
            'free_pct' => isset($rows[$endMonth->toDateString()]) ? (float) $rows[$endMonth->toDateString()]->free_pct : null,
            'lease_pct' => isset($rows[$endMonth->toDateString()]) ? (float) $rows[$endMonth->toDateString()]->lease_pct : null,
        ])->values();
    }

    protected function buildRollingP90Series(Builder $baseQuery, \Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $this->buildRollingP90SeriesFallback($baseQuery, $endMonths);
        }

        $rows = $this->joinedRollingRows($baseQuery, $endMonths)
            ->selectRaw('ranges.year, ROUND(PERCENTILE_CONT(0.9) WITHIN GROUP (ORDER BY rows.price)) as p90')
            ->whereNotNull('rows.price')
            ->where('rows.price', '>', 0)
            ->groupBy('ranges.year')
            ->orderBy('ranges.year')
            ->get()
            ->keyBy('year');

        return $endMonths->map(fn ($endMonth) => (object) [
            'year' => $endMonth->year,
            'p90' => isset($rows[$endMonth->year]) ? (int) $rows[$endMonth->year]->p90 : null,
        ])->values();
    }

    protected function buildRollingTop5Series(Builder $baseQuery, \Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $this->buildRollingTop5SeriesFallback($baseQuery, $endMonths);
        }

        $ranked = DB::query()
            ->fromSub($this->joinedRollingRows($baseQuery, $endMonths), 'joined')
            ->selectRaw('year, price, ROW_NUMBER() OVER (PARTITION BY year ORDER BY price DESC) as rn, COUNT(*) OVER (PARTITION BY year) as cnt')
            ->whereNotNull('price')
            ->where('price', '>', 0);

        $rows = DB::query()
            ->fromSub($ranked, 'ranked')
            ->selectRaw('year, ROUND(AVG(price)) as top5_avg')
            ->whereColumn('rn', '<=', DB::raw('CEIL(0.05 * cnt)'))
            ->groupBy('year')
            ->orderBy('year')
            ->get()
            ->keyBy('year');

        return $endMonths->map(fn ($endMonth) => (object) [
            'year' => $endMonth->year,
            'top5_avg' => isset($rows[$endMonth->year]) ? (int) $rows[$endMonth->year]->top5_avg : null,
        ])->values();
    }

    protected function buildRollingTopSaleSeries(Builder $baseQuery, \Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $this->buildRollingTopSaleSeriesFallback($baseQuery, $endMonths);
        }

        $rows = DB::query()
            ->fromSub($this->normalizedBaseRowsQuery($baseQuery), 'rows')
            ->selectRaw('EXTRACT(YEAR FROM rows.date)::int as year, MAX(rows.price) as top_sale')
            ->whereIn(DB::raw('EXTRACT(YEAR FROM rows.date)::int'), $endMonths->map(fn ($endMonth) => $endMonth->year)->all())
            ->whereNotNull('rows.date')
            ->whereNotNull('rows.price')
            ->where('rows.price', '>', 0)
            ->groupBy(DB::raw('EXTRACT(YEAR FROM rows.date)::int'))
            ->get()
            ->keyBy('year');

        return $endMonths->map(fn ($endMonth) => (object) [
            'year' => $endMonth->year,
            'top_sale' => isset($rows[$endMonth->year]) ? (int) $rows[$endMonth->year]->top_sale : null,
        ])->values();
    }

    protected function buildRollingTop3Series(Builder $baseQuery, \Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $this->buildRollingTop3SeriesFallback($baseQuery, $endMonths);
        }

        $ranked = DB::query()
            ->fromSub($this->normalizedBaseRowsQuery($baseQuery), 'rows')
            ->selectRaw('EXTRACT(YEAR FROM rows.date)::int as year, rows.date as date, rows.postcode as postcode, rows.price as price, ROW_NUMBER() OVER (PARTITION BY EXTRACT(YEAR FROM rows.date)::int ORDER BY rows.price DESC) as rn')
            ->whereIn(DB::raw('EXTRACT(YEAR FROM rows.date)::int'), $endMonths->map(fn ($endMonth) => $endMonth->year)->all())
            ->whereNotNull('rows.date')
            ->whereNotNull('price')
            ->where('price', '>', 0);

        return DB::query()
            ->fromSub($ranked, 'ranked')
            ->select('year', 'date', 'postcode', 'price', 'rn')
            ->where('rn', '<=', 3)
            ->orderBy('year')
            ->orderBy('rn')
            ->get()
            ->map(fn ($row) => (object) [
                'year' => (int) $row->year,
                'Date' => $row->date,
                'Postcode' => $row->postcode,
                'Price' => $row->price,
                'rn' => (int) $row->rn,
            ])
            ->values();
    }

    private function joinedRollingRows(Builder $baseQuery, \Illuminate\Support\Collection $endMonths): Builder
    {
        return DB::query()
            ->fromSub($this->normalizedBaseRowsQuery($baseQuery), 'rows')
            ->joinSub($this->rangesQuery($endMonths), 'ranges', function ($join) {
                $join->whereRaw('rows.date >= ranges.start_date AND rows.date <= ranges.end_date');
            });
    }

    private function normalizedBaseRowsQuery(Builder $baseQuery): Builder
    {
        if ($this->activeTempTable !== null && $this->hasTempTable($this->activeTempTable)) {
            return DB::table($this->activeTempTable)
                ->selectRaw('date, price, property_type, new_build, duration, postcode');
        }

        return DB::query()
            ->fromSub($baseQuery, 'lr')
            ->selectRaw('lr."Date" as date, lr."Price" as price, lr."PropertyType" as property_type, lr."NewBuild" as new_build, lr."Duration" as duration, lr."Postcode" as postcode');
    }

    private function createDistrictTempTable(string $district): void
    {
        $this->dropDistrictTempTable();

        DB::statement(
            'CREATE TEMP TABLE tmp_prime_district AS
            SELECT
                "Date" as date,
                "Price" as price,
                "PropertyType" as property_type,
                "NewBuild" as new_build,
                "Duration" as duration,
                "Postcode" as postcode
            FROM land_registry
            WHERE "PPDCategoryType" = ?
            AND REPLACE("Postcode", \' \', \'\') LIKE (? || \'%\')',
            ['A', $district]
        );

        DB::statement('CREATE INDEX tmp_prime_date_idx ON tmp_prime_district(date)');
        DB::statement('CREATE INDEX tmp_prime_price_idx ON tmp_prime_district(price)');
        $this->activeTempTable = 'tmp_prime_district';
    }

    private function dropDistrictTempTable(): void
    {
        DB::statement('DROP TABLE IF EXISTS tmp_prime_district');
        if ($this->activeTempTable === 'tmp_prime_district') {
            $this->activeTempTable = null;
        }
    }

    private function createAllDistrictTempTable(): void
    {
        $this->dropAllDistrictTempTable();

        DB::statement(
            'CREATE TEMP TABLE tmp_prime_all AS
            SELECT
                "Date" as date,
                "Price" as price,
                "PropertyType" as property_type,
                "NewBuild" as new_build,
                "Duration" as duration,
                "Postcode" as postcode
            FROM land_registry
            WHERE "PPDCategoryType" = ?
            AND EXISTS (
                SELECT 1
                FROM prime_postcodes pp
                WHERE pp.category = ?
                AND REPLACE("Postcode", \' \', \'\') LIKE (pp.postcode || \'%\')
            )',
            ['A', self::CATEGORY]
        );

        DB::statement('CREATE INDEX tmp_prime_all_date_idx ON tmp_prime_all(date)');
        DB::statement('CREATE INDEX tmp_prime_all_price_idx ON tmp_prime_all(price)');
        $this->activeTempTable = 'tmp_prime_all';
    }

    private function dropAllDistrictTempTable(): void
    {
        DB::statement('DROP TABLE IF EXISTS tmp_prime_all');
        if ($this->activeTempTable === 'tmp_prime_all') {
            $this->activeTempTable = null;
        }
    }

    private function hasTempTable(string $tableName): bool
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return false;
        }

        $table = DB::selectOne("SELECT to_regclass('pg_temp.{$tableName}') as t");

        return ! empty($table?->t);
    }

    private function salesRollingMonthlyQuery(Builder $baseQuery, \Illuminate\Support\Collection $endMonths): Builder
    {
        $monthly = DB::query()
            ->fromSub($this->normalizedBaseRowsQuery($baseQuery), 'rows')
            ->selectRaw("date_trunc('month', rows.date)::date as month, COUNT(*) as sales")
            ->groupBy('month');

        return DB::query()
            ->fromSub($this->monthSeriesQuery($baseQuery, $endMonths), 'months')
            ->leftJoinSub($monthly, 'monthly', 'months.month', '=', 'monthly.month')
            ->selectRaw(
                'months.month, '.
                'SUM(COALESCE(monthly.sales, 0)) OVER (ORDER BY months.month ROWS BETWEEN 11 PRECEDING AND CURRENT ROW) as rolling_sales'
            );
    }

    private function propertyTypeRollingMonthlyQuery(Builder $baseQuery, \Illuminate\Support\Collection $endMonths): Builder
    {
        $baseRows = $this->normalizedBaseRowsQuery($baseQuery);

        $types = DB::query()
            ->fromSub($baseRows, 'rows')
            ->selectRaw('DISTINCT rows.property_type as type')
            ->whereNotNull('rows.property_type');

        $monthly = DB::query()
            ->fromSub($baseRows, 'rows')
            ->selectRaw("date_trunc('month', rows.date)::date as month, rows.property_type as type, COUNT(*) as monthly_count")
            ->whereNotNull('rows.property_type')
            ->groupBy('month', 'rows.property_type');

        return DB::query()
            ->fromSub($this->monthSeriesQuery($baseQuery, $endMonths), 'months')
            ->crossJoinSub($types, 'types')
            ->leftJoinSub($monthly, 'monthly', function ($join) {
                $join->on('months.month', '=', 'monthly.month')
                    ->on('types.type', '=', 'monthly.type');
            })
            ->selectRaw(
                'months.month, types.type, '.
                'SUM(COALESCE(monthly.monthly_count, 0)) OVER (PARTITION BY types.type ORDER BY months.month ROWS BETWEEN 11 PRECEDING AND CURRENT ROW) as rolling_count'
            );
    }

    private function newBuildRollingMonthlyQuery(Builder $baseQuery, \Illuminate\Support\Collection $endMonths): Builder
    {
        $monthly = DB::query()
            ->fromSub($this->normalizedBaseRowsQuery($baseQuery), 'rows')
            ->selectRaw(
                "date_trunc('month', rows.date)::date as month, ".
                "SUM(CASE WHEN rows.new_build = 'Y' THEN 1 ELSE 0 END) as new_count, ".
                "SUM(CASE WHEN rows.new_build = 'N' THEN 1 ELSE 0 END) as existing_count, ".
                "COUNT(*) FILTER (WHERE rows.new_build IN ('Y','N') AND rows.price IS NOT NULL AND rows.price > 0) as total_count"
            )
            ->whereNotNull('rows.new_build')
            ->whereIn('rows.new_build', ['Y', 'N'])
            ->whereNotNull('rows.price')
            ->where('rows.price', '>', 0)
            ->groupBy('month');

        $rolling = DB::query()
            ->fromSub($this->monthSeriesQuery($baseQuery, $endMonths), 'months')
            ->leftJoinSub($monthly, 'monthly', 'months.month', '=', 'monthly.month')
            ->selectRaw(
                'months.month, '.
                'SUM(COALESCE(monthly.new_count, 0)) OVER (ORDER BY months.month ROWS BETWEEN 11 PRECEDING AND CURRENT ROW) as rolling_new_count, '.
                'SUM(COALESCE(monthly.existing_count, 0)) OVER (ORDER BY months.month ROWS BETWEEN 11 PRECEDING AND CURRENT ROW) as rolling_existing_count, '.
                'SUM(COALESCE(monthly.total_count, 0)) OVER (ORDER BY months.month ROWS BETWEEN 11 PRECEDING AND CURRENT ROW) as rolling_total_count'
            );

        return DB::query()
            ->fromSub($rolling, 'rolling')
            ->selectRaw(
                'month, '.
                'CASE WHEN rolling_total_count > 0 THEN ROUND(100.0 * rolling_new_count / rolling_total_count, 1) ELSE NULL END as new_pct, '.
                'CASE WHEN rolling_total_count > 0 THEN ROUND(100.0 * rolling_existing_count / rolling_total_count, 1) ELSE NULL END as existing_pct'
            );
    }

    private function tenureRollingMonthlyQuery(Builder $baseQuery, \Illuminate\Support\Collection $endMonths): Builder
    {
        $monthly = DB::query()
            ->fromSub($this->normalizedBaseRowsQuery($baseQuery), 'rows')
            ->selectRaw(
                "date_trunc('month', rows.date)::date as month, ".
                "SUM(CASE WHEN rows.duration = 'F' THEN 1 ELSE 0 END) as free_count, ".
                "SUM(CASE WHEN rows.duration = 'L' THEN 1 ELSE 0 END) as lease_count, ".
                "COUNT(*) FILTER (WHERE rows.duration IN ('F','L') AND rows.price IS NOT NULL AND rows.price > 0) as total_count"
            )
            ->whereNotNull('rows.duration')
            ->whereIn('rows.duration', ['F', 'L'])
            ->whereNotNull('rows.price')
            ->where('rows.price', '>', 0)
            ->groupBy('month');

        $rolling = DB::query()
            ->fromSub($this->monthSeriesQuery($baseQuery, $endMonths), 'months')
            ->leftJoinSub($monthly, 'monthly', 'months.month', '=', 'monthly.month')
            ->selectRaw(
                'months.month, '.
                'SUM(COALESCE(monthly.free_count, 0)) OVER (ORDER BY months.month ROWS BETWEEN 11 PRECEDING AND CURRENT ROW) as rolling_free_count, '.
                'SUM(COALESCE(monthly.lease_count, 0)) OVER (ORDER BY months.month ROWS BETWEEN 11 PRECEDING AND CURRENT ROW) as rolling_lease_count, '.
                'SUM(COALESCE(monthly.total_count, 0)) OVER (ORDER BY months.month ROWS BETWEEN 11 PRECEDING AND CURRENT ROW) as rolling_total_count'
            );

        return DB::query()
            ->fromSub($rolling, 'rolling')
            ->selectRaw(
                'month, '.
                'CASE WHEN rolling_total_count > 0 THEN ROUND(100.0 * rolling_free_count / rolling_total_count, 1) ELSE NULL END as free_pct, '.
                'CASE WHEN rolling_total_count > 0 THEN ROUND(100.0 * rolling_lease_count / rolling_total_count, 1) ELSE NULL END as lease_pct'
            );
    }

    private function monthSeriesQuery(Builder $baseQuery, \Illuminate\Support\Collection $endMonths): Builder
    {
        $earliestDate = (clone $baseQuery)->min('Date');
        $lastMonth = $endMonths->last();

        if ($earliestDate === null || $lastMonth === null) {
            return DB::query()
                ->selectRaw('NULL::date as month')
                ->whereRaw('1 = 0');
        }

        $startMonth = \Carbon\Carbon::parse($earliestDate)->startOfMonth()->toDateString();
        $endMonth = $lastMonth->copy()->startOfMonth()->toDateString();

        return DB::query()
            ->selectRaw("generate_series(?::date, ?::date, interval '1 month')::date as month", [
                $startMonth,
                $endMonth,
            ]);
    }

    private function endMonthKeys(\Illuminate\Support\Collection $endMonths): array
    {
        return $endMonths
            ->map(fn ($endMonth) => $endMonth->copy()->startOfMonth()->toDateString())
            ->values()
            ->all();
    }

    private function rangesQuery(\Illuminate\Support\Collection $endMonths): Builder
    {
        $ranges = $endMonths->map(fn ($endMonth) => $this->rollingRangeForEndMonth($endMonth))->values();
        $query = null;

        foreach ($ranges as $range) {
            $select = DB::query()->selectRaw(
                '? as year, ?::timestamp as start_date, ?::timestamp as end_date',
                [
                    $range['year'],
                    $range['start']->toDateTimeString(),
                    $range['end']->toDateTimeString(),
                ]
            );

            $query = $query === null ? $select : $query->unionAll($select);
        }

        return $query ?? DB::query()->selectRaw('NULL::int as year, NULL::timestamp as start_date, NULL::timestamp as end_date')->whereRaw('1 = 0');
    }

    private function baseAllDistrictsQuery(): Builder
    {
        return DB::table('land_registry')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('prime_postcodes as pp')
                    ->where('pp.category', self::CATEGORY)
                    ->whereRaw($this->normalizedPostcodeExpression('land_registry')." LIKE (pp.postcode || '%')");
            })
            ->where('PPDCategoryType', 'A');
    }

    private function baseDistrictQuery(string $district): Builder
    {
        return DB::table('land_registry')
            ->whereRaw($this->normalizedPostcodeExpression()." LIKE (? || '%')", [$district])
            ->where('PPDCategoryType', 'A');
    }

    private function medianPriceExpression(string $columnExpression): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return "PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY {$columnExpression})";
        }

        return "AVG({$columnExpression})";
    }

    private function quotedColumn(string $column): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return '"'.$column.'"';
        }

        return $column;
    }

    private function normalizedPostcodeExpression(?string $table = null): string
    {
        $column = $this->quotedColumn('Postcode');

        if ($table !== null) {
            $column = $table.'.'.$column;
        }

        return 'REPLACE('.$column.", ' ', '')";
    }
}
