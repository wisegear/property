<?php

use App\Http\Controllers\Api\EpcCertificateController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\PropertyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/epc/{reference}', EpcCertificateController::class)
        ->where('reference', '[A-Za-z0-9-]+')
        ->name('epc.show');
    Route::get('/schools/{slug}', SchoolController::class)
        ->where('slug', '[a-z0-9-]+')
        ->name('schools.show');
    Route::get('/properties', [PropertyController::class, 'search'])
        ->name('properties.index');
    Route::get('/properties/{slug}', [PropertyController::class, 'showBySlug'])
        ->where('slug', '[a-z0-9-]+')
        ->name('properties.show');
});
