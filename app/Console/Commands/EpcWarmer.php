<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EpcWarmer extends Command
{
    protected $signature = 'epc:warm-dashboard';

    protected $description = 'Precompute and cache EPC dashboard queries for faster page loads';

    public function handle(): int
    {
        $this->info('Warming EPC dashboard cache...');
        $started = microtime(true);
        $driver = DB::connection()->getDriverName();
        DB::connection()->disableQueryLog();

        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $today = Carbon::today();
        $ttl = now()->addDays(45);
        $ratings = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

        $nations = [
            'ew' => [
                'table' => 'epc_certificates',
                'dateExpr' => $this->ewDateExpr($driver),
                'yearExpr' => $this->ewYearExpr($driver),
                'currentCol' => 'CURRENT_ENERGY_RATING',
                'potentialCol' => 'POTENTIAL_ENERGY_RATING',
                'tenureCol' => 'TENURE',
                'since' => Carbon::create(2008, 1, 1),
            ],
            'scotland' => [
                'table' => 'epc_certificates_scotland',
                'dateExpr' => $this->scotlandDateExpr($driver),
                'yearExpr' => $this->scotlandYearExpr($driver),
                'currentCol' => 'CURRENT_ENERGY_RATING',
                'potentialCol' => 'POTENTIAL_ENERGY_RATING',
                'tenureCol' => 'TENURE',
                'since' => Carbon::create(2015, 1, 1),
            ],
        ];

        $cacheKey = fn (string $nation, string $key): string => "epc:{$nation}:{$key}";

        foreach ($nations as $nation => $cfg) {
            $this->line("-> Warming {$nation} ({$cfg['table']})...");

            $maxDate = DB::table($cfg['table'])
                ->selectRaw("MAX({$cfg['dateExpr']}) as d")
                ->value('d');

            $last30FromLatest = $maxDate ? Carbon::parse($maxDate)->copy()->subDays(30) : $today->copy()->subDays(30);

            $last30Count = $maxDate
                ? (int) DB::table($cfg['table'])
                    ->whereBetween(DB::raw($cfg['dateExpr']), [$last30FromLatest, $maxDate])
                    ->count()
                : 0;

            $last365Count = (int) DB::table($cfg['table'])
                ->whereBetween(DB::raw($cfg['dateExpr']), [$today->copy()->subDays(365), $today])
                ->count();

            Cache::put($cacheKey($nation, 'stats'), [
                'total' => (int) DB::table($cfg['table'])->count(),
                'latest_lodgement' => $maxDate,
                'last30_count' => $last30Count,
                'last365_count' => $last365Count,
            ], $ttl);

            $byYear = DB::table($cfg['table'])
                ->selectRaw("{$cfg['yearExpr']} as yr, COUNT(*) as cnt")
                ->whereRaw("{$cfg['dateExpr']} IS NOT NULL")
                ->whereRaw("{$cfg['dateExpr']} >= ?", [$cfg['since']])
                ->groupBy('yr')
                ->orderBy('yr')
                ->get();
            Cache::put($cacheKey($nation, 'byYear'), $byYear, $ttl);

            $ratingByYear = DB::table($cfg['table'])
                ->selectRaw("{$cfg['yearExpr']} as yr, {$this->column($cfg['currentCol'])} as rating, COUNT(*) as cnt")
                ->whereRaw("{$cfg['dateExpr']} IS NOT NULL")
                ->whereRaw("{$cfg['dateExpr']} >= ?", [$cfg['since']])
                ->whereIn($cfg['currentCol'], $ratings)
                ->groupBy('yr', 'rating')
                ->orderBy('yr')
                ->get();
            Cache::put($cacheKey($nation, 'ratingByYear'), $ratingByYear, $ttl);

            $potentialByYear = DB::table($cfg['table'])
                ->selectRaw("{$cfg['yearExpr']} as yr, {$this->column($cfg['potentialCol'])} as rating, COUNT(*) as cnt")
                ->whereRaw("{$cfg['dateExpr']} IS NOT NULL")
                ->whereRaw("{$cfg['dateExpr']} >= ?", [$cfg['since']])
                ->whereIn($cfg['potentialCol'], $ratings)
                ->groupBy('yr', 'rating')
                ->orderBy('yr')
                ->get();
            Cache::put($cacheKey($nation, 'potentialByYear'), $potentialByYear, $ttl);

            $tenureByYear = DB::table($cfg['table'])
                ->selectRaw("\n                    {$cfg['yearExpr']} as yr,\n                    CASE\n                        WHEN {$this->column($cfg['tenureCol'])} IN ('Owner-occupied','owner-occupied') THEN 'Owner-occupied'\n                        WHEN {$this->column($cfg['tenureCol'])} IN ('Rented (private)','rental (private)') THEN 'Rented (private)'\n                        WHEN {$this->column($cfg['tenureCol'])} IN ('Rented (social)','rental (social)') THEN 'Rented (social)'\n                        ELSE NULL\n                    END as tenure,\n                    COUNT(*) as cnt\n                ")
                ->whereRaw("{$cfg['dateExpr']} IS NOT NULL")
                ->whereRaw("{$cfg['dateExpr']} >= ?", [$cfg['since']])
                ->whereIn($cfg['tenureCol'], [
                    'Owner-occupied', 'owner-occupied',
                    'Rented (private)', 'rental (private)',
                    'Rented (social)', 'rental (social)',
                ])
                ->groupBy('yr', 'tenure')
                ->orderBy('yr')
                ->get();
            Cache::put($cacheKey($nation, 'tenureByYear'), $tenureByYear, $ttl);

            $ratingDist = DB::table($cfg['table'])
                ->selectRaw("\n                    CASE\n                        WHEN {$this->column($cfg['currentCol'])} IN ('A','B','C','D','E','F','G') THEN {$this->column($cfg['currentCol'])}\n                        WHEN {$this->column($cfg['currentCol'])} IS NULL THEN 'Unknown'\n                        ELSE 'Other'\n                    END as rating,\n                    COUNT(*) as cnt\n                ")
                ->groupBy('rating')
                ->get();
            Cache::put($cacheKey($nation, 'ratingDist'), $ratingDist, $ttl);

            Cache::put($cacheKey($nation, 'last_warm'), now()->toIso8601String(), $ttl);
        }

        Cache::put('epc:last_warm', now()->toIso8601String(), $ttl);

        $elapsed = round((microtime(true) - $started), 2);
        $this->info("Done in {$elapsed}s");

        return self::SUCCESS;
    }

    private function ewYearExpr(string $driver): string
    {
        if ($driver === 'pgsql') {
            return 'EXTRACT(YEAR FROM CAST("LODGEMENT_DATE" AS date))::int';
        }

        return 'CAST(strftime(\'%Y\', "LODGEMENT_DATE") AS INTEGER)';
    }

    private function ewDateExpr(string $driver): string
    {
        if ($driver === 'pgsql') {
            return 'CAST("LODGEMENT_DATE" AS date)';
        }

        return 'date("LODGEMENT_DATE")';
    }

    private function scotlandDateExpr(string $driver): string
    {
        if ($driver === 'pgsql') {
            return 'CAST("LODGEMENT_DATE" AS date)';
        }

        return 'date("LODGEMENT_DATE")';
    }

    private function scotlandYearExpr(string $driver): string
    {
        if ($driver === 'pgsql') {
            return 'EXTRACT(YEAR FROM CAST("LODGEMENT_DATE" AS date))::int';
        }

        return 'CAST(strftime(\'%Y\', "LODGEMENT_DATE") AS INTEGER)';
    }

    private function ratingOrderExpression(string $column, bool $includeTail = false): string
    {
        $case = "CASE {$column}\n            WHEN 'A' THEN 1\n            WHEN 'B' THEN 2\n            WHEN 'C' THEN 3\n            WHEN 'D' THEN 4\n            WHEN 'E' THEN 5\n            WHEN 'F' THEN 6\n            WHEN 'G' THEN 7";

        if ($includeTail) {
            $case .= "\n            WHEN 'Other' THEN 8\n            WHEN 'Unknown' THEN 9";
        }

        return $case."\n            ELSE 99\n        END";
    }

    private function tenureOrderExpression(string $column): string
    {
        return "CASE {$column}\n            WHEN 'Owner-occupied' THEN 1\n            WHEN 'Rented (private)' THEN 2\n            WHEN 'Rented (social)' THEN 3\n            ELSE 99\n        END";
    }

    private function column(string $name): string
    {
        return '"'.$name.'"';
    }
}
