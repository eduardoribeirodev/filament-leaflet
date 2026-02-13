<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis\Concerns;

use Closure;
use EduardoRibeiroDev\FilamentLeafletPostgis\Enums\SpatialColumnType;
use Illuminate\Support\Facades\DB;

/**
 * Trait for handling PostGIS spatial columns in Filament Leaflet components.
 *
 * This trait enables automatic detection and conversion between JSON coordinates
 * and PostGIS geometry/geography types (POINT, LINESTRING, POLYGON, etc.).
 */
trait HasSpatialColumn
{
    /**
     * Whether the column is a PostGIS spatial column.
     */
    protected bool $isSpatialColumn = false;

    /**
     * The SRID (Spatial Reference System Identifier) for the geometry.
     * Default is 4326 (WGS84 - GPS coordinates).
     */
    protected int $srid = 4326;

    /**
     * The geometry type (POINT, LINESTRING, POLYGON, etc.).
     */
    protected ?string $geometryType = null;

    /**
     * Whether to auto-detect if the column is spatial.
     */
    protected bool $autoDetectSpatial = true;

    /**
     * Cache for detected column types.
     *
     * @var array<string, SpatialColumnType>
     */
    protected static array $columnTypeCache = [];

    /**
     * Mark this column as a PostGIS spatial column.
     */
    public function spatialColumn(bool|Closure $isSpatial = true): static
    {
        $this->isSpatialColumn = (bool) $this->evaluate($isSpatial);

        return $this;
    }

    /**
     * Set the SRID for the spatial column.
     */
    public function srid(int|Closure $srid): static
    {
        $this->srid = (int) $this->evaluate($srid);

        return $this;
    }

    /**
     * Set the geometry type (POINT, LINESTRING, POLYGON, MULTIPOINT, etc.).
     */
    public function geometryType(string|Closure $type): static
    {
        $this->geometryType = $this->evaluate($type);

        return $this;
    }

    /**
     * Enable or disable auto-detection of spatial columns.
     */
    public function autoDetectSpatial(bool|Closure $enabled = true): static
    {
        $this->autoDetectSpatial = (bool) $this->evaluate($enabled);

        return $this;
    }

    /**
     * Get the SRID for the spatial column.
     */
    public function getSrid(): int
    {
        return $this->srid;
    }

    /**
     * Get the geometry type.
     */
    public function getGeometryType(): ?string
    {
        return $this->geometryType;
    }

    /**
     * Check if this is a spatial column.
     */
    public function isSpatialColumn(): bool
    {
        if ($this->isSpatialColumn) {
            return true;
        }

        if (! $this->autoDetectSpatial) {
            return false;
        }

        return $this->detectSpatialColumn();
    }

    /**
     * Detect if the column is a PostGIS spatial column.
     */
    protected function detectSpatialColumn(): bool
    {
        $record = $this->getRecord();
        if (! $record) {
            return false;
        }

        $table = $record->getTable();
        $column = $this->getName();
        $cacheKey = "{$table}.{$column}";

        if (isset(static::$columnTypeCache[$cacheKey])) {
            return static::$columnTypeCache[$cacheKey] === SpatialColumnType::PostGIS;
        }

        $columnType = $this->getColumnSpatialType($table, $column);
        static::$columnTypeCache[$cacheKey] = $columnType;

        return $columnType === SpatialColumnType::PostGIS;
    }

