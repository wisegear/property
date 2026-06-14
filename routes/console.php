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

// Sitemap
Schedule::command('sitemap:generate')->dailyAt('01:10');
Schedule::command('sitemap:generate-epc-postcodes')->dailyAt('01:15');
Schedule::command('sitemap:generate-streets')->dailyAt('01:20');
Schedule::command('swaps:import-boe')->weekdays()->at('13:00');

// Spatie Backups

Schedule::command('backup:clean')->dailyAt('04:00');
Schedule::command('backup:run')->dailyAt('04:10')->withoutOverlapping();
Schedule::command('backup:monitor')->dailyAt('04:20');
