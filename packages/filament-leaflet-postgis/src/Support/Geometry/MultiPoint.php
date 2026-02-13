<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis\Support\Geometry;

use Closure;
use EduardoRibeiroDev\FilamentLeafletPostgis\Enums\Color;
use EduardoRibeiroDev\FilamentLeafletPostgis\Support\Shapes\Shape;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a MultiPoint geometry (collection of points).
 */
class MultiPoint extends Shape
{
    /**
     * @var array<int, array{0: float, 1: float}> Array of [lng, lat] coordinates
     */
    protected array $points = [];

    final public function __construct(array $points = [])
    {
        $this->points = $points;
    }

    public static function make(array $points = []): static
    {
        return new static($points);
    }

    /**
     * Create from a database record.
     */
    public static function fromRecord(
        Model $record,
        string $pointsColumn = 'coordinates',
        ?string $titleColumn = 'title',
        ?string $descriptionColumn = 'description',
        ?array $popupFieldsColumns = null,
        null|string|Color $color = null,
        ?Closure $mapRecordCallback = null
    ): static {
        $points = [];

        if ($record->hasAttribute($pointsColumn)) {
            $value = $record->{$pointsColumn};

            // Handle GeoJSON format
            if (is_array($value) && isset($value['type'], $value['coordinates'])) {
                if ($value['type'] === 'MultiPoint') {
                    $points = $value['coordinates'];
                }
            } elseif (is_string($value)) {
                $decoded = json_decode($value, true);
                if (isset($decoded['coordinates'])) {
                    $points = $decoded['coordinates'];
                }
            } elseif (is_array($value)) {
                $points = $value;
            }
        }

        return (new static($points))
            ->record($record)
            ->title($record->{$titleColumn} ?? null)
            ->popupContent($record->{$descriptionColumn} ?? null)
            ->popupFields(is_array($popupFieldsColumns) ? $record->only($popupFieldsColumns) : $record->except([
                'id',
                $pointsColumn,
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
        if (($geoJson['type'] ?? null) !== 'MultiPoint') {
            throw new \InvalidArgumentException('GeoJSON type must be MultiPoint');
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

        if (! preg_match('/^MULTIPOINT\s*\((.+)\)$/i', $wkt, $matches)) {
            throw new \InvalidArgumentException('Invalid WKT for MultiPoint');
        }

        $points = [];
        $coordPairs = preg_split('/,\s*/', trim($matches[1]));

        foreach ($coordPairs as $pair) {
            // Handle both "x y" and "(x y)" formats
            $pair = trim($pair, '() ');
            $parts = preg_split('/\s+/', $pair);

            if (count($parts) >= 2) {
                $points[] = [(float) $parts[0], (float) $parts[1]];
            }
        }

        return new static($points);
    }

    /**
     * Add a point to the collection.
     */
    public function addPoint(float $lng, float $lat): static
    {
        $this->points[] = [$lng, $lat];

        return $this;
    }

    /**
     * Get all points.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    public function getType(): string
    {
        return 'multipoint';
    }

    protected function getShapeData(): array
    {
        return [
            'points' => $this->points,
        ];
    }

    public function isValid(): bool
    {
        return count($this->points) >= 1;
    }

    public function getCoordinates(): array
    {
        if (empty($this->points)) {
            return [0, 0];
        }

        // Calculate centroid
        $lngSum = 0;
        $latSum = 0;
        $count = count($this->points);

        foreach ($this->points as $point) {
            $lngSum += $point[0];
            $latSum += $point[1];
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
            'type' => 'MultiPoint',
            'coordinates' => $this->points,
        ];
    }

    /**
     * Convert to WKT format.
     */
    public function toWKT(): string
    {
        if (empty($this->points)) {
            return 'MULTIPOINT EMPTY';
        }

        $coords = array_map(
            fn ($p) => sprintf('%f %f', $p[0], $p[1]),
            $this->points
        );

        return 'MULTIPOINT('.implode(', ', $coords).')';
    }

    /**
     * Convert to WKT with SRID.
     */
    public function toEWKT(int $srid = 4326): string
    {
        return "SRID={$srid};".$this->toWKT();
    }
}
