<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

class PropertyHomeWarm extends Command
{
    /**
     * Keep the same signature so your existing schedule still works.
     */
    protected $signature = 'property:home-warm {--parallel=1} {--task=}';

    protected $description = 'Warm the PropertyController homepage cache (England & Wales, Category A aggregates only)';

    public function handle(): int
    {
        $this->info('Starting PropertyController home cache warm (EW Cat A only)...');

        // Reduce memory usage during large aggregations
        DB::connection()->disableQueryLog();

        // TTL in seconds (45 days)
        $ttl = 60 * 60 * 24 * 45;

        // If a specific task is provided, run only that (child mode)
        $task = (string) ($this->option('task') ?? '');
        if ($task !== '') {
            $this->runTask($task, $ttl);
            Cache::put('property:home:catA:last_warm', now()->toIso8601String(), $ttl);
            $this->info("Task '{$task}' complete.");

            return self::SUCCESS;
        }

        // Orchestrator mode: define the seven independent tasks
        $tasks = ['sales', 'avgPrice', 'p90', 'top5', 'topSale', 'top3', 'monthly24', 'typeSplit', 'newBuildSplit', 'durationSplit', 'avgPriceByType'];

        $parallel = max(1, (int) ($this->option('parallel') ?? 1));
        if ($parallel <= 1) {
            // Sequential behaviour (original)
            $this->withProgressBar($tasks, function (string $t) use ($ttl) {
                $this->runTask($t, $ttl);
            });
            $this->newLine(2);
            Cache::put('property:home:catA:last_warm', now()->toIso8601String(), $ttl);
            $this->info('PropertyController home cache warm complete (EW Cat A only).');

            return self::SUCCESS;
        }

        // Parallel: spawn up to N child processes, each running a single task
        $this->info("Running in parallel with up to {$parallel} workers...");

        $total = count($tasks);
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $maxWorkers = (int) min($parallel, 11); // safety cap (we have 11 tasks)
        $queue = $tasks; // array of strings
        $running = [];
        $failedTasks = [];

        $startWorker = function (string $t) use (&$running) {
            $php = PHP_BINARY;
            $artisan = base_path('artisan');
            $proc = new Process([$php, $artisan, 'property:home-warm', '--task='.$t]);
            $proc->setTimeout(null);
            $proc->disableOutput();
            $proc->start();
            $running[$t] = $proc;
        };

        // Prime the pool
        while (! empty($queue) && count($running) < $maxWorkers) {
            $startWorker(array_shift($queue));
        }

        // Event loop
        while (! empty($running)) {
            foreach ($running as $t => $proc) {
                if (! $proc->isRunning()) {
                    if ($proc->getExitCode() !== 0) {
                        $this->warn("Worker failed for task {$t} (exit code: {$proc->getExitCode()})");
                        $failedTasks[] = $t;
                    }
                    unset($running[$t]);
                    $bar->advance();
                    if (! empty($queue)) {
                        $startWorker(array_shift($queue));
                    }
                }
            }
            usleep(100000); // 100ms
        }

        $bar->finish();
        $this->newLine(2);

        if (! empty($failedTasks)) {
            $this->error('One or more warm tasks failed: '.implode(', ', $failedTasks));

            return self::FAILURE;
        }

        Cache::put('property:home:catA:last_warm', now()->toIso8601String(), $ttl);
        $this->info('PropertyController home cache warm complete (EW Cat A only).');

        return self::SUCCESS;
    }

