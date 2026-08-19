<?php

use App\Http\Controllers\Api\CrimeController;
use App\Http\Controllers\Api\EpcCertificateController;
use App\Http\Controllers\Api\EpcDashboardController;
use App\Http\Controllers\Api\EpcSearchController;
use App\Http\Controllers\Api\HpiDashboardController;
use App\Http\Controllers\Api\MonthlyPropertySnapshotController;
use App\Http\Controllers\Api\PropertyDashboardController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\SchoolPostcodeSearchController;
use App\Http\Controllers\Api\ScottishEpcCertificateController;
use App\Http\Controllers\Api\StressInsightsController;
use App\Http\Controllers\Api\SwapRatesController;
use App\Http\Controllers\PropertyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/insights/crime', [CrimeController::class, 'index'])
        ->name('insights.crime.index');
    Route::get('/insights/crime/{area_slug}', [CrimeController::class, 'show'])
        ->where('area_slug', '[a-z0-9-]+')
        ->name('insights.crime.show');
    Route::get('/insights/stress', StressInsightsController::class)
        ->name('insights.stress');
    Route::get('/insights/swap-rates', SwapRatesController::class)
        ->middleware('cache.headers:public;max_age=3600;s_maxage=86400;stale_while_revalidate=604800;etag')
        ->name('insights.swap-rates');
    Route::get('/hpi/dashboard', HpiDashboardController::class)
        ->name('hpi.dashboard');
    Route::get('/epc/dashboard', EpcDashboardController::class)
        ->name('epc.dashboard');
    Route::get('/epc/search', EpcSearchController::class)
        ->name('epc.search');
    Route::get('/epc/scotland/{reference}', ScottishEpcCertificateController::class)
        ->where('reference', '[A-Za-z0-9-]+')
        ->name('epc.scotland.show');
    Route::get('/epc/{reference}', EpcCertificateController::class)
        ->where('reference', '[A-Za-z0-9-]+')
        ->name('epc.show');
    Route::get('/schools', SchoolPostcodeSearchController::class)
        ->name('schools.index');
    Route::get('/schools/{slug}', SchoolController::class)
        ->where('slug', '[a-z0-9-]+')
        ->name('schools.show');
    Route::get('/properties', [PropertyController::class, 'search'])
        ->name('properties.index');
    Route::get('/property/dashboard', PropertyDashboardController::class)
        ->middleware('cache.headers:public;max_age=3600;s_maxage=86400;stale_while_revalidate=604800;etag')
        ->name('property.dashboard');
    Route::get('/property/monthly-snapshot', MonthlyPropertySnapshotController::class)
        ->middleware('cache.headers:public;max_age=3600;s_maxage=86400;stale_while_revalidate=604800;etag')
        ->name('property.monthly-snapshot');
    Route::get('/properties/{slug}', [PropertyController::class, 'showBySlug'])
        ->where('slug', '[a-z0-9-]+')
        ->name('properties.show');
});
