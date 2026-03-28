<?php

namespace App\Console\Commands;

use App\Http\Controllers\InsightController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WarmInsights extends Command
{
    protected $signature = 'insights:warm';

    protected $description = 'Warm cache for existing insight sector pages';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var InsightController $controller */
        $controller = app(InsightController::class);

        $sectors = Cache::get('insights:sectors');

        if (! $sectors) {
            $sectors = DB::table('market_insights')
                ->select('area_code')
                ->distinct()
                ->orderBy('area_code')
                ->pluck('area_code');
        }

        $total = $sectors->count();

        if ($total === 0) {
            $this->info('No insight sectors found to warm. Run insights:generate to populate market_insights first.');

            return self::SUCCESS;
        }

        $count = 0;
        $this->info('Warming caches for existing sectors only. This command does not generate market_insights rows.');
        $this->output->progressStart($total);

        foreach ($sectors as $sector) {
            $areaCode = is_object($sector)
                ? (string) $sector->area_code
                : (string) $sector;

            $this->line("Warming {$areaCode}");
            $controller->warmSectorCache($areaCode);

            $count++;
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();
        $this->info("Insight cache warming complete ({$count} sectors)");

        return self::SUCCESS;
    }
}
