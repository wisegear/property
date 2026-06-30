<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

if (! app()->environment('testing')) {
    Artisan::command('test', function () {
        throw new RuntimeException(
            'Tests can only run in testing environment.'
        );
    });
}

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// BoE import command
Schedule::command('swaps:import-boe')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/boe-swap-scheduler.log'));

