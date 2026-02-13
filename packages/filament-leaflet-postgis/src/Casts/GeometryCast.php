<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent cast for PostGIS geometry columns.
 *
 * Automatically converts between PostGIS binary format and GeoJSON arrays.
 *
 * Usage in your model:
 * ```php
 * protected function casts(): array
 * {
 *     return [
 *         'location' => GeometryCast::class,
 *         // or with options:
 *         'location' => GeometryCast::class . ':4326,Point',
 *     ];
 * }
 * ```
 */
class GeometryCast implements CastsAttributes
{
    /**
     * The SRID for the geometry (default: 4326 WGS84).
     */
    protected int $srid;

    /**
     * The expected geometry type (POINT, LINESTRING, POLYGON, etc.).
     */
    protected ?string $geometryType;

    /**
     * Create a new cast instance.
     */
    public function __construct(int $srid = 4326, ?string $geometryType = null)
    {
        $this->srid = $srid;
        $this->geometryType = $geometryType;
    }

    /**
     * Cast the given value (from database to PHP).
     *
     * @param  array<string, mixed>  $attributes
     * @return array{type: string, coordinates: array}|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        // If already an array, return as-is (might be from JSON column)
        if (is_array($value)) {
            return $value;
        }

        // Try to decode as JSON first (might already be GeoJSON)
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // For PostGIS, we need to use ST_AsGeoJSON in a raw query
        // This typically happens when the model accessor is used
        $driver = $model->getConnection()->getDriverName();

        if ($driver === 'pgsql' || $driver === 'mysql') {
            try {
                $result = $model->getConnection()->selectOne(
                    'SELECT ST_AsGeoJSON(?) AS geojson',
                    [$value]
                );

                if ($result && $result->geojson) {
                    return json_decode($result->geojson, true);
                }
            } catch (\Exception $e) {
                // Fall through to return null
                report($e);
            }
        }

        return null;
    }

    /**
     * Prepare the given value for storage (from PHP to database).
     *
     * @param  array<string, mixed>  $attributes
     * @return \Illuminate\Database\Query\Expression|string|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        // Handle simple lat/lng array format
        if (is_array($value)) {
            // Convert {"lat": x, "lng": y} to GeoJSON Point
            if (isset($value['lat'], $value['lng'])) {
                $value = [
                    'type' => 'Point',
                    'coordinates' => [(float) $value['lng'], (float) $value['lat']],
                ];
            }

            // Handle {"latitude": x, "longitude": y}
            if (isset($value['latitude'], $value['longitude'])) {
                $value = [
                    'type' => 'Point',
                    'coordinates' => [(float) $value['longitude'], (float) $value['latitude']],
                ];
            }
        }

        // Ensure we have a valid GeoJSON structure
        if (! is_array($value) || ! isset($value['type'], $value['coordinates'])) {
            return null;
        }

        // Validate geometry type if specified
        if ($this->geometryType !== null) {
            $expectedType = strtoupper($this->geometryType);
            $actualType = strtoupper($value['type']);

            if ($expectedType !== $actualType) {
                throw new \InvalidArgumentException(
                    "Expected geometry type {$expectedType}, got {$actualType}"
                );
            }
        }

        $driver = $model->getConnection()->getDriverName();
        $geoJson = json_encode($value);

        if ($driver === 'pgsql') {
            return DB::raw("ST_SetSRID(ST_GeomFromGeoJSON('{$geoJson}'), {$this->srid})");
        }

        if ($driver === 'mysql') {
            return DB::raw("ST_GeomFromGeoJSON('{$geoJson}')");
        }

        // For other databases, store as JSON string
        return $geoJson;
    }
}
