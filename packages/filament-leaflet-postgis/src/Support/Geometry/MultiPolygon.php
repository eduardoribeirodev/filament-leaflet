<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis\Support\Geometry;

use Closure;
use EduardoRibeiroDev\FilamentLeafletPostgis\Enums\Color;
use EduardoRibeiroDev\FilamentLeafletPostgis\Support\Shapes\Shape;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a MultiPolygon geometry (collection of polygons).
 */
class MultiPolygon extends Shape
{
    /**
     * @var array<int, array<int, array<int, array{0: float, 1: float}>>> Array of polygon arrays
     */
    protected array $polygons = [];

    final public function __construct(array $polygons = [])
    {
        $this->polygons = $polygons;
    }

    public static function make(array $polygons = []): static
    {
        return new static($polygons);
    }

    /**
     * Create from a database record.
     */
    public static function fromRecord(
        Model $record,
        string $polygonsColumn = 'coordinates',
        ?string $titleColumn = 'title',
        ?string $descriptionColumn = 'description',
        ?array $popupFieldsColumns = null,
        null|string|Color $color = null,
        ?Closure $mapRecordCallback = null
    ): static {
        $polygons = [];

        if ($record->hasAttribute($polygonsColumn)) {
            $value = $record->{$polygonsColumn};

            // Handle GeoJSON format
            if (is_array($value) && isset($value['type'], $value['coordinates'])) {
                if ($value['type'] === 'MultiPolygon') {
                    $polygons = $value['coordinates'];
                }
            } elseif (is_string($value)) {
                $decoded = json_decode($value, true);
                if (isset($decoded['coordinates'])) {
                    $polygons = $decoded['coordinates'];
                }
            } elseif (is_array($value)) {
                $polygons = $value;
            }
        }

        return (new static($polygons))
            ->record($record)
            ->title($record->{$titleColumn} ?? null)
            ->popupContent($record->{$descriptionColumn} ?? null)
            ->popupFields(is_array($popupFieldsColumns) ? $record->only($popupFieldsColumns) : $record->except([
                'id',
                $polygonsColumn,
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
     * @param  array{type: string, coordinates: array}  $geoJson
     */
    public static function fromGeoJson(array $geoJson): static
    {
        if (($geoJson['type'] ?? null) !== 'MultiPolygon') {
            throw new \InvalidArgumentException('GeoJSON type must be MultiPolygon');
        }

        return new static($geoJson['coordinates'] ?? []);
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

        if (! preg_match('/^MULTIPOLYGON\s*\(\(\((.+)\)\)\)$/i', $wkt, $matches)) {
            throw new \InvalidArgumentException('Invalid WKT for MultiPolygon');
        }

        $polygons = [];
        $polygonStrings = preg_split('/\)\),\s*\(\(/', $matches[1]);

        foreach ($polygonStrings as $polygonString) {
            $rings = [];
            $ringStrings = preg_split('/\),\s*\(/', $polygonString);

            foreach ($ringStrings as $ringString) {
                $points = [];
                $coordPairs = preg_split('/,\s*/', trim($ringString, '()'));

                foreach ($coordPairs as $pair) {
                    $parts = preg_split('/\s+/', trim($pair));
                    if (count($parts) >= 2) {
                        $points[] = [(float) $parts[0], (float) $parts[1]];
                    }
                }

                if (! empty($points)) {
                    $rings[] = $points;
                }
            }

            if (! empty($rings)) {
                $polygons[] = $rings;
            }
        }

        return new static($polygons);
    }

    /**
     * Add a polygon to the collection.
     *
     * @param  array<int, array<int, array{0: float, 1: float}>>  $rings  Outer ring + optional holes
     */
    public function addPolygon(array $rings): static
    {
        $this->polygons[] = $rings;

        return $this;
    }

    /**
     * Get all polygons.
     *
     * @return array<int, array<int, array<int, array{0: float, 1: float}>>>
     */
    public function getPolygons(): array
    {
        return $this->polygons;
    }

    public function getType(): string
    {
        return 'multipolygon';
    }

    protected function getShapeData(): array
    {
        return [
            'polygons' => $this->polygons,
        ];
    }

    public function isValid(): bool
    {
        if (empty($this->polygons)) {
            return false;
        }

        foreach ($this->polygons as $polygon) {
            if (empty($polygon)) {
                return false;
            }

            // Each polygon needs at least an exterior ring with 4 points (closed)
            foreach ($polygon as $ring) {
                if (count($ring) < 4) {
                    return false;
                }
            }
        }

        return true;
    }

    public function getCoordinates(): array
    {
        if (empty($this->polygons)) {
            return [0, 0];
        }

        // Calculate centroid of all exterior ring points
        $lngSum = 0;
        $latSum = 0;
        $count = 0;

        foreach ($this->polygons as $polygon) {
            // Use only exterior ring (first ring)
            $exteriorRing = $polygon[0] ?? [];
            foreach ($exteriorRing as $point) {
                $lngSum += $point[0];
                $latSum += $point[1];
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
     * Convert to GeoJSON format.
     *
     * @return array{type: string, coordinates: array}
     */
    public function toGeoJson(): array
    {
        return [
            'type' => 'MultiPolygon',
            'coordinates' => $this->polygons,
        ];
    }

    /**
     * Convert to WKT format.
     */
    public function toWKT(): string
    {
        if (empty($this->polygons)) {
            return 'MULTIPOLYGON EMPTY';
        }

        $polygonStrings = array_map(function ($polygon) {
            $rings = array_map(function ($ring) {
                $coords = array_map(
                    fn ($p) => sprintf('%f %f', $p[0], $p[1]),
                    $ring
                );

                return '('.implode(', ', $coords).')';
            }, $polygon);

            return '('.implode(', ', $rings).')';
        }, $this->polygons);

        return 'MULTIPOLYGON('.implode(', ', $polygonStrings).')';
    }

    /**
     * Convert to WKT with SRID.
     */
    public function toEWKT(int $srid = 4326): string
    {
        return "SRID={$srid};".$this->toWKT();
    }

    /**
     * Calculate total area in square kilometers (approximate using Shoelace formula).
     */
    public function calculateArea(): float
    {
        $totalArea = 0;
        $earthRadius = 6371; // km

        foreach ($this->polygons as $polygon) {
            // Exterior ring adds area
            $exteriorRing = $polygon[0] ?? [];
            $totalArea += $this->calculateRingArea($exteriorRing, $earthRadius);

            // Interior rings (holes) subtract area
            for ($i = 1; $i < count($polygon); $i++) {
                $totalArea -= $this->calculateRingArea($polygon[$i], $earthRadius);
            }
        }

        return abs($totalArea);
    }

    /**
     * Calculate area of a single ring using spherical excess formula.
     */
    protected function calculateRingArea(array $ring, float $earthRadius): float
    {
        if (count($ring) < 4) {
            return 0;
        }

        $area = 0;
        $n = count($ring);

        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $k = ($i + 2) % $n;

            $area += deg2rad($ring[$j][0] - $ring[$i][0]) *
                (2 + sin(deg2rad($ring[$i][1])) + sin(deg2rad($ring[$j][1])));
        }

        $area = $area * $earthRadius * $earthRadius / 2;

        return abs($area);
    }

    /**
     * Get total number of polygons.
     */
    public function count(): int
    {
        return count($this->polygons);
    }
}
