<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis\Support\Geometry;

use Closure;
use EduardoRibeiroDev\FilamentLeafletPostgis\Enums\Color;
use EduardoRibeiroDev\FilamentLeafletPostgis\Support\Shapes\Shape;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a MultiLineString geometry (collection of lines).
 */
class MultiLineString extends Shape
{
    /**
     * @var array<int, array<int, array{0: float, 1: float}>> Array of line arrays
     */
    protected array $lines = [];

    final public function __construct(array $lines = [])
    {
        $this->lines = $lines;
    }

    public static function make(array $lines = []): static
    {
        return new static($lines);
    }

    /**
     * Create from a database record.
     */
    public static function fromRecord(
        Model $record,
        string $linesColumn = 'coordinates',
        ?string $titleColumn = 'title',
        ?string $descriptionColumn = 'description',
        ?array $popupFieldsColumns = null,
        null|string|Color $color = null,
        ?Closure $mapRecordCallback = null
    ): static {
        $lines = [];

        if ($record->hasAttribute($linesColumn)) {
            $value = $record->{$linesColumn};

            // Handle GeoJSON format
            if (is_array($value) && isset($value['type'], $value['coordinates'])) {
                if ($value['type'] === 'MultiLineString') {
                    $lines = $value['coordinates'];
                }
            } elseif (is_string($value)) {
                $decoded = json_decode($value, true);
                if (isset($decoded['coordinates'])) {
                    $lines = $decoded['coordinates'];
                }
            } elseif (is_array($value)) {
                $lines = $value;
            }
        }

        return (new static($lines))
            ->record($record)
            ->title($record->{$titleColumn} ?? null)
            ->popupContent($record->{$descriptionColumn} ?? null)
            ->popupFields(is_array($popupFieldsColumns) ? $record->only($popupFieldsColumns) : $record->except([
                'id',
                $linesColumn,
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
        if (($geoJson['type'] ?? null) !== 'MultiLineString') {
            throw new \InvalidArgumentException('GeoJSON type must be MultiLineString');
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

        if (! preg_match('/^MULTILINESTRING\s*\((.+)\)$/i', $wkt, $matches)) {
            throw new \InvalidArgumentException('Invalid WKT for MultiLineString');
        }

        $lines = [];
        $lineStrings = preg_split('/\),\s*\(/', trim($matches[1], '()'));

        foreach ($lineStrings as $lineString) {
            $points = [];
            $coordPairs = preg_split('/,\s*/', trim($lineString));

            foreach ($coordPairs as $pair) {
                $parts = preg_split('/\s+/', trim($pair));
                if (count($parts) >= 2) {
                    $points[] = [(float) $parts[0], (float) $parts[1]];
                }
            }

            if (! empty($points)) {
                $lines[] = $points;
            }
        }

        return new static($lines);
    }

    /**
     * Add a line to the collection.
     *
     * @param  array<int, array{0: float, 1: float}>  $points
     */
    public function addLine(array $points): static
    {
        $this->lines[] = $points;

        return $this;
    }

    /**
     * Get all lines.
     *
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    public function getType(): string
    {
        return 'multilinestring';
    }

    protected function getShapeData(): array
    {
        return [
            'lines' => $this->lines,
        ];
    }

    public function isValid(): bool
    {
        if (empty($this->lines)) {
            return false;
        }

        foreach ($this->lines as $line) {
            if (count($line) < 2) {
                return false;
            }
        }

        return true;
    }

    public function getCoordinates(): array
    {
        if (empty($this->lines)) {
            return [0, 0];
        }

        // Calculate centroid of all points
        $lngSum = 0;
        $latSum = 0;
        $count = 0;

        foreach ($this->lines as $line) {
            foreach ($line as $point) {
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
            'type' => 'MultiLineString',
            'coordinates' => $this->lines,
        ];
    }

    /**
     * Convert to WKT format.
     */
    public function toWKT(): string
    {
        if (empty($this->lines)) {
            return 'MULTILINESTRING EMPTY';
        }

        $lineStrings = array_map(function ($line) {
            $coords = array_map(
                fn ($p) => sprintf('%f %f', $p[0], $p[1]),
                $line
            );

            return '('.implode(', ', $coords).')';
        }, $this->lines);

        return 'MULTILINESTRING('.implode(', ', $lineStrings).')';
    }

    /**
     * Convert to WKT with SRID.
     */
    public function toEWKT(int $srid = 4326): string
    {
        return "SRID={$srid};".$this->toWKT();
    }

    /**
     * Calculate total length in kilometers (approximate using Haversine).
     */
    public function calculateLength(): float
    {
        $totalLength = 0;
        $earthRadius = 6371; // km

        foreach ($this->lines as $line) {
            for ($i = 0; $i < count($line) - 1; $i++) {
                $lng1 = deg2rad($line[$i][0]);
                $lat1 = deg2rad($line[$i][1]);
                $lng2 = deg2rad($line[$i + 1][0]);
                $lat2 = deg2rad($line[$i + 1][1]);

                $dlat = $lat2 - $lat1;
                $dlng = $lng2 - $lng1;

                $a = sin($dlat / 2) * sin($dlat / 2) +
                    cos($lat1) * cos($lat2) *
                    sin($dlng / 2) * sin($dlng / 2);

                $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
                $totalLength += $earthRadius * $c;
            }
        }

        return $totalLength;
    }
}
