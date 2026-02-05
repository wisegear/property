<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class PclWarm extends Command
{
    protected $signature = 'pcl:warm {--district=} {--parallel=1}';

    protected $description = 'Warm the Prime Central London cache (Category A only)';

    public function handle(): int
    {
        $districtOption = strtoupper(trim((string) ($this->option('district') ?? '')));
        $parallel = max(1, (int) ($this->option('parallel') ?? 1));

        DB::connection()->disableQueryLog();

        $ttl = 60 * 60 * 24 * 45;

        if ($districtOption !== '') {
            $this->warmDistrict($districtOption, $ttl);

            return self::SUCCESS;
        }

        $this->info('Starting Prime Central cache warm...');

        $districts = DB::table('prime_postcodes')
            ->where('category', 'Prime Central')
            ->pluck('postcode')
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($districts)) {
            $this->warn('No Prime Central districts found.');

            return self::SUCCESS;
        }

        if ($parallel <= 1) {
            $this->withProgressBar($districts, function (string $district) use ($ttl) {
                $this->warmDistrict($district, $ttl);
            });
        } else {
            $this->info("Running in parallel with up to {$parallel} workers...");

            $bar = $this->output->createProgressBar(count($districts));
            $bar->start();

            $maxWorkers = (int) min($parallel, 8);
            $queue = $districts;
            $running = [];
            $failedDistricts = [];

            $startWorker = function (string $district) use (&$running): void {
                $proc = new Process([PHP_BINARY, base_path('artisan'), 'pcl:warm', '--district='.$district]);
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
                $this->error('Prime Central warm failed for: '.implode(', ', $failedDistricts));

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('Warming ALL Prime Central (aggregate)');

        $allSeries = $this->buildSeries($districts);
        foreach ($allSeries as $name => $value) {
            Cache::put('pcl:v3:catA:ALL:'.$name, $value, $ttl);
        }

        Cache::put('pcl:v3:catA:last_warm', now()->toIso8601String(), $ttl);

        $this->newLine(2);
        $this->info('Prime Central cache warm complete.');

        return self::SUCCESS;
    }

    private function warmDistrict(string $district, int $ttl): void
    {
        $series = $this->buildSeries([$district]);

        foreach ($series as $name => $value) {
            Cache::put('pcl:v3:catA:'.$district.':'.$name, $value, $ttl);
        }
    }

    private function buildSeries(array $districts): array
    {
        $yearExpr = $this->yearExpression();
        $base = $this->baseQueryForDistricts($districts);

        $avgPrice = (clone $base)
            ->selectRaw("{$yearExpr} as year, ROUND(AVG(".$this->column('Price').')) as avg_price')
            ->groupByRaw($yearExpr)
            ->orderBy('year')
            ->get();

        $sales = (clone $base)
            ->selectRaw("{$yearExpr} as year, COUNT(*) as sales")
            ->groupByRaw($yearExpr)
            ->orderBy('year')
            ->get();

        $propertyTypes = (clone $base)
            ->selectRaw("{$yearExpr} as year, ".$this->column('PropertyType').' as type, COUNT(*) as count')
            ->groupByRaw($yearExpr.', '.$this->column('PropertyType'))
            ->orderBy('year')
            ->get();

        $avgPriceByType = (clone $base)
            ->selectRaw("{$yearExpr} as year, SUBSTR(".$this->column('PropertyType').', 1, 1) as type, ROUND(AVG('.$this->column('Price').')) as avg_price')
            ->whereNotNull('PropertyType')
            ->whereNotNull('Price')
            ->where('Price', '>', 0)
            ->whereRaw('SUBSTR('.$this->column('PropertyType').", 1, 1) IN ('D','S','T','F')")
            ->groupByRaw($yearExpr.', type')
            ->orderBy('year')
            ->get();

        $newBuildPct = (clone $base)
            ->selectRaw(
                "{$yearExpr} as year, ".
                'ROUND(100 * SUM(CASE WHEN '.$this->column('NewBuild')." = 'Y' THEN 1 ELSE 0 END) / COUNT(*), 1) as new_pct, ".
                'ROUND(100 * SUM(CASE WHEN '.$this->column('NewBuild')." = 'N' THEN 1 ELSE 0 END) / COUNT(*), 1) as existing_pct"
            )
            ->whereNotNull('NewBuild')
            ->whereIn('NewBuild', ['Y', 'N'])
            ->whereNotNull('Price')
            ->where('Price', '>', 0)
            ->groupByRaw($yearExpr)
            ->orderBy('year')
            ->get();

        $tenurePct = (clone $base)
            ->selectRaw(
                "{$yearExpr} as year, ".
                'ROUND(100 * SUM(CASE WHEN '.$this->column('Duration')." = 'F' THEN 1 ELSE 0 END) / COUNT(*), 1) as free_pct, ".
                'ROUND(100 * SUM(CASE WHEN '.$this->column('Duration')." = 'L' THEN 1 ELSE 0 END) / COUNT(*), 1) as lease_pct"
            )
            ->whereNotNull('Duration')
            ->whereIn('Duration', ['F', 'L'])
            ->whereNotNull('Price')
            ->where('Price', '>', 0)
            ->groupByRaw($yearExpr)
            ->orderBy('year')
            ->get();

        $deciles = (clone $base)
            ->selectRaw("{$yearExpr} as year, ".$this->column('Price').", NTILE(10) OVER (PARTITION BY {$yearExpr} ORDER BY ".$this->column('Price').') as decile')
            ->whereNotNull('Price')
            ->where('Price', '>', 0);

        $p90 = DB::query()
            ->fromSub($deciles, 't')
            ->selectRaw('year, MIN("Price") as p90')
            ->where('decile', 10)
            ->groupBy('year')
            ->orderBy('year')
            ->get();

        $rankedTop5 = (clone $base)
            ->selectRaw("{$yearExpr} as year, ".$this->column('Price').", ROW_NUMBER() OVER (PARTITION BY {$yearExpr} ORDER BY ".$this->column('Price')." DESC) as rn, COUNT(*) OVER (PARTITION BY {$yearExpr}) as cnt")
            ->whereNotNull('Price')
            ->where('Price', '>', 0);

        $top5 = DB::query()
            ->fromSub($rankedTop5, 'ranked')
            ->selectRaw('year, ROUND(AVG("Price")) as top5_avg')
            ->whereRaw('rn <= ((cnt + 19) / 20)')
            ->groupBy('year')
            ->orderBy('year')
            ->get();

        $topSalePerYear = (clone $base)
            ->selectRaw("{$yearExpr} as year, MAX(".$this->column('Price').') as top_sale')
            ->whereNotNull('Price')
            ->where('Price', '>', 0)
            ->groupByRaw($yearExpr)
            ->orderBy('year')
            ->get();

        $rankedTop3 = (clone $base)
            ->selectRaw("{$yearExpr} as year, ".$this->column('Date').' as date, '.$this->column('Postcode').' as postcode, '.$this->column('Price')." as price, ROW_NUMBER() OVER (PARTITION BY {$yearExpr} ORDER BY ".$this->column('Price').' DESC) as rn')
            ->whereNotNull('Price')
            ->where('Price', '>', 0);

        $top3PerYear = DB::query()
            ->fromSub($rankedTop3, 'r')
            ->select('year', 'date', 'postcode', 'price', 'rn')
            ->where('rn', '<=', 3)
            ->orderBy('year')
            ->orderBy('rn')
            ->get();

        return [
            'avgPrice' => $avgPrice,
            'sales' => $sales,
            'propertyTypes' => $propertyTypes,
            'avgPriceByType' => $avgPriceByType,
            'newBuildPct' => $newBuildPct,
            'tenurePct' => $tenurePct,
            'p90' => $p90,
            'top5' => $top5,
            'topSalePerYear' => $topSalePerYear,
            'top3PerYear' => $top3PerYear,
        ];
    }

    private function baseQueryForDistricts(array $districts): Builder
    {
        $outwardExpr = $this->outwardExpr();

        return DB::table('land_registry')
            ->where('PPDCategoryType', 'A')
            ->where(function ($query) use ($districts, $outwardExpr) {
                foreach ($districts as $district) {
                    $query->orWhereRaw("{$outwardExpr} LIKE ?", [$district.'%']);
                }
            });
    }

    private function yearExpression(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return 'CAST(strftime(\'%Y\', "Date") AS INTEGER)';
        }

        return '"YearDate"';
    }

    private function outwardExpr(): string
    {
        return 'REPLACE('.$this->column('Postcode').", ' ', '')";
    }

    private function column(string $name): string
    {
        return '"'.$name.'"';
    }
}
