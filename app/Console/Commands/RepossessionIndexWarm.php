<?php

namespace App\Console\Commands;

use App\Http\Controllers\RepossessionsController;
use App\Models\RepoLaQuarterly;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RepossessionIndexWarm extends Command
{
    protected $signature = 'repos:warm-index';

    protected $description = 'Warm repossession index page caches';

    public function handle()
    {
        $this->info('Warming repossession index…');

        Cache::forget('repos:index:v2:'.implode('-', RepoLaQuarterly::latestPeriod()));

        // Call the same logic as controller
        app(RepossessionsController::class)
            ->index(request());

        $this->info('Repossession index warmed.');
    }
}
