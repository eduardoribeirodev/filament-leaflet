<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis;

use EduardoRibeiroDev\FilamentLeafletPostgis\Database\SpatialScopes;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for PostGIS spatial support in Filament Leaflet.
 */
class PostGISServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/filament-leaflet-postgis.php',
            'filament-leaflet-postgis'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register spatial query macros
        SpatialScopes::registerSpatialMacros();

        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/filament-leaflet-postgis.php' => config_path('filament-leaflet-postgis.php'),
        ], 'filament-leaflet-postgis-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'filament-leaflet-postgis-migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