    /**
     * Get the spatial type of a database column.
     */
    protected function getColumnSpatialType(string $table, string $column): SpatialColumnType
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            return $this->detectPostgresColumnType($table, $column);
        }

        if ($driver === 'mysql') {
            return $this->detectMysqlColumnType($table, $column);
        }

        // Default to JSON for SQLite and other databases
        return SpatialColumnType::Json;
    }

    /**
     * Detect PostGIS column type in PostgreSQL.
     */
    protected function detectPostgresColumnType(string $table, string $column): SpatialColumnType
    {
        try {
            // Check geometry_columns view (PostGIS)
            $result = DB::selectOne('
                SELECT type, srid
                FROM geometry_columns
                WHERE f_table_name = ? AND f_geometry_column = ?
            ', [$table, $column]);

            if ($result) {
                $this->geometryType = $result->type;
                $this->srid = (int) $result->srid;

                return SpatialColumnType::PostGIS;
            }

            // Check geography_columns view (PostGIS)
            $result = DB::selectOne('
                SELECT type, srid
                FROM geography_columns
                WHERE f_table_name = ? AND f_geography_column = ?
            ', [$table, $column]);

            if ($result) {
                $this->geometryType = $result->type;
                $this->srid = (int) $result->srid;

                return SpatialColumnType::PostGIS;
            }

            // Fallback: check column type directly
            $result = DB::selectOne('
                SELECT data_type, udt_name
                FROM information_schema.columns
                WHERE table_name = ? AND column_name = ?
            ', [$table, $column]);

            if ($result && in_array($result->udt_name, ['geometry', 'geography'], true)) {
                return SpatialColumnType::PostGIS;
            }

            if ($result && in_array($result->data_type, ['json', 'jsonb'], true)) {
                return SpatialColumnType::Json;
            }
        } catch (\Exception $e) {
            // PostGIS extension may not be installed
            report($e);
        }

        return SpatialColumnType::Json;
    }

    /**
     * Detect spatial column type in MySQL.
     */
    protected function detectMysqlColumnType(string $table, string $column): SpatialColumnType
    {
        try {
            $result = DB::selectOne('
                SELECT DATA_TYPE
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = ? AND COLUMN_NAME = ?
            ', [$table, $column]);

            if ($result) {
                $spatialTypes = [
                    'geometry',
                    'point',
                    'linestring',
                    'polygon',
                    'multipoint',
                    'multilinestring',
                    'multipolygon',
                    'geometrycollection',
                ];

                if (in_array(strtolower($result->DATA_TYPE), $spatialTypes, true)) {
                    $this->geometryType = strtoupper($result->DATA_TYPE);

                    return SpatialColumnType::MySQLSpatial;
                }

                if (in_array(strtolower($result->DATA_TYPE), ['json', 'longtext'], true)) {
                    return SpatialColumnType::Json;
                }
            }
        } catch (\Exception $e) {
            report($e);
        }

        return SpatialColumnType::Json;
    }

    /**
     * Convert GeoJSON to PostGIS format for storage.
     *
     * @param  array{type: string, coordinates: array}|array{lat: float, lng: float}  $geoJson
     */
    public function convertToPostGIS(array $geoJson): string
    {
        // Handle simple lat/lng format
        if (isset($geoJson['lat'], $geoJson['lng'])) {
            $geoJson = [
                'type' => 'Point',
                'coordinates' => [$geoJson['lng'], $geoJson['lat']],
            ];
        }

        // Handle lat/longitude field names
        if (isset($geoJson[$this->latitudeFieldName], $geoJson[$this->longitudeFieldName])) {
            $geoJson = [
                'type' => 'Point',
                'coordinates' => [
                    $geoJson[$this->longitudeFieldName],
                    $geoJson[$this->latitudeFieldName],
                ],
            ];
        }

        $jsonString = json_encode($geoJson);

        return "ST_SetSRID(ST_GeomFromGeoJSON('{$jsonString}'), {$this->srid})";
    }

    /**
     * Convert PostGIS geometry to GeoJSON format.
     *
     * @return array{type: string, coordinates: array}|null
     */
    public function convertFromPostGIS(mixed $geometry): ?array
    {
        if ($geometry === null) {
            return null;
        }

        // If already an array (JSON cast), return as-is
        if (is_array($geometry)) {
            return $geometry;
        }

        // If string, try to parse as JSON (might already be GeoJSON)
        if (is_string($geometry)) {
            $decoded = json_decode($geometry, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }

            // Try WKT parsing
            return $this->parseWKT($geometry);
        }

        return null;
    }

    /**
     * Parse Well-Known Text (WKT) to GeoJSON format.
     *
     * @return array{type: string, coordinates: array}|null
     */
    protected function parseWKT(string $wkt): ?array
    {
        $wkt = trim($wkt);

        // Remove SRID prefix if present
        if (preg_match('/^SRID=\d+;(.+)$/i', $wkt, $matches)) {
            $wkt = $matches[1];
        }

        // POINT
        if (preg_match('/^POINT\s*\(\s*([\d.-]+)\s+([\d.-]+)\s*\)$/i', $wkt, $matches)) {
            return [
                'type' => 'Point',
                'coordinates' => [(float) $matches[1], (float) $matches[2]],
            ];
        }

        // LINESTRING
        if (preg_match('/^LINESTRING\s*\((.+)\)$/i', $wkt, $matches)) {
            return [
                'type' => 'LineString',
                'coordinates' => $this->parseCoordinateString($matches[1]),
            ];
        }

        // POLYGON
        if (preg_match('/^POLYGON\s*\(\((.+)\)\)$/i', $wkt, $matches)) {
            $rings = explode('),(', $matches[1]);

            return [
                'type' => 'Polygon',
                'coordinates' => array_map(
                    fn ($ring) => $this->parseCoordinateString($ring),
                    $rings
                ),
            ];
        }

        // MULTIPOINT
        if (preg_match('/^MULTIPOINT\s*\((.+)\)$/i', $wkt, $matches)) {
            return [
                'type' => 'MultiPoint',
                'coordinates' => $this->parseCoordinateString($matches[1]),
            ];
        }

        // MULTILINESTRING
        if (preg_match('/^MULTILINESTRING\s*\((.+)\)$/i', $wkt, $matches)) {
            $lines = preg_split('/\),\s*\(/', trim($matches[1], '()'));

            return [
                'type' => 'MultiLineString',
                'coordinates' => array_map(
                    fn ($line) => $this->parseCoordinateString($line),
                    $lines
                ),
            ];
        }

        // MULTIPOLYGON
        if (preg_match('/^MULTIPOLYGON\s*\(\(\((.+)\)\)\)$/i', $wkt, $matches)) {
            $polygons = preg_split('/\)\),\s*\(\(/', $matches[1]);

            return [
                'type' => 'MultiPolygon',
                'coordinates' => array_map(function ($polygon) {
                    $rings = explode('),(', $polygon);

                    return array_map(
                        fn ($ring) => $this->parseCoordinateString($ring),
                        $rings
                    );
                }, $polygons),
            ];
        }

        return null;
    }

    /**
     * Parse a coordinate string into an array of coordinate pairs.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    protected function parseCoordinateString(string $coordString): array
    {
        $coords = [];
        $pairs = preg_split('/,\s*/', trim($coordString));

        foreach ($pairs as $pair) {
            $parts = preg_split('/\s+/', trim($pair));
            if (count($parts) >= 2) {
                $coords[] = [(float) $parts[0], (float) $parts[1]];
            }
        }

        return $coords;
    }

    /**
     * Convert GeoJSON coordinates to simple lat/lng array.
     *
     * @param  array{type: string, coordinates: array}  $geoJson
     * @return array{lat: float, lng: float}|null
     */
    public function geoJsonToLatLng(array $geoJson): ?array
    {
        if (! isset($geoJson['type'], $geoJson['coordinates'])) {
            // Already in lat/lng format
            if (isset($geoJson['lat'], $geoJson['lng'])) {
                return $geoJson;
            }

            return null;
        }

        if ($geoJson['type'] === 'Point') {
            return [
                'lng' => $geoJson['coordinates'][0],
                'lat' => $geoJson['coordinates'][1],
            ];
        }

        // For non-point geometries, return the centroid
        return $this->calculateCentroid($geoJson);
    }

    /**
     * Calculate the centroid of a geometry.
     *
     * @param  array{type: string, coordinates: array}  $geoJson
     * @return array{lat: float, lng: float}|null
     */
    protected function calculateCentroid(array $geoJson): ?array
    {
        $coords = $geoJson['coordinates'];
        $type = $geoJson['type'];

        switch ($type) {
            case 'Point':
                return ['lng' => $coords[0], 'lat' => $coords[1]];

            case 'LineString':
            case 'MultiPoint':
                return $this->averageCoordinates($coords);

            case 'Polygon':
                // Use exterior ring for centroid
                return $this->averageCoordinates($coords[0] ?? []);

            case 'MultiLineString':
                $allCoords = array_merge(...$coords);

                return $this->averageCoordinates($allCoords);

            case 'MultiPolygon':
                $allCoords = [];
                foreach ($coords as $polygon) {
                    $allCoords = array_merge($allCoords, $polygon[0] ?? []);
                }

                return $this->averageCoordinates($allCoords);
        }

        return null;
    }

    /**
     * Calculate the average of coordinate pairs.
     *
     * @param  array<int, array{0: float, 1: float}>  $coords
     * @return array{lat: float, lng: float}|null
     */
    protected function averageCoordinates(array $coords): ?array
    {
        if (empty($coords)) {
            return null;
        }

        $lngSum = 0;
        $latSum = 0;
        $count = count($coords);

        foreach ($coords as $coord) {
            $lngSum += $coord[0];
            $latSum += $coord[1];
        }

        return [
            'lng' => $lngSum / $count,
            'lat' => $latSum / $count,
        ];
    }

    /**
     * Convert coordinates to WKT format.
     *
     * @param  array{lat: float, lng: float}|array{type: string, coordinates: array}  $data
     */
    public function toWKT(array $data): string
    {
        // Handle simple lat/lng
        if (isset($data['lat'], $data['lng'])) {
            return sprintf('POINT(%f %f)', $data['lng'], $data['lat']);
        }

        // Handle GeoJSON
        if (isset($data['type'], $data['coordinates'])) {
            return $this->geoJsonToWKT($data);
        }

        // Handle latitude/longitude field names
        if (isset($data[$this->latitudeFieldName], $data[$this->longitudeFieldName])) {
            return sprintf(
                'POINT(%f %f)',
                $data[$this->longitudeFieldName],
                $data[$this->latitudeFieldName]
            );
        }

        return 'POINT(0 0)';
    }

    /**
     * Convert GeoJSON to WKT format.
     *
     * @param  array{type: string, coordinates: array}  $geoJson
     */
    protected function geoJsonToWKT(array $geoJson): string
    {
        $type = $geoJson['type'];
        $coords = $geoJson['coordinates'];

        switch ($type) {
            case 'Point':
                return sprintf('POINT(%f %f)', $coords[0], $coords[1]);

            case 'LineString':
                $points = array_map(
                    fn ($c) => sprintf('%f %f', $c[0], $c[1]),
                    $coords
                );

                return 'LINESTRING('.implode(', ', $points).')';

            case 'Polygon':
                $rings = array_map(function ($ring) {
                    $points = array_map(
                        fn ($c) => sprintf('%f %f', $c[0], $c[1]),
                        $ring
                    );

                    return '('.implode(', ', $points).')';
                }, $coords);

                return 'POLYGON('.implode(', ', $rings).')';

            case 'MultiPoint':
                $points = array_map(
                    fn ($c) => sprintf('%f %f', $c[0], $c[1]),
                    $coords
                );

                return 'MULTIPOINT('.implode(', ', $points).')';

            case 'MultiLineString':
                $lines = array_map(function ($line) {
                    $points = array_map(
                        fn ($c) => sprintf('%f %f', $c[0], $c[1]),
                        $line
                    );

                    return '('.implode(', ', $points).')';
                }, $coords);

                return 'MULTILINESTRING('.implode(', ', $lines).')';

            case 'MultiPolygon':
                $polygons = array_map(function ($polygon) {
                    $rings = array_map(function ($ring) {
                        $points = array_map(
                            fn ($c) => sprintf('%f %f', $c[0], $c[1]),
                            $ring
                        );

                        return '('.implode(', ', $points).')';
                    }, $polygon);

                    return '('.implode(', ', $rings).')';
                }, $coords);

                return 'MULTIPOLYGON('.implode(', ', $polygons).')';
        }

        return 'POINT(0 0)';
    }
}
