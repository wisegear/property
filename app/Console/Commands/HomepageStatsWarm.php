<?php

namespace App\Console\Commands;

use App\Services\HomepageDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class HomepageStatsWarm extends Command
{
    protected $signature = 'home:stats-warm';

    protected $description = 'Warm cached stats displayed on the homepage';

    public function handle(HomepageDataService $homepageDataService): int
    {
        $this->info('Warming homepage stats cache...');

        $ttl = now()->addDays(30);
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
