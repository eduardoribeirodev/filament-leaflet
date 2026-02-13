<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis\Support\Geometry;

use Closure;
use EduardoRibeiroDev\FilamentLeafletPostgis\Enums\Color;
use EduardoRibeiroDev\FilamentLeafletPostgis\Support\BaseLayer;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a GeometryCollection (heterogeneous collection of geometries).
 */
class GeometryCollection extends BaseLayer
{
    /**
     * @var array<int, array{type: string, coordinates: array}> Array of GeoJSON geometries
     */
    protected array $geometries = [];

    final public function __construct(array $geometries = [])
    {
        $this->geometries = $geometries;
    }

    public static function make(array $geometries = []): static
    {
        return new static($geometries);
    }

    /**
     * Create from a database record.
     */
    public static function fromRecord(
        Model $record,
        string $geometriesColumn = 'geometries',
        ?string $titleColumn = 'title',
        ?string $descriptionColumn = 'description',
        ?array $popupFieldsColumns = null,
        null|string|Color $color = null,
        ?Closure $mapRecordCallback = null
    ): static {
        $geometries = [];

        if ($record->hasAttribute($geometriesColumn)) {
            $value = $record->{$geometriesColumn};

            // Handle GeoJSON format
            if (is_array($value) && isset($value['type'])) {
                if ($value['type'] === 'GeometryCollection' && isset($value['geometries'])) {
                    $geometries = $value['geometries'];
                } elseif (isset($value['coordinates'])) {
                    // Single geometry
                    $geometries = [$value];
                }
            } elseif (is_string($value)) {
                $decoded = json_decode($value, true);
                if (isset($decoded['geometries'])) {
                    $geometries = $decoded['geometries'];
                }
            } elseif (is_array($value)) {
                $geometries = $value;
            }
        }

        return (new static($geometries))
            ->record($record)
            ->title($record->{$titleColumn} ?? null)
            ->popupContent($record->{$descriptionColumn} ?? null)
            ->popupFields(is_array($popupFieldsColumns) ? $record->only($popupFieldsColumns) : $record->except([
                'id',
                $geometriesColumn,
                $titleColumn,
                $descriptionColumn,
                'created_at',
                'updated_at',
            ]))
            ->color($color)
            ->mapRecordUsing($mapRecordCallback);
    }

    /**
     * Create from GeoJSON.
     *
     * @param  array{type: string, geometries?: array}  $geoJson
     */
    public static function fromGeoJson(array $geoJson): static
    {
        if (($geoJson['type'] ?? null) !== 'GeometryCollection') {
            throw new \InvalidArgumentException('GeoJSON type must be GeometryCollection');
        }

        return new static($geoJson['geometries'] ?? []);
    }

    /**
     * Create from WKT string.
     */
    public static function fromWKT(string $wkt): static
    {
        $wkt = trim($wkt);

        // Remove SRID prefix if present
        if (preg_match('/^SRID=\d+;(.+)$/i', $wkt, $matches)) {
            $wkt = $matches[1];
        }

        if (! preg_match('/^GEOMETRYCOLLECTION\s*\((.+)\)$/is', $wkt, $matches)) {
            throw new \InvalidArgumentException('Invalid WKT for GeometryCollection');
        }

        // Parse individual geometries - this is complex due to nested parentheses
        $geometries = [];
        $content = $matches[1];

        // Split by top-level commas (not inside parentheses)
        $parts = static::splitByTopLevelComma($content);

        foreach ($parts as $part) {
            $part = trim($part);
            $geometry = static::parseWKTGeometry($part);
            if ($geometry) {
                $geometries[] = $geometry;
            }
        }

        return new static($geometries);
    }

