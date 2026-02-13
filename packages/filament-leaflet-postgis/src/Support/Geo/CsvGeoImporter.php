<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis\Support\Geo;

use Illuminate\Support\Collection;

/**
 * CsvGeoImporter - Import CSV files with geographic coordinates.
 *
 * Supports various coordinate formats:
 * - Separate lat/lng columns
 * - Combined coordinate column (various formats)
 * - DMS (Degrees Minutes Seconds) format
 *
 * @example
 * $importer = new CsvGeoImporter();
 * $features = $importer
 *     ->latitudeColumn('lat')
 *     ->longitudeColumn('lng')
 *     ->fromFile('/path/to/file.csv');
 */
class CsvGeoImporter
{
    /**
     * Latitude column name.
     */
    protected string $latitudeColumn = 'latitude';

    /**
     * Longitude column name.
     */
    protected string $longitudeColumn = 'longitude';

    /**
     * Combined coordinate column (if using single column).
     */
    protected ?string $coordinateColumn = null;

    /**
     * CSV delimiter.
     */
    protected string $delimiter = ',';

    /**
     * CSV enclosure.
     */
    protected string $enclosure = '"';

    /**
     * CSV escape character.
     */
    protected string $escape = '\\';

    /**
     * Whether first row is header.
     */
    protected bool $hasHeader = true;

    /**
     * Custom column mapping.
     */
    protected array $columnMapping = [];

    /**
     * Skip invalid rows.
     */
    protected bool $skipInvalid = true;

    /**
     * Encoding of the file.
     */
    protected string $encoding = 'UTF-8';

