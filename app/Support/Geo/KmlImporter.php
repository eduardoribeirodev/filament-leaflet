<?php

declare(strict_types=1);

namespace App\Support\Geo;

use Illuminate\Support\Collection;

/**
 * KmlImporter - Import KML/KMZ files into application data.
 *
 * Supports:
 * - Placemarks with Point, LineString, Polygon
 * - Folders and Documents
 * - Extended data and descriptions
 * - KMZ (compressed KML) files
 *
 * @example
 * $importer = new KmlImporter();
 * $features = $importer->fromFile('/path/to/file.kml');
 * $features = $importer->fromKmz('/path/to/file.kmz');
 */
class KmlImporter
{
    /**
     * Whether to parse extended data.
     */
    protected bool $parseExtendedData = true;

    /**
     * Whether to strip HTML from descriptions.
     */
    protected bool $stripHtml = false;

    /**
     * Parse KML from file path.
     *
     * @return Collection<int, array>
     */
    public function fromFile(string $path): Collection
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("File not found: {$path}");
        }

        // Check if KMZ
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'kmz') {
            return $this->fromKmz($path);
        }

        $content = file_get_contents($path);

        return $this->fromString($content);
    }

    /**
     * Parse KMZ file (compressed KML).
     *
     * @return Collection<int, array>
     */
    public function fromKmz(string $path): Collection
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("File not found: {$path}");
        }

        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            throw new \RuntimeException("Failed to open KMZ file: {$path}");
        }

        // Find doc.kml or any .kml file
        $kmlContent = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'kml') {
                $kmlContent = $zip->getFromIndex($i);
                break;
            }
        }

        $zip->close();

        if ($kmlContent === null) {
            throw new \RuntimeException('No KML file found in KMZ archive');
        }

        return $this->fromString($kmlContent);
    }

    /**
     * Parse KML from string.
     *
     * @return Collection<int, array>
     */
    public function fromString(string $kml): Collection
    {
        // Suppress XML errors and handle them manually
        $previousErrors = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($kml);

            if ($xml === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                throw new \InvalidArgumentException('Invalid KML: '.($errors[0]->message ?? 'Unknown error'));
            }

            // Register KML namespace
            $xml->registerXPathNamespace('kml', 'http://www.opengis.net/kml/2.2');

            return $this->parseDocument($xml);
        } finally {
            libxml_use_internal_errors($previousErrors);
        }
    }

    /**
     * Enable/disable extended data parsing.
     */
    public function parseExtendedData(bool $parse = true): static
    {
        $this->parseExtendedData = $parse;

        return $this;
    }

    /**
     * Enable/disable HTML stripping from descriptions.
     */
    public function stripHtml(bool $strip = true): static
    {
        $this->stripHtml = $strip;

        return $this;
    }

    /**
     * Parse KML document.
     */
    protected function parseDocument(\SimpleXMLElement $xml): Collection
    {
        $features = collect();

        // Find all Placemarks (with or without namespace)
        $placemarks = $xml->xpath('//kml:Placemark') ?: $xml->xpath('//Placemark') ?: [];

        foreach ($placemarks as $placemark) {
            $feature = $this->parsePlacemark($placemark);

            if ($feature) {
                $features->push($feature);
            }
        }

        return $features;
    }

    /**
     * Parse a single Placemark.
     */
    protected function parsePlacemark(\SimpleXMLElement $placemark): ?array
    {
        $feature = [
            'name' => (string) ($placemark->name ?? ''),
            'description' => $this->parseDescription($placemark),
            'geometry' => null,
            'geometry_type' => null,
            'properties' => [],
        ];

        // Parse geometry
        if ($placemark->Point) {
            $feature['geometry'] = $this->parsePoint($placemark->Point);
            $feature['geometry_type'] = 'Point';

            if ($feature['geometry']) {
                $feature['longitude'] = $feature['geometry']['coordinates'][0];
                $feature['latitude'] = $feature['geometry']['coordinates'][1];
            }
        } elseif ($placemark->LineString) {
            $feature['geometry'] = $this->parseLineString($placemark->LineString);
            $feature['geometry_type'] = 'LineString';
        } elseif ($placemark->Polygon) {
            $feature['geometry'] = $this->parsePolygon($placemark->Polygon);
            $feature['geometry_type'] = 'Polygon';
        } elseif ($placemark->MultiGeometry) {
            $feature['geometry'] = $this->parseMultiGeometry($placemark->MultiGeometry);
            $feature['geometry_type'] = 'GeometryCollection';
        }

        // Parse extended data
        if ($this->parseExtendedData && $placemark->ExtendedData) {
            $feature['properties'] = array_merge(
                $feature['properties'],
                $this->parseExtendedDataElement($placemark->ExtendedData)
            );
        }

        // Add style reference if present
        if ($placemark->styleUrl) {
            $feature['properties']['styleUrl'] = (string) $placemark->styleUrl;
        }

        return $feature;
    }

    /**
     * Parse description element.
     */
    protected function parseDescription(\SimpleXMLElement $placemark): string
    {
        $description = (string) ($placemark->description ?? '');

        if ($this->stripHtml) {
            $description = strip_tags($description);
            $description = html_entity_decode($description);
            $description = trim($description);
        }

        return $description;
    }

    /**
     * Parse Point geometry.
     */
    protected function parsePoint(\SimpleXMLElement $point): ?array
    {
        $coordinates = $this->parseCoordinates((string) $point->coordinates);

        if (empty($coordinates)) {
            return null;
        }

        return [
            'type' => 'Point',
            'coordinates' => $coordinates[0],
        ];
    }

    /**
     * Parse LineString geometry.
     */
    protected function parseLineString(\SimpleXMLElement $lineString): ?array
    {
        $coordinates = $this->parseCoordinates((string) $lineString->coordinates);

        if (empty($coordinates)) {
            return null;
        }

        return [
            'type' => 'LineString',
            'coordinates' => $coordinates,
        ];
    }

    /**
     * Parse Polygon geometry.
     */
    protected function parsePolygon(\SimpleXMLElement $polygon): ?array
    {
        $rings = [];

        // Outer boundary
        if ($polygon->outerBoundaryIs && $polygon->outerBoundaryIs->LinearRing) {
            $coords = $this->parseCoordinates((string) $polygon->outerBoundaryIs->LinearRing->coordinates);

            if (! empty($coords)) {
                $rings[] = $coords;
            }
        }

        // Inner boundaries (holes)
        foreach ($polygon->innerBoundaryIs as $inner) {
            if ($inner->LinearRing) {
                $coords = $this->parseCoordinates((string) $inner->LinearRing->coordinates);

                if (! empty($coords)) {
                    $rings[] = $coords;
                }
            }
        }

        if (empty($rings)) {
            return null;
        }

        return [
            'type' => 'Polygon',
            'coordinates' => $rings,
        ];
    }

    /**
     * Parse MultiGeometry.
     */
    protected function parseMultiGeometry(\SimpleXMLElement $multi): ?array
    {
        $geometries = [];

        foreach ($multi->children() as $child) {
            $geometry = match ($child->getName()) {
                'Point' => $this->parsePoint($child),
                'LineString' => $this->parseLineString($child),
                'Polygon' => $this->parsePolygon($child),
                default => null,
            };

            if ($geometry) {
                $geometries[] = $geometry;
            }
        }

        if (empty($geometries)) {
            return null;
        }

        return [
            'type' => 'GeometryCollection',
            'geometries' => $geometries,
        ];
    }

    /**
     * Parse KML coordinates string.
     *
     * @return array<int, array{0: float, 1: float, 2?: float}>
     */
    protected function parseCoordinates(string $coordString): array
    {
        $coordString = trim($coordString);

        if (empty($coordString)) {
            return [];
        }

        $coordinates = [];
        $tuples = preg_split('/\s+/', $coordString);

        foreach ($tuples as $tuple) {
            $parts = explode(',', trim($tuple));

            if (count($parts) >= 2) {
                $coord = [
                    (float) $parts[0], // longitude
                    (float) $parts[1], // latitude
                ];

                // Include altitude if present
                if (isset($parts[2]) && $parts[2] !== '') {
                    $coord[] = (float) $parts[2];
                }

                $coordinates[] = $coord;
            }
        }

        return $coordinates;
    }

    /**
     * Parse ExtendedData element.
     */
    protected function parseExtendedDataElement(\SimpleXMLElement $extendedData): array
    {
        $data = [];

        // Parse Data elements
        foreach ($extendedData->Data as $dataElement) {
            $name = (string) $dataElement['name'];
            $value = (string) $dataElement->value;
            $data[$name] = $value;
        }

        // Parse SchemaData elements
        foreach ($extendedData->SchemaData as $schemaData) {
            foreach ($schemaData->SimpleData as $simpleData) {
                $name = (string) $simpleData['name'];
                $value = (string) $simpleData;
                $data[$name] = $value;
            }
        }

        return $data;
    }

    /**
     * Convert to GeoJSON format.
     */
    public function toGeoJson(Collection $features): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => $features->map(function ($feature) {
                return [
                    'type' => 'Feature',
                    'geometry' => $feature['geometry'],
                    'properties' => array_merge(
                        ['name' => $feature['name'], 'description' => $feature['description']],
                        $feature['properties']
                    ),
                ];
            })->toArray(),
        ];
    }
}