    private function runTask(string $task, int $ttl): void
    {
        $window = $this->rollingWindow();
        $cachePrefix = $this->rollingCachePrefix($window['latest_month']);
        $endMonths = $this->rollingEndMonths($window['latest_month']);

        if ($task !== 'monthly24') {
            Cache::put("{$cachePrefix}:meta", $this->serializeRollingWindow($window), $ttl);
        }

        switch ($task) {
            case 'sales':
                $data = $this->buildRollingSalesSeries($endMonths);
                Cache::put("{$cachePrefix}:sales", $this->rollingPayload($window, $data), $ttl);
                break;

            case 'avgPrice':
                $data = $this->buildRollingMedianSeries($endMonths);
                Cache::put("{$cachePrefix}:avgPrice", $this->rollingPayload($window, $data), $ttl);
                break;

            case 'p90':
                $data = $this->buildRollingP90Series($endMonths);
                Cache::put("{$cachePrefix}:p90", $this->rollingPayload($window, $data), $ttl);
                break;

            case 'top5':
                $data = $this->buildRollingTop5Series($endMonths);
                Cache::put("{$cachePrefix}:top5", $this->rollingPayload($window, $data), $ttl);
                break;

            case 'topSale':
                $data = $this->buildRollingTopSaleSeries($endMonths);
                Cache::put("{$cachePrefix}:topSale", $this->rollingPayload($window, $data), $ttl);
                break;

            case 'top3':
                $data = $this->buildRollingTop3Series($endMonths);
                Cache::put("{$cachePrefix}:top3", $this->rollingPayload($window, $data), $ttl);
                break;

            case 'monthly24':
                // Monthly sales — last 24 months (England & Wales, Cat A)
                // Build a slightly wider seed window, then trim to last available month and take 24 months
                $seedMonths = 36;
                $seedStart = now()->startOfMonth()->subMonths($seedMonths - 1);
                $seedEnd = now()->startOfMonth();

                $raw = DB::table('land_registry')
                    ->selectRaw($this->monthStartExpression().' as month_start, COUNT(*) as sales')
                    ->where('PPDCategoryType', 'A')
                    ->whereDate('Date', '>=', $seedStart)
                    ->groupBy('month_start')
                    ->orderBy('month_start')
                    ->pluck('sales', 'month_start')
                    ->toArray();

                // Determine last month with data
                $keys = array_keys($raw);
                if (! empty($keys)) {
                    sort($keys); // ascending
                    $lastDataKey = end($keys); // e.g., '2025-08-01'
                    $seriesEnd = \Carbon\Carbon::createFromFormat('Y-m-d', $lastDataKey)->startOfMonth();
                } else {
                    // If nothing in window, use end of previous month
                    $seriesEnd = $seedEnd->copy()->subMonth();
                }

                // Build exactly 24 months ending at last available month
                $start = $seriesEnd->copy()->subMonths(23)->startOfMonth();

                $labels = [];
                $data = [];
                $cursor = $start->copy();
                while ($cursor->lte($seriesEnd)) {
                    $key = $cursor->format('Y-m-01');
                    $labels[] = $cursor->format('M Y');  // matches controller (formatted to MM/YY in ticks)
                    $data[] = (int) ($raw[$key] ?? 0);
                    $cursor->addMonth();
                }

                // Store combined payload to match controller Cache::remember() contract
                Cache::put('dashboard:sales_last_24m:EW:catA:v2', [$labels, $data], $ttl);
                break;

            case 'typeSplit':
                $data = $this->buildRollingTypeSplitSeries($endMonths);
                Cache::put("{$cachePrefix}:typeSplit", $this->rollingPayload($window, $data), $ttl);
                break;

            case 'newBuildSplit':
                $data = $this->buildRollingNewBuildSplitSeries($endMonths);
                Cache::put("{$cachePrefix}:newBuildSplit", $this->rollingPayload($window, $data), $ttl);
                break;

            case 'durationSplit':
                $data = $this->buildRollingDurationSplitSeries($endMonths);
                Cache::put("{$cachePrefix}:durationSplit", $this->rollingPayload($window, $data), $ttl);
                break;

            case 'avgPriceByType':
                $data = $this->buildRollingAvgPriceByTypeSeries($endMonths);
                Cache::put("{$cachePrefix}:avgPriceByType", $this->rollingPayload($window, $data), $ttl);
                break;

            default:
                $this->warn("Unknown task '{$task}', skipping.");
        }
    }

    private function yearExpression(): string
    {
        if (Schema::hasColumn('land_registry', 'YearDate')) {
            return '"YearDate"';
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return 'CAST(strftime(\'%Y\', "Date") AS INTEGER)';
        }

        return 'EXTRACT(YEAR FROM "Date")::int';
    }

    /**
     * @return array{
     *     latest_month: Carbon,
     *     rolling_start: Carbon,
     *     rolling_end: Carbon,
     *     previous_start: Carbon,
     *     previous_end: Carbon
     * }
     */
    private function rollingWindow(): array
    {
        $latestDate = DB::table('land_registry')->max('Date');
        $latestMonth = $latestDate
            ? Carbon::parse($latestDate)->startOfMonth()
            : now()->startOfMonth();

        $rollingStart = $latestMonth->copy()->subMonths(11)->startOfMonth();
        $rollingEnd = $latestMonth->copy()->endOfMonth();

        return [
            'latest_month' => $latestMonth,
            'rolling_start' => $rollingStart,
            'rolling_end' => $rollingEnd,
            'previous_start' => $rollingStart->copy()->subYear(),
            'previous_end' => $rollingEnd->copy()->subYear(),
        ];
    }

