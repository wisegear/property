<?php

namespace App\Console\Commands;

use App\Services\HomepageDataService;
use App\Services\TopSalesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class HomepageStatsWarm extends Command
{
    protected $signature = 'home:stats-warm';

    protected $description = 'Warm cached stats displayed on the homepage';

    public function handle(HomepageDataService $homepageDataService, TopSalesService $topSalesService): int
    {
        $this->info('Warming homepage stats cache...');

        $ttl = now()->addDays(30);

        foreach (['ultra', 'london', 'rest'] as $mode) {
            $topSalesService->warmMode($mode);
        }

        Cache::put($topSalesService->lastWarmedCacheKey(), now()->toIso8601String(), now()->addDays(45));
        $this->line('→ top_sales caches refreshed');

        $stats = $homepageDataService->homepageStats();
        $homepagePanels = $homepageDataService->homepagePanels();

        Cache::put('homepage_stats', $stats, $ttl);
        $this->line('→ homepage_stats cached for 30 days');
        Cache::put('homepage_panels', $homepagePanels, $ttl);
        $this->line('→ homepage_panels cached for 30 days');

        // Record last warm time
        Cache::put('homepage_stats:last_warm', now());

        $this->info('Homepage stats cache warmed successfully.');

        return Command::SUCCESS;
    }
}