    /**
     * Split string by commas at the top level only (not inside parentheses).
     */
    protected static function splitByTopLevelComma(string $content): array
    {
        $parts = [];
        $depth = 0;
        $current = '';

        for ($i = 0; $i < strlen($content); $i++) {
            $char = $content[$i];

            if ($char === '(') {
                $depth++;
                $current .= $char;
            } elseif ($char === ')') {
                $depth--;
                $current .= $char;
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }

    /**
     * Parse a single WKT geometry string to GeoJSON.
     */
    protected static function parseWKTGeometry(string $wkt): ?array
    {
        $wkt = trim($wkt);

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
                'coordinates' => static::parseCoordinateString($matches[1]),
            ];
        }

        // POLYGON
        if (preg_match('/^POLYGON\s*\((.+)\)$/i', $wkt, $matches)) {
            $rings = static::parsePolygonRings($matches[1]);

            return [
                'type' => 'Polygon',
                'coordinates' => $rings,
            ];
        }

        // MULTIPOINT
        if (preg_match('/^MULTIPOINT\s*\((.+)\)$/i', $wkt, $matches)) {
            return [
                'type' => 'MultiPoint',
                'coordinates' => static::parseCoordinateString($matches[1]),
            ];
        }

        // MULTILINESTRING
        if (preg_match('/^MULTILINESTRING\s*\((.+)\)$/i', $wkt, $matches)) {
            $lineStrings = static::splitByTopLevelComma(trim($matches[1], '()'));

            return [
                'type' => 'MultiLineString',
                'coordinates' => array_map(
                    fn ($line) => static::parseCoordinateString(trim($line, '()')),
                    $lineStrings
                ),
            ];
        }

        // MULTIPOLYGON
        if (preg_match('/^MULTIPOLYGON\s*\((.+)\)$/i', $wkt, $matches)) {
            $polygonStrings = static::splitByTopLevelComma(trim($matches[1], '()'));

            return [
                'type' => 'MultiPolygon',
                'coordinates' => array_map(
                    fn ($polygon) => static::parsePolygonRings(trim($polygon, '()')),
                    $polygonStrings
                ),
            ];
        }

        return null;
    }

    /**
     * Parse coordinate string to array of coordinate pairs.
     */
    protected static function parseCoordinateString(string $coordString): array
    {
        $coords = [];
        $pairs = preg_split('/,\s*/', trim($coordString));

        foreach ($pairs as $pair) {
            $pair = trim($pair, '() ');
            $parts = preg_split('/\s+/', $pair);
            if (count($parts) >= 2) {
                $coords[] = [(float) $parts[0], (float) $parts[1]];
            }
        }

        return $coords;
    }

    /**
     * Parse polygon rings from WKT format.
     */
    protected static function parsePolygonRings(string $content): array
    {
        $rings = [];
        $ringStrings = preg_split('/\),\s*\(/', trim($content, '()'));

        foreach ($ringStrings as $ringString) {
            $rings[] = static::parseCoordinateString($ringString);
        }

        return $rings;
    }

    /**
     * Add a geometry to the collection.
     *
     * @param  array{type: string, coordinates: array}  $geometry
     */
    public function addGeometry(array $geometry): static
    {
        $this->geometries[] = $geometry;

        return $this;
    }

    /**
     * Add a Point geometry.
     */
    public function addPoint(float $lng, float $lat): static
    {
        $this->geometries[] = [
            'type' => 'Point',
            'coordinates' => [$lng, $lat],
        ];

        return $this;
    }

    /**
     * Add a LineString geometry.
     *
     * @param  array<int, array{0: float, 1: float}>  $coordinates
     */
    public function addLineString(array $coordinates): static
    {
        $this->geometries[] = [
            'type' => 'LineString',
            'coordinates' => $coordinates,
        ];

        return $this;
    }

    /**
     * Add a Polygon geometry.
     *
     * @param  array<int, array<int, array{0: float, 1: float}>>  $rings
     */
    public function addPolygon(array $rings): static
    {
        $this->geometries[] = [
            'type' => 'Polygon',
            'coordinates' => $rings,
        ];

        return $this;
    }

    /**
     * Get all geometries.
     *
     * @return array<int, array{type: string, coordinates: array}>
     */
    public function getGeometries(): array
    {
        return $this->geometries;
    }

    public function getType(): string
    {
        return 'geometrycollection';
    }

    protected function getLayerData(): array
    {
        return [
            'geometries' => $this->geometries,
        ];
    }

    public function isValid(): bool
    {
        if (empty($this->geometries)) {
            return false;
        }

        foreach ($this->geometries as $geometry) {
            if (! isset($geometry['type'], $geometry['coordinates'])) {
                return false;
            }
        }

        return true;
    }

