<?php

namespace App\Console\Commands;

use App\Models\AnalyticsVisit;
use App\Services\AnalyticsService;
use Illuminate\Console\Command;

class AnalyticsClassifyBots extends Command
{
    protected $signature = 'analytics:classify-bots';

    protected $description = 'Backfill analytics_visits with bot classifications using user agents and IP ranges.';

    public function __construct(
        private AnalyticsService $analyticsService
    ) {
        parent::__construct();
    }

    /**
     * Validation queries:
     *
     * SELECT country_code, COUNT(*)
     * FROM analytics_visits
     * WHERE is_bot = false
     * GROUP BY country_code
     * ORDER BY COUNT(*) DESC
     * LIMIT 20;
     *
     * SELECT bot_name, COUNT(*)
     * FROM analytics_visits
     * WHERE is_bot = true
     * GROUP BY bot_name
     * ORDER BY COUNT(*) DESC
     * LIMIT 20;
     *
     * SELECT ip_address, COUNT(*)
     * FROM analytics_visits
     * WHERE is_bot = true
     * GROUP BY ip_address
     * ORDER BY COUNT(*) DESC
     * LIMIT 20;
     */
    public function handle(): int
    {
        $scanned = 0;
        $updated = 0;
        $botCount = 0;
        $humanCount = 0;

        $this->info('Classifying analytics visits...');

        AnalyticsVisit::query()
            ->orderBy('id')
            ->chunkById(500, function ($visits) use (&$scanned, &$updated, &$botCount, &$humanCount): void {
                foreach ($visits as $visit) {
                    $scanned++;

                    $botMatch = $this->analyticsService->classifyBot(
                        $visit->user_agent,
                        $visit->ip_address
                    );

                    if ($botMatch['is_bot']) {
                        $botCount++;
                    } else {
                        $humanCount++;
                    }

                    $newIsBot = $botMatch['is_bot'];
                    $newBotName = $botMatch['bot_name'];

                    if ((bool) $visit->is_bot === $newIsBot && $visit->bot_name === $newBotName) {
                        continue;
                    }

                    $visit->forceFill([
                        'is_bot' => $newIsBot,
                        'bot_name' => $newBotName,
                    ])->save();

                    $updated++;
                }
            });

        $this->line("Scanned: {$scanned}");
        $this->line("Updated: {$updated}");
        $this->line("Bot visits: {$botCount}");
        $this->line("Human visits: {$humanCount}");

        return Command::SUCCESS;
    }
}
