<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WarmInsights extends Command
{
    protected $signature = 'insights:warm';

    protected $description = 'Warm cache for insight sector pages';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
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
            $this->info('No insight sectors found to warm.');

            return self::SUCCESS;
        }

        $count = 0;
        $this->output->progressStart($total);

        // Load all insights once instead of querying per sector
        $allInsights = DB::table('market_insights')
            ->orderBy('area_code')
            ->orderByDesc('period_end')
            ->get()
            ->groupBy('area_code');

        foreach ($sectors as $sector) {
            $areaCode = is_object($sector)
                ? (string) $sector->area_code
                : (string) $sector;

            $rows = $allInsights[$areaCode] ?? collect();

            Cache::put("insights:sector:{$areaCode}", $rows, now()->addDays(45));

            $count++;
            $this->output->progressAdvance();
            $this->output->writeln(" {$count}/{$total} {$areaCode}");
        }

        $this->output->progressFinish();
        $this->info("Insight cache warming complete ({$count} sectors)");

        return self::SUCCESS;
    }
}
