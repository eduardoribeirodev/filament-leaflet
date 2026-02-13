<?php

declare(strict_types=1);

namespace App\Support\Spatial;

use Illuminate\Support\ServiceProvider;

/**
 * SpatialServiceProvider - Registers spatial services.
 */
class SpatialServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(MeasurementTools::class, function () {
            return new MeasurementTools;
        });

        $this->app->singleton(CoordinateConverter::class, function () {
            return new CoordinateConverter;
        });

        $this->app->singleton(Geocoder::class, function () {
            return new Geocoder;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
