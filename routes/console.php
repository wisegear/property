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

// Spatie Backups

Schedule::command('backup:clean')->dailyAt('04:00');
Schedule::command('backup:run')->dailyAt('04:10')->withoutOverlapping();
Schedule::command('backup:monitor')->dailyAt('04:20');
