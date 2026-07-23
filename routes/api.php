<?php

use App\Http\Controllers\PropertyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/properties', [PropertyController::class, 'search'])
        ->name('properties.index');
    Route::get('/properties/{slug}', [PropertyController::class, 'showBySlug'])
        ->where('slug', '[a-z0-9-]+')
        ->name('properties.show');
});
