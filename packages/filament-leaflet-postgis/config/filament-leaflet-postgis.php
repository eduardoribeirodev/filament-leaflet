<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default SRID
    |--------------------------------------------------------------------------
    |
    | The default Spatial Reference System Identifier (SRID) for geometries.
    | 4326 is WGS84, the standard GPS coordinate system.
    |
    */
    'default_srid' => env('FILAMENT_LEAFLET_SRID', 4326),

    /*
    |--------------------------------------------------------------------------
    | Auto-Detect Spatial Columns
    |--------------------------------------------------------------------------
    |
    | When enabled, the package will automatically detect if a column is a
    | PostGIS geometry/geography column and handle conversions appropriately.
    |
    */
    'auto_detect_spatial' => env('FILAMENT_LEAFLET_AUTO_DETECT', true),

    /*
    |--------------------------------------------------------------------------
    | Spatial Query Caching
    |--------------------------------------------------------------------------
    |
    | Enable caching for expensive spatial queries to improve performance.
    |
    */
    'cache' => [
        'enabled' => env('FILAMENT_LEAFLET_CACHE_ENABLED', false),
        'ttl' => env('FILAMENT_LEAFLET_CACHE_TTL', 3600), // seconds
        'prefix' => 'filament-leaflet-spatial:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Driver Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for different database drivers.
    |
    */
    'drivers' => [
        'pgsql' => [
            // Use geography type for more accurate distance calculations
            'use_geography' => env('FILAMENT_LEAFLET_USE_GEOGRAPHY', true),

            // Index type recommendation
            'index_type' => 'GIST', // or 'BRIN' for time-series data
        ],

        'mysql' => [
            // MySQL spatial functions are more limited
            'supported_functions' => [
                'ST_Distance',
                'ST_Within',
                'ST_Contains',
                'ST_Intersects',
                'ST_Buffer',
                'ST_Centroid',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Optimization
    |--------------------------------------------------------------------------
    |
    | Settings for optimizing performance with large datasets.
    |
    */
    'performance' => [
        // Maximum markers to load at once (use pagination/viewport filtering)
        'max_markers' => env('FILAMENT_LEAFLET_MAX_MARKERS', 5000),

        // Enable viewport-based lazy loading
        'viewport_loading' => env('FILAMENT_LEAFLET_VIEWPORT_LOADING', true),

        // Simplification tolerance for complex geometries
        'simplify_tolerance' => env('FILAMENT_LEAFLET_SIMPLIFY_TOLERANCE', 0.0001),
    ],

    /*
    |--------------------------------------------------------------------------
    | GeoJSON Import/Export
    |--------------------------------------------------------------------------
    |
    | Settings for GeoJSON file handling.
    |
    */
    'geojson' => [
        // Maximum file size for import (in bytes)
        'max_import_size' => env('FILAMENT_LEAFLET_MAX_IMPORT_SIZE', 10485760), // 10MB

        // Batch size for bulk imports
        'import_batch_size' => env('FILAMENT_LEAFLET_IMPORT_BATCH_SIZE', 100),

        // Coordinate precision for exports
        'export_precision' => env('FILAMENT_LEAFLET_EXPORT_PRECISION', 6),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Geometry Types
    |--------------------------------------------------------------------------
    |
    | List of supported geometry types and their configurations.
    |
    */
    'geometry_types' => [
        'Point' => [
            'class' => \EduardoRibeiroDev\FilamentLeaflet\Support\Markers\Marker::class,
            'color' => '#3388ff',
        ],
        'LineString' => [
            'class' => \EduardoRibeiroDev\FilamentLeaflet\Support\Shapes\Polyline::class,
            'color' => '#3388ff',
        ],
        'Polygon' => [
            'class' => \EduardoRibeiroDev\FilamentLeaflet\Support\Shapes\Polygon::class,
            'color' => '#3388ff',
            'fill_color' => '#3388ff',
            'fill_opacity' => 0.2,
        ],
        'MultiPoint' => [
            'class' => \EduardoRibeiroDev\FilamentLeaflet\Support\Geometry\MultiPoint::class,
            'color' => '#3388ff',
        ],
        'MultiLineString' => [
            'class' => \EduardoRibeiroDev\FilamentLeaflet\Support\Geometry\MultiLineString::class,
            'color' => '#3388ff',
        ],
        'MultiPolygon' => [
            'class' => \EduardoRibeiroDev\FilamentLeaflet\Support\Geometry\MultiPolygon::class,
            'color' => '#3388ff',
            'fill_color' => '#3388ff',
            'fill_opacity' => 0.2,
        ],
        'GeometryCollection' => [
            'class' => \EduardoRibeiroDev\FilamentLeaflet\Support\Geometry\GeometryCollection::class,
        ],
    ],
];
