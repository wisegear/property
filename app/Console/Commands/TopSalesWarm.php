<?php

namespace App\Console\Commands;

use App\Services\TopSalesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TopSalesWarm extends Command
{
    protected $signature = 'property:top-sales-warm';

    protected $description = 'Warm top property sales cache';

    /**
     * Execute the console command.
     */
    public function handle(TopSalesService $topSalesService): int
    {
        $this->info('Warming top property sales...');

        foreach (['ultra', 'london', 'rest'] as $mode) {
            $topSalesService->warmMode($mode);

            $this->info("Warmed {$mode}");
        }

        Cache::put($topSalesService->lastWarmedCacheKey(), now()->toIso8601String(), now()->addDays(45));

        $this->info('Done.');

        return self::SUCCESS;
    }
}
