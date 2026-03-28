<?php

namespace App\Console\Commands;

use App\Http\Controllers\CrimeController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CrimeWarm extends Command
{
    protected $signature = 'crime:warm';

    protected $description = 'Warm cache for the national and regional crime dashboards';

    public function handle(): int
    {
        /** @var CrimeController $controller */
        $controller = app(CrimeController::class);

        $this->info('Warming national crime dashboard cache.');
        $national = $controller->warmNationalCache();
        $areas = $national['areas'] ?? [];

        if ($areas === []) {
            $this->info('No crime areas found to warm.');

            return self::SUCCESS;
        }

        $this->output->progressStart(count($areas));

        foreach ($areas as $area) {
            $slug = (string) ($area['slug'] ?? '');

            if ($slug === '') {
                $this->output->progressAdvance();

                continue;
            }

            $this->line('Warming '.$slug);
            $controller->warmAreaCache($slug);
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        Cache::put('insights:crime:last_warmed_at', now()->toIso8601String(), now()->addDays(45));

        $this->info('Crime dashboard cache warming complete ('.count($areas).' areas)');

        return self::SUCCESS;
    }
}
