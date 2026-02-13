<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis\Database;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait providing spatial query scopes for Eloquent models.
 *
 * Enables fluent spatial queries using PostGIS functions.
 *
 * @method static Builder withinRadius(string $column, float $lat, float $lng, float $radiusKm)
 * @method static Builder orderByDistance(string $column, float $lat, float $lng, string $direction = 'asc')
 * @method static Builder intersects(string $column, array $geometry)
 * @method static Builder contains(string $column, array $geometry)
 * @method static Builder within(string $column, array $geometry)
 * @method static Builder withinBounds(string $column, float $minLng, float $minLat, float $maxLng, float $maxLat)
 */
trait SpatialScopes
{
    /**
     * The default SRID for spatial operations (WGS84).
     */
    protected static int $defaultSrid = 4326;

    /**
     * Boot the trait and register macros.
     */
    public static function bootSpatialScopes(): void
    {
        // Register query builder macros if not already registered
        if (! Builder::hasGlobalMacro('withinRadius')) {
            static::registerSpatialMacros();
        }
    }

    /**
     * Register spatial query macros.
     */
    protected static function registerSpatialMacros(): void
    {
        /**
         * Find records within a radius (in kilometers) from a point.
         */
        Builder::macro('withinRadius', function (
            string $column,
            float $lat,
            float $lng,
            float $radiusKm,
            ?int $srid = null
        ): Builder {
            /** @var Builder $this */
            $srid ??= 4326;
            $radiusMeters = $radiusKm * 1000;

            $driver = $this->getConnection()->getDriverName();

            if ($driver === 'pgsql') {
                // PostGIS: Use ST_DWithin for indexed searches (geography type)
                return $this->whereRaw(
                    "ST_DWithin(
                        {$column}::geography,
                        ST_SetSRID(ST_MakePoint(?, ?), ?)::geography,
                        ?
                    )",
                    [$lng, $lat, $srid, $radiusMeters]
                );
            }

            if ($driver === 'mysql') {
                // MySQL: Use ST_Distance_Sphere
                return $this->whereRaw(
                    "ST_Distance_Sphere(
                        {$column},
                        ST_SRID(POINT(?, ?), ?)
                    ) <= ?",
                    [$lng, $lat, $srid, $radiusMeters]
                );
            }

            // Fallback: Haversine formula for JSON columns
            return $this->whereRaw(
                "(
                    6371 * acos(
                        cos(radians(?)) *
                        cos(radians(CAST({$column}->>'lat' AS FLOAT))) *
                        cos(radians(CAST({$column}->>'lng' AS FLOAT)) - radians(?)) +
                        sin(radians(?)) *
                        sin(radians(CAST({$column}->>'lat' AS FLOAT)))
                    )
                ) <= ?",
                [$lat, $lng, $lat, $radiusKm]
            );
        });

        /**
         * Order records by distance from a point.
         */
        Builder::macro('orderByDistance', function (
            string $column,
            float $lat,
            float $lng,
            string $direction = 'asc',
            ?int $srid = null
        ): Builder {
            /** @var Builder $this */
            $srid ??= 4326;

            $driver = $this->getConnection()->getDriverName();

            if ($driver === 'pgsql') {
                return $this->orderByRaw(
                    "ST_Distance(
                        {$column}::geography,
                        ST_SetSRID(ST_MakePoint(?, ?), ?)::geography
                    ) {$direction}",
                    [$lng, $lat, $srid]
                );
            }

            if ($driver === 'mysql') {
                return $this->orderByRaw(
                    "ST_Distance_Sphere(
                        {$column},
                        ST_SRID(POINT(?, ?), ?)
                    ) {$direction}",
                    [$lng, $lat, $srid]
                );
            }

            // Fallback: Haversine for JSON
            return $this->orderByRaw(
                "(
                    6371 * acos(
                        cos(radians(?)) *
                        cos(radians(CAST({$column}->>'lat' AS FLOAT))) *
                        cos(radians(CAST({$column}->>'lng' AS FLOAT)) - radians(?)) +
                        sin(radians(?)) *
                        sin(radians(CAST({$column}->>'lat' AS FLOAT)))
                    )
                ) {$direction}",
                [$lat, $lng, $lat]
            );
        });

        /**
         * Select distance as a column.
         */
        Builder::macro('selectDistance', function (
            string $column,
            float $lat,
            float $lng,
            string $alias = 'distance',
            ?int $srid = null
        ): Builder {
            /** @var Builder $this */
            $srid ??= 4326;

            $driver = $this->getConnection()->getDriverName();

            if ($driver === 'pgsql') {
                return $this->selectRaw(
                    "ST_Distance(
                        {$column}::geography,
                        ST_SetSRID(ST_MakePoint(?, ?), ?)::geography
                    ) / 1000 AS {$alias}",
                    [$lng, $lat, $srid]
                );
            }

            if ($driver === 'mysql') {
                return $this->selectRaw(
                    "ST_Distance_Sphere(
                        {$column},
                        ST_SRID(POINT(?, ?), ?)
                    ) / 1000 AS {$alias}",
                    [$lng, $lat, $srid]
                );
            }

            // Fallback: Haversine for JSON (returns km)
            return $this->selectRaw(
                "(
                    6371 * acos(
                        cos(radians(?)) *
                        cos(radians(CAST({$column}->>'lat' AS FLOAT))) *
                        cos(radians(CAST({$column}->>'lng' AS FLOAT)) - radians(?)) +
                        sin(radians(?)) *
                        sin(radians(CAST({$column}->>'lat' AS FLOAT)))
                    )
                ) AS {$alias}",
                [$lat, $lng, $lat]
            );
        });

        /**
         * Find records that intersect with a geometry.
         */
        Builder::macro('intersects', function (
            string $column,
            array $geometry,
            ?int $srid = null
        ): Builder {
            /** @var Builder $this */
            $srid ??= 4326;
            $geoJson = json_encode($geometry);

            $driver = $this->getConnection()->getDriverName();

            if ($driver === 'pgsql') {
                return $this->whereRaw(
                    "ST_Intersects(
                        {$column},
                        ST_SetSRID(ST_GeomFromGeoJSON(?), ?)
                    )",
                    [$geoJson, $srid]
                );
            }

            if ($driver === 'mysql') {
                return $this->whereRaw(
                    "ST_Intersects(
                        {$column},
                        ST_GeomFromGeoJSON(?)
                    )",
                    [$geoJson]
                );
            }

            // JSON fallback not supported for intersection
            throw new \RuntimeException(
                'Spatial intersection queries require PostGIS or MySQL Spatial extension.'
            );
        });

        /**
         * Find records contained within a geometry.
         */
        Builder::macro('within', function (
            string $column,
            array $geometry,
            ?int $srid = null
        ): Builder {
            /** @var Builder $this */
            $srid ??= 4326;
            $geoJson = json_encode($geometry);

            $driver = $this->getConnection()->getDriverName();

            if ($driver === 'pgsql') {
                return $this->whereRaw(
                    "ST_Within(
                        {$column},
                        ST_SetSRID(ST_GeomFromGeoJSON(?), ?)
                    )",
                    [$geoJson, $srid]
                );
            }

            if ($driver === 'mysql') {
                return $this->whereRaw(
                    "ST_Within(
                        {$column},
                        ST_GeomFromGeoJSON(?)
                    )",
                    [$geoJson]
                );
            }

            throw new \RuntimeException(
                'Spatial within queries require PostGIS or MySQL Spatial extension.'
            );
        });

        /**
         * Find records that contain a geometry.
         */
        Builder::macro('contains', function (
            string $column,
            array $geometry,
            ?int $srid = null
        ): Builder {
            /** @var Builder $this */
            $srid ??= 4326;
            $geoJson = json_encode($geometry);

            $driver = $this->getConnection()->getDriverName();

            if ($driver === 'pgsql') {
                return $this->whereRaw(
                    "ST_Contains(
                        {$column},
                        ST_SetSRID(ST_GeomFromGeoJSON(?), ?)
                    )",
                    [$geoJson, $srid]
                );
            }

            if ($driver === 'mysql') {
                return $this->whereRaw(
                    "ST_Contains(
                        {$column},
                        ST_GeomFromGeoJSON(?)
                    )",
                    [$geoJson]
                );
            }

            throw new \RuntimeException(
                'Spatial contains queries require PostGIS or MySQL Spatial extension.'
            );
        });

        /**
         * Find records within a bounding box.
         */
        Builder::macro('withinBounds', function (
            string $column,
            float $minLng,
            float $minLat,
            float $maxLng,
            float $maxLat,
            ?int $srid = null
        ): Builder {
            /** @var Builder $this */
            $srid ??= 4326;

            $driver = $this->getConnection()->getDriverName();

            if ($driver === 'pgsql') {
                return $this->whereRaw(
                    "ST_Within(
                        {$column},
                        ST_MakeEnvelope(?, ?, ?, ?, ?)
                    )",
                    [$minLng, $minLat, $maxLng, $maxLat, $srid]
                );
            }

            if ($driver === 'mysql') {
                return $this->whereRaw(
                    "ST_Within(
                        {$column},
                        ST_MakeEnvelope(
                            POINT(?, ?),
                            POINT(?, ?)
                        )
                    )",
                    [$minLng, $minLat, $maxLng, $maxLat]
                );
            }

            // JSON fallback
            return $this->whereRaw(
                "CAST({$column}->>'lng' AS FLOAT) BETWEEN ? AND ?
                 AND CAST({$column}->>'lat' AS FLOAT) BETWEEN ? AND ?",
                [$minLng, $maxLng, $minLat, $maxLat]
            );
        });

        /**
         * Apply a buffer around the geometry.
         */
        Builder::macro('buffer', function (
            string $column,
            float $distanceMeters,
            string $alias = 'buffered',
            ?int $srid = null
        ): Builder {
            /** @var Builder $this */
            $srid ??= 4326;

            $driver = $this->getConnection()->getDriverName();

            if ($driver === 'pgsql') {
                return $this->selectRaw(
                    "ST_AsGeoJSON(ST_Buffer({$column}::geography, ?)::geometry) AS {$alias}",
                    [$distanceMeters]
                );
            }

            if ($driver === 'mysql') {
                return $this->selectRaw(
                    "ST_AsGeoJSON(ST_Buffer({$column}, ?)) AS {$alias}",
                    [$distanceMeters]
                );
            }

            throw new \RuntimeException(
                'Buffer operations require PostGIS or MySQL Spatial extension.'
            );
        });

        /**
         * Get the centroid of geometries.
         */
        Builder::macro('selectCentroid', function (
            string $column,
            string $alias = 'centroid'
        ): Builder {
            /** @var Builder $this */
            $driver = $this->getConnection()->getDriverName();

            if ($driver === 'pgsql') {
                return $this->selectRaw(
                    "ST_AsGeoJSON(ST_Centroid({$column})) AS {$alias}"
                );
            }

            if ($driver === 'mysql') {
                return $this->selectRaw(
                    "ST_AsGeoJSON(ST_Centroid({$column})) AS {$alias}"
                );
            }

            // JSON fallback - just return the original coordinates
            return $this->selectRaw("{$column} AS {$alias}");
        });

        /**
         * Get the area of polygon geometries (in square meters).
         */
        Builder::macro('selectArea', function (
            string $column,
            string $alias = 'area'
        ): Builder {
            /** @var Builder $this */
            $driver = $this->getConnection()->getDriverName();

            if ($driver === 'pgsql') {
                return $this->selectRaw(
                    "ST_Area({$column}::geography) AS {$alias}"
                );
            }

            if ($driver === 'mysql') {
                return $this->selectRaw(
                    "ST_Area({$column}) AS {$alias}"
                );
            }

            throw new \RuntimeException(
                'Area calculations require PostGIS or MySQL Spatial extension.'
            );
        });

        /**
         * Get the length of linestring geometries (in meters).
         */
        Builder::macro('selectLength', function (
            string $column,
            string $alias = 'length'
        ): Builder {
            /** @var Builder $this */
            $driver = $this->getConnection()->getDriverName();

            if ($driver === 'pgsql') {
                return $this->selectRaw(
                    "ST_Length({$column}::geography) AS {$alias}"
                );
            }

            if ($driver === 'mysql') {
                return $this->selectRaw(
                    "ST_Length({$column}) AS {$alias}"
                );
            }

            throw new \RuntimeException(
                'Length calculations require PostGIS or MySQL Spatial extension.'
            );
        });

        /**
         * Simplify geometries for performance.
         */
        Builder::macro('simplify', function (
            string $column,
            float $tolerance,
            string $alias = 'simplified'
        ): Builder {
            /** @var Builder $this */
            $driver = $this->getConnection()->getDriverName();

            if ($driver === 'pgsql') {
                return $this->selectRaw(
                    "ST_AsGeoJSON(ST_Simplify({$column}, ?)) AS {$alias}",
                    [$tolerance]
                );
            }

            if ($driver === 'mysql') {
                return $this->selectRaw(
                    "ST_AsGeoJSON(ST_Simplify({$column}, ?)) AS {$alias}",
                    [$tolerance]
                );
            }

            // JSON fallback - return original
            return $this->selectRaw("{$column} AS {$alias}");
        });

        /**
         * Convert geometry to GeoJSON for frontend.
         */
        Builder::macro('selectAsGeoJson', function (
            string $column,
            string $alias = 'geojson'
        ): Builder {
            /** @var Builder $this */
            $driver = $this->getConnection()->getDriverName();

            if ($driver === 'pgsql' || $driver === 'mysql') {
                return $this->selectRaw(
                    "ST_AsGeoJSON({$column}) AS {$alias}"
                );
            }

            // JSON fallback - format as GeoJSON Point
            return $this->selectRaw(
                "json_build_object(
                    'type', 'Point',
                    'coordinates', json_build_array(
                        CAST({$column}->>'lng' AS FLOAT),
                        CAST({$column}->>'lat' AS FLOAT)
                    )
                ) AS {$alias}"
            );
        });
    }

    /**
     * Scope: Find records within radius.
     */
    public function scopeNearby(
        Builder $query,
        string $column,
        float $lat,
        float $lng,
        float $radiusKm
    ): Builder {
        return $query->withinRadius($column, $lat, $lng, $radiusKm);
    }

    /**
     * Scope: Order by distance.
     */
    public function scopeClosestTo(
        Builder $query,
        string $column,
        float $lat,
        float $lng
    ): Builder {
        return $query->orderByDistance($column, $lat, $lng, 'asc');
    }

    /**
     * Scope: Order by distance descending.
     */
    public function scopeFarthestFrom(
        Builder $query,
        string $column,
        float $lat,
        float $lng
    ): Builder {
        return $query->orderByDistance($column, $lat, $lng, 'desc');
    }

    /**
     * Calculate distance from a point (instance method).
     */
    public function distanceFrom(string $column, float $lat, float $lng): ?float
    {
        $location = $this->{$column};

        if (! $location) {
            return null;
        }

        // Extract lat/lng from various formats
        $recordLat = null;
        $recordLng = null;

        if (is_array($location)) {
            if (isset($location['lat'], $location['lng'])) {
                $recordLat = $location['lat'];
                $recordLng = $location['lng'];
            } elseif (isset($location['coordinates'])) {
                $recordLng = $location['coordinates'][0];
                $recordLat = $location['coordinates'][1];
            }
        }

        if ($recordLat === null || $recordLng === null) {
            return null;
        }

        // Haversine formula
        $earthRadius = 6371; // km

        $latDiff = deg2rad($recordLat - $lat);
        $lngDiff = deg2rad($recordLng - $lng);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
            cos(deg2rad($lat)) * cos(deg2rad($recordLat)) *
            sin($lngDiff / 2) * sin($lngDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