    private function rollingCachePrefix(Carbon $latestMonth): string
    {
        return 'property:home:rolling:'.$latestMonth->format('Ym');
    }

    /**
     * @return \Illuminate\Support\Collection<int, Carbon>
     */
    private function rollingEndMonths(Carbon $latestMonth): \Illuminate\Support\Collection
    {
        $earliestDate = DB::table('land_registry')->min('Date');

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

    /**
     * @return array{year:int,start:Carbon,end:Carbon}
     */
    private function rollingRangeForEndMonth(Carbon $endMonth): array
    {
        return [
            'year' => $endMonth->year,
            'start' => $endMonth->copy()->subMonths(11)->startOfMonth(),
            'end' => $endMonth->copy()->endOfMonth(),
        ];
    }

    private function buildRollingSalesSeries(\Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        return $endMonths->map(function (Carbon $endMonth) {
            $range = $this->rollingRangeForEndMonth($endMonth);

            return (object) [
                'year' => $range['year'],
                'total' => DB::table('land_registry')
                    ->where('PPDCategoryType', 'A')
                    ->whereBetween('Date', [$range['start'], $range['end']])
                    ->count(),
            ];
        });
    }

    private function buildRollingMedianSeries(\Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        $medianExpr = $this->medianPriceExpression();

        return $endMonths->map(function (Carbon $endMonth) use ($medianExpr) {
            $range = $this->rollingRangeForEndMonth($endMonth);
            $avgPrice = DB::table('land_registry')
                ->where('PPDCategoryType', 'A')
                ->whereBetween('Date', [$range['start'], $range['end']])
                ->selectRaw("ROUND({$medianExpr}) as avg_price")
                ->value('avg_price');

            return (object) [
                'year' => $range['year'],
                'avg_price' => $avgPrice !== null ? (int) $avgPrice : null,
            ];
        });
    }

    private function buildRollingP90Series(\Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        return $endMonths->map(function (Carbon $endMonth) {
            $range = $this->rollingRangeForEndMonth($endMonth);
            $sub = DB::table('land_registry')
                ->selectRaw('"Price", CUME_DIST() OVER (ORDER BY "Price") as cd')
                ->where('PPDCategoryType', 'A')
                ->whereBetween('Date', [$range['start'], $range['end']])
                ->whereNotNull('Price')
                ->where('Price', '>', 0);

            $p90Price = DB::query()->fromSub($sub, 't')
                ->where('cd', '>=', 0.9)
                ->min('Price');

            return (object) [
                'year' => $range['year'],
                'p90_price' => $p90Price !== null ? (int) $p90Price : null,
            ];
        });
    }

    private function buildRollingTop5Series(\Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        return $endMonths->map(function (Carbon $endMonth) {
            $range = $this->rollingRangeForEndMonth($endMonth);
            $sub = DB::table('land_registry')
                ->selectRaw('"Price", ROW_NUMBER() OVER (ORDER BY "Price" DESC) as rn, COUNT(*) OVER () as cnt')
                ->where('PPDCategoryType', 'A')
                ->whereBetween('Date', [$range['start'], $range['end']])
                ->whereNotNull('Price')
                ->where('Price', '>', 0);

            $top5Average = DB::query()->fromSub($sub, 'r')
                ->selectRaw('ROUND(AVG("Price")) as top5_avg')
                ->whereColumn('rn', '<=', DB::raw('CEIL(0.05 * cnt)'))
                ->value('top5_avg');

            return (object) [
                'year' => $range['year'],
                'top5_avg' => $top5Average !== null ? (int) $top5Average : null,
            ];
        });
    }

    private function buildRollingTopSaleSeries(\Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        return $endMonths->map(function (Carbon $endMonth) {
            $range = $this->rollingRangeForEndMonth($endMonth);

            return (object) [
                'year' => $range['year'],
                'top_sale' => DB::table('land_registry')
                    ->where('PPDCategoryType', 'A')
                    ->whereBetween('Date', [$range['start'], $range['end']])
                    ->whereNotNull('Price')
                    ->where('Price', '>', 0)
                    ->max('Price'),
            ];
        });
    }

    private function buildRollingTop3Series(\Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        return $endMonths->flatMap(function (Carbon $endMonth) {
            $range = $this->rollingRangeForEndMonth($endMonth);
            $rankedTop3 = DB::table('land_registry')
                ->selectRaw('"Date", "Postcode", "Price", ROW_NUMBER() OVER (ORDER BY "Price" DESC) as rn')
                ->where('PPDCategoryType', 'A')
                ->whereBetween('Date', [$range['start'], $range['end']])
                ->whereNotNull('Price')
                ->where('Price', '>', 0);

            return DB::query()
                ->fromSub($rankedTop3, 'r')
                ->select('Date', 'Postcode', 'Price', 'rn')
                ->where('rn', '<=', 3)
                ->orderBy('rn')
                ->get()
                ->map(fn ($row) => (object) [
                    'year' => $range['year'],
                    'Date' => $row->Date,
                    'Postcode' => $row->Postcode,
                    'Price' => $row->Price,
                    'rn' => $row->rn,
                ]);
        })->values();
    }

    private function buildRollingTypeSplitSeries(\Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        return $this->buildRollingGroupedCountSeries($endMonths, 'PropertyType', 'type', ['D', 'S', 'T', 'F']);
    }

    private function buildRollingNewBuildSplitSeries(\Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        return $this->buildRollingGroupedCountSeries($endMonths, 'NewBuild', 'nb', ['Y', 'N']);
    }

    private function buildRollingDurationSplitSeries(\Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        return $this->buildRollingGroupedCountSeries($endMonths, 'Duration', 'dur', ['F', 'L']);
    }

    private function buildRollingAvgPriceByTypeSeries(\Illuminate\Support\Collection $endMonths): \Illuminate\Support\Collection
    {
        $medianExpr = $this->medianPriceExpression();

        return $endMonths->flatMap(function (Carbon $endMonth) use ($medianExpr) {
            $range = $this->rollingRangeForEndMonth($endMonth);

            return DB::table('land_registry')
                ->selectRaw("\"PropertyType\" as type, ROUND({$medianExpr}) as avg_price")
                ->where('PPDCategoryType', 'A')
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

    private function buildRollingGroupedCountSeries(
        \Illuminate\Support\Collection $endMonths,
        string $column,
        string $alias,
        array $allowedValues
    ): \Illuminate\Support\Collection {
        return $endMonths->flatMap(function (Carbon $endMonth) use ($column, $alias, $allowedValues) {
            $range = $this->rollingRangeForEndMonth($endMonth);

            return DB::table('land_registry')
                ->selectRaw("\"{$column}\" as {$alias}, COUNT(*) as total")
                ->where('PPDCategoryType', 'A')
                ->whereBetween('Date', [$range['start'], $range['end']])
                ->whereIn($column, $allowedValues)
                ->groupBy($column)
                ->get()
                ->map(fn ($row) => (object) [
                    'year' => $range['year'],
                    $alias => $row->{$alias},
                    'total' => (int) $row->total,
                ]);
        })->values();
    }

    /**
     * @param  array{
     *     latest_month: Carbon,
     *     rolling_start: Carbon,
     *     rolling_end: Carbon,
     *     previous_start: Carbon,
     *     previous_end: Carbon
     * }  $window
     */
    private function serializeRollingWindow(array $window): array
    {
        return [
            'latest_month' => $window['latest_month']->toDateString(),
            'rolling_start' => $window['rolling_start']->toDateString(),
            'rolling_end' => $window['rolling_end']->toDateString(),
            'previous_start' => $window['previous_start']->toDateString(),
            'previous_end' => $window['previous_end']->toDateString(),
        ];
    }

    /**
     * @param  array{
     *     latest_month: Carbon,
     *     rolling_start: Carbon,
     *     rolling_end: Carbon,
     *     previous_start: Carbon,
     *     previous_end: Carbon
     * }  $window
     */
    private function rollingPayload(array $window, mixed $data): array
    {
        return [
            ...$this->serializeRollingWindow($window),
            'data' => $data,
        ];
    }

    private function monthStartExpression(): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return "strftime('%Y-%m-01', \"Date\")";
        }

        return "TO_CHAR(DATE_TRUNC('month', \"Date\"), 'YYYY-MM-01')";
    }

    private function medianPriceExpression(): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            return 'PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY "Price")';
        }

        return 'AVG("Price")';
    }
}
