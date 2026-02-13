<?php

use App\Http\Controllers\Api\SpatialDataController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

Route::prefix('spatial')->name('spatial.')->group(function () {
    // Get available models
    Route::get('models', [SpatialDataController::class, 'models'])
        ->name('models');

    // Measurement endpoints
    Route::post('distance', [SpatialDataController::class, 'distance'])
        ->name('distance');

    Route::post('area', [SpatialDataController::class, 'area'])
        ->name('area');

    // Model-specific endpoints
    Route::prefix('{model}')->group(function () {
        // Load features within viewport bounds with optional clustering
        Route::get('features', [SpatialDataController::class, 'features'])
            ->name('features');

        // Load features as GeoJSON FeatureCollection
        Route::get('geojson', [SpatialDataController::class, 'geojson'])
            ->name('geojson');

        // Export features as KML
        Route::get('kml', [SpatialDataController::class, 'kml'])
            ->name('kml');

        // Get feature count within bounds
        Route::get('count', [SpatialDataController::class, 'count'])
            ->name('count');

        // Invalidate cache for model
        Route::post('cache/invalidate', [SpatialDataController::class, 'invalidateCache'])
            ->name('cache.invalidate');
    });
});