    /**
     * Parse CSV from file path.
     *
     * @return Collection<int, array>
     */
    public function fromFile(string $path): Collection
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("File not found: {$path}");
        }

        $content = file_get_contents($path);

        // Handle encoding
        if ($this->encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $this->encoding);
        }

        return $this->fromString($content);
    }

    /**
     * Parse CSV from string.
     *
     * @return Collection<int, array>
     */
    public function fromString(string $csv): Collection
    {
        $rows = collect();
        $lines = str_getcsv($csv, "\n");
        $headers = null;

        foreach ($lines as $index => $line) {
            if (empty(trim($line))) {
                continue;
            }

            $values = str_getcsv($line, $this->delimiter, $this->enclosure, $this->escape);

            if ($this->hasHeader && $headers === null) {
                $headers = array_map('trim', $values);

                continue;
            }

            if ($headers) {
                $row = array_combine($headers, $values);
            } else {
                $row = $values;
            }

            $parsed = $this->parseRow($row, $index);

            if ($parsed !== null) {
                $rows->push($parsed);
            }
        }

        return $rows;
    }

    /**
     * Set latitude column name.
     */
    public function latitudeColumn(string $column): static
    {
        $this->latitudeColumn = $column;

        return $this;
    }

    /**
     * Set longitude column name.
     */
    public function longitudeColumn(string $column): static
    {
        $this->longitudeColumn = $column;

        return $this;
    }

    /**
     * Set combined coordinate column.
     */
    public function coordinateColumn(string $column): static
    {
        $this->coordinateColumn = $column;

        return $this;
    }

    /**
     * Set CSV delimiter.
     */
    public function delimiter(string $delimiter): static
    {
        $this->delimiter = $delimiter;

        return $this;
    }

    /**
     * Set CSV enclosure.
     */
    public function enclosure(string $enclosure): static
    {
        $this->enclosure = $enclosure;

        return $this;
    }

    /**
     * Set whether first row is header.
     */
    public function hasHeader(bool $hasHeader = true): static
    {
        $this->hasHeader = $hasHeader;

        return $this;
    }

    /**
     * Set custom column mapping.
     */
    public function mapColumns(array $mapping): static
    {
        $this->columnMapping = $mapping;

        return $this;
    }

    /**
     * Set whether to skip invalid rows.
     */
    public function skipInvalid(bool $skip = true): static
    {
        $this->skipInvalid = $skip;

        return $this;
    }

    /**
     * Set file encoding.
     */
    public function encoding(string $encoding): static
    {
        $this->encoding = $encoding;

        return $this;
    }

    /**
     * Use semicolon delimiter (common in European CSVs).
     */
    public function europeanFormat(): static
    {
        $this->delimiter = ';';

        return $this;
    }

    /**
     * Use tab delimiter.
     */
    public function tabDelimited(): static
    {
        $this->delimiter = "\t";

        return $this;
    }

    /**
     * Parse a single row.
     */
    protected function parseRow(array $row, int $index): ?array
    {
        $latitude = null;
        $longitude = null;

        // Try combined coordinate column first
        if ($this->coordinateColumn && isset($row[$this->coordinateColumn])) {
            $coords = $this->parseCoordinateString($row[$this->coordinateColumn]);

            if ($coords) {
                $latitude = $coords['lat'];
                $longitude = $coords['lng'];
            }
        }

        // Fall back to separate columns
        if ($latitude === null || $longitude === null) {
            $latitude = $this->extractCoordinate($row, $this->latitudeColumn);
            $longitude = $this->extractCoordinate($row, $this->longitudeColumn);
        }

        // Validate coordinates
        if ($latitude === null || $longitude === null) {
            if ($this->skipInvalid) {
                return null;
            }
            throw new \InvalidArgumentException("Invalid coordinates at row {$index}");
        }

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            if ($this->skipInvalid) {
                return null;
            }
            throw new \InvalidArgumentException("Coordinates out of range at row {$index}");
        }

        // Build result
        $result = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$longitude, $latitude],
            ],
            'properties' => [],
        ];

        // Map other columns to properties
        foreach ($row as $key => $value) {
            if ($key === $this->latitudeColumn || $key === $this->longitudeColumn || $key === $this->coordinateColumn) {
                continue;
            }

            $mappedKey = $this->columnMapping[$key] ?? $key;
            $result['properties'][$mappedKey] = $value;
        }

        return $result;
    }

    /**
     * Extract coordinate from row.
     */
    protected function extractCoordinate(array $row, string $column): ?float
    {
        if (! isset($row[$column])) {
            return null;
        }

        $value = trim((string) $row[$column]);

        if ($value === '') {
            return null;
        }

        // Try parsing as DMS
        if (preg_match('/[°\'"]|[NSEW]/i', $value)) {
            return $this->parseDMS($value);
        }

        // Try parsing as decimal
        // Handle both . and , as decimal separator
        $value = str_replace(',', '.', $value);

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * Parse coordinate string (combined lat/lng).
     *
     * @return array{lat: float, lng: float}|null
     */
    protected function parseCoordinateString(string $value): ?array
    {
        $value = trim($value);

        // GeoJSON format: [lng, lat]
        if (preg_match('/^\[?\s*(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)\s*\]?$/', $value, $matches)) {
            // Determine if it's [lng, lat] or lat, lng based on value ranges
            $first = (float) $matches[1];
            $second = (float) $matches[2];

            // If first value is out of latitude range, assume [lng, lat]
            if (abs($first) > 90) {
                return ['lat' => $second, 'lng' => $first];
            }

            // Default to lat, lng
            return ['lat' => $first, 'lng' => $second];
        }

        // "lat lng" or "lat, lng" format
        if (preg_match('/^(-?\d+\.?\d*)[,\s]+(-?\d+\.?\d*)$/', $value, $matches)) {
            return [
                'lat' => (float) $matches[1],
                'lng' => (float) $matches[2],
            ];
        }

        // DMS format with cardinal directions
        if (preg_match('/([NS])/i', $value) && preg_match('/([EW])/i', $value)) {
            return $this->parseDMSPair($value);
        }

        return null;
    }

    /**
     * Parse DMS (Degrees Minutes Seconds) string.
     */
    protected function parseDMS(string $dms): ?float
    {
        // Match patterns like: 40°26'46"N, 40 26 46 N, 40d26m46sN
        $pattern = '/(-?)(\d+)[°d\s]+(\d+)[\'′m\s]+(\d+\.?\d*)[\"″s]?\s*([NSEW])?/i';

        if (! preg_match($pattern, $dms, $matches)) {
            // Try simpler pattern: 40.446° N
            $simplePattern = '/(-?)(\d+\.?\d*)[°]?\s*([NSEW])?/i';

            if (preg_match($simplePattern, $dms, $matches)) {
                $decimal = (float) $matches[2];
                $direction = strtoupper($matches[3] ?? '');

                if ($direction === 'S' || $direction === 'W') {
                    $decimal = -$decimal;
                }

                return $decimal;
            }

            return null;
        }

        $sign = $matches[1] === '-' ? -1 : 1;
        $degrees = (int) $matches[2];
        $minutes = (int) $matches[3];
        $seconds = (float) $matches[4];
        $direction = strtoupper($matches[5] ?? '');

        $decimal = $sign * ($degrees + $minutes / 60 + $seconds / 3600);

        if ($direction === 'S' || $direction === 'W') {
            $decimal = -abs($decimal);
        }

        return $decimal;
    }

    /**
     * Parse DMS pair (lat and lng together).
     *
     * @return array{lat: float, lng: float}|null
     */
    protected function parseDMSPair(string $value): ?array
    {
        // Split by cardinal direction
        $parts = preg_split('/([NSEW])/i', $value, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if (count($parts) < 4) {
            return null;
        }

        $lat = null;
        $lng = null;

        for ($i = 0; $i < count($parts) - 1; $i += 2) {
            $number = trim($parts[$i]);
            $direction = strtoupper(trim($parts[$i + 1]));

            $parsed = $this->parseDMS($number.$direction);

            if ($direction === 'N' || $direction === 'S') {
                $lat = $parsed;
            } elseif ($direction === 'E' || $direction === 'W') {
                $lng = $parsed;
            }
        }

        if ($lat !== null && $lng !== null) {
            return ['lat' => $lat, 'lng' => $lng];
        }

        return null;
    }

    /**
     * Convert to GeoJSON.
     */
    public function toGeoJson(Collection $features): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => $features->map(fn ($f) => [
                'type' => 'Feature',
                'geometry' => $f['geometry'],
                'properties' => $f['properties'],
            ])->toArray(),
        ];
    }

    /**
     * Get validation errors for a CSV.
     */
    public function validate(string $csv): array
    {
        $errors = [];
        $lines = str_getcsv($csv, "\n");
        $headers = null;
        $rowNum = 0;

        foreach ($lines as $line) {
            $rowNum++;

            if (empty(trim($line))) {
                continue;
            }

            $values = str_getcsv($line, $this->delimiter, $this->enclosure, $this->escape);

            if ($this->hasHeader && $headers === null) {
                $headers = array_map('trim', $values);

                // Validate headers
                if ($this->coordinateColumn === null) {
                    if (! in_array($this->latitudeColumn, $headers)) {
                        $errors[] = "Missing latitude column: {$this->latitudeColumn}";
                    }
                    if (! in_array($this->longitudeColumn, $headers)) {
                        $errors[] = "Missing longitude column: {$this->longitudeColumn}";
                    }
                } else {
                    if (! in_array($this->coordinateColumn, $headers)) {
                        $errors[] = "Missing coordinate column: {$this->coordinateColumn}";
                    }
                }

                continue;
            }

            if ($headers && count($values) !== count($headers)) {
                $errors[] = "Row {$rowNum}: Column count mismatch (expected ".count($headers).', got '.count($values).')';
            }
        }

        return $errors;
    }
}
