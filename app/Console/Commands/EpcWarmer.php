<?php

namespace App\Console\Commands;

use App\Services\EpcDashboardData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EpcWarmer extends Command
{
    protected $signature = 'epc:warm-dashboard';

    protected $description = 'Precompute and cache EPC dashboard queries for faster page loads';

    public function handle(EpcDashboardData $dashboardData): int
    {
        $this->info('Warming EPC dashboard cache...');
        $started = microtime(true);
        $ttl = now()->addDays(45);
        DB::connection()->disableQueryLog();

        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        foreach (['ew', 'scotland'] as $nation) {
            $this->line("-> Warming {$nation}...");

            foreach (['stats', 'byYear', 'ratingByYear', 'potentialByYear', 'tenureByYear', 'ratingDist'] as $dataset) {
                Cache::forget("epc:{$nation}:{$dataset}");
            }

            $dashboardData->forNation($nation);
            Cache::put("epc:{$nation}:last_warm", now()->toIso8601String(), $ttl);
        }

        Cache::put('epc:last_warm', now()->toIso8601String(), $ttl);

        $this->info('Done in '.round(microtime(true) - $started, 2).'s');

        return self::SUCCESS;
    }
}