    public function getCoordinates(): array
    {
        if (empty($this->geometries)) {
            return [0, 0];
        }

        // Calculate centroid of all geometries
        $lngSum = 0;
        $latSum = 0;
        $count = 0;

        foreach ($this->geometries as $geometry) {
            $centroid = $this->getGeometryCentroid($geometry);
            if ($centroid) {
                $lngSum += $centroid[0];
                $latSum += $centroid[1];
                $count++;
            }
        }

        if ($count === 0) {
            return [0, 0];
        }

        // Return as [lat, lng] for Leaflet
        return [$latSum / $count, $lngSum / $count];
    }

    /**
     * Get the centroid of a single geometry.
     *
     * @param  array{type: string, coordinates: array}  $geometry
     * @return array{0: float, 1: float}|null [lng, lat]
     */
    protected function getGeometryCentroid(array $geometry): ?array
    {
        $coords = $geometry['coordinates'];

        switch ($geometry['type']) {
            case 'Point':
                return $coords;

            case 'LineString':
            case 'MultiPoint':
                return $this->averageCoordinates($coords);

            case 'Polygon':
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
     * Calculate average of coordinate pairs.
     *
     * @param  array<int, array{0: float, 1: float}>  $coords
     * @return array{0: float, 1: float}|null
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

        return [$lngSum / $count, $latSum / $count];
    }

    /**
     * Convert to GeoJSON format.
     *
     * @return array{type: string, geometries: array}
     */
    public function toGeoJson(): array
    {
        return [
            'type' => 'GeometryCollection',
            'geometries' => $this->geometries,
        ];
    }

    /**
     * Convert to WKT format.
     */
    public function toWKT(): string
    {
        if (empty($this->geometries)) {
            return 'GEOMETRYCOLLECTION EMPTY';
        }

        $wktParts = array_map(
            fn ($g) => $this->geometryToWKT($g),
            $this->geometries
        );

        return 'GEOMETRYCOLLECTION('.implode(', ', $wktParts).')';
    }

    /**
     * Convert a single geometry to WKT.
     *
     * @param  array{type: string, coordinates: array}  $geometry
     */
    protected function geometryToWKT(array $geometry): string
    {
        $type = $geometry['type'];
        $coords = $geometry['coordinates'];

        switch ($type) {
            case 'Point':
                return sprintf('POINT(%f %f)', $coords[0], $coords[1]);

            case 'LineString':
                $points = array_map(fn ($c) => sprintf('%f %f', $c[0], $c[1]), $coords);

                return 'LINESTRING('.implode(', ', $points).')';

            case 'Polygon':
                $rings = array_map(function ($ring) {
                    $points = array_map(fn ($c) => sprintf('%f %f', $c[0], $c[1]), $ring);

                    return '('.implode(', ', $points).')';
                }, $coords);

                return 'POLYGON('.implode(', ', $rings).')';

            case 'MultiPoint':
                $points = array_map(fn ($c) => sprintf('%f %f', $c[0], $c[1]), $coords);

                return 'MULTIPOINT('.implode(', ', $points).')';

            case 'MultiLineString':
                $lines = array_map(function ($line) {
                    $points = array_map(fn ($c) => sprintf('%f %f', $c[0], $c[1]), $line);

                    return '('.implode(', ', $points).')';
                }, $coords);

                return 'MULTILINESTRING('.implode(', ', $lines).')';

            case 'MultiPolygon':
                $polygons = array_map(function ($polygon) {
                    $rings = array_map(function ($ring) {
                        $points = array_map(fn ($c) => sprintf('%f %f', $c[0], $c[1]), $ring);

                        return '('.implode(', ', $points).')';
                    }, $polygon);

                    return '('.implode(', ', $rings).')';
                }, $coords);

                return 'MULTIPOLYGON('.implode(', ', $polygons).')';
        }

        return 'POINT(0 0)';
    }

    /**
     * Convert to WKT with SRID.
     */
    public function toEWKT(int $srid = 4326): string
    {
        return "SRID={$srid};".$this->toWKT();
    }

    /**
     * Get the number of geometries in the collection.
     */
    public function count(): int
    {
        return count($this->geometries);
    }
}
