<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis\Support\Geo;

use Illuminate\Support\Collection;

/**
 * GeoJsonImporter - Import GeoJSON files into application data.
 *
 * Supports:
 * - FeatureCollection
 * - Feature
 * - Geometry types (Point, LineString, Polygon, Multi*)
 *
 * @example
 * $importer = new GeoJsonImporter();
 * $features = $importer->fromFile('/path/to/file.geojson');
 * $features = $importer->fromString($geoJsonString);
 * $importer->importToModel(Location::class, $features, ['name' => 'properties.name']);
 */
class GeoJsonImporter
{
    /**
     * Property mapping for import.
     */
    protected array $propertyMapping = [];

    /**
     * Geometry column name in target model.
     */
    protected string $geometryColumn = 'location';

    /**
     * Whether to store geometry as GeoJSON or lat/lng.
     */
    protected bool $storeAsGeoJson = false;

    /**
     * Callback for custom feature transformation.
     */
    protected ?\Closure $transformer = null;

    /**
     * Parse GeoJSON from file path.
     *
     * @return Collection<int, array>
     */
    public function fromFile(string $path): Collection
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("File not found: {$path}");
        }

        $content = file_get_contents($path);

        return $this->fromString($content);
    }

    /**
     * Parse GeoJSON from string.
     *
     * @return Collection<int, array>
     */
    public function fromString(string $geoJson): Collection
    {
        $data = json_decode($geoJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: '.json_last_error_msg());
        }

        return $this->parse($data);
    }

    /**
     * Parse GeoJSON data structure.
     *
     * @return Collection<int, array>
     */
    public function parse(array $data): Collection
    {
        $type = $data['type'] ?? null;

        return match ($type) {
            'FeatureCollection' => $this->parseFeatureCollection($data),
            'Feature' => collect([$this->parseFeature($data)]),
            'Point', 'LineString', 'Polygon', 'MultiPoint', 'MultiLineString', 'MultiPolygon', 'GeometryCollection' => collect([
                $this->parseGeometry($data),
            ]),
            default => throw new \InvalidArgumentException("Unknown GeoJSON type: {$type}"),
        };
    }

    /**
     * Parse a FeatureCollection.
     */
    protected function parseFeatureCollection(array $data): Collection
    {
        $features = $data['features'] ?? [];

        return collect($features)->map(fn ($feature) => $this->parseFeature($feature));
    }

    /**
     * Parse a single Feature.
     */
    protected function parseFeature(array $feature): array
    {
        $geometry = $feature['geometry'] ?? null;
        $properties = $feature['properties'] ?? [];
        $id = $feature['id'] ?? null;

        $result = [
            'id' => $id,
            'geometry' => $geometry,
            'geometry_type' => $geometry['type'] ?? null,
            'properties' => $properties,
        ];

        // Extract coordinates for convenience
        if ($geometry && $geometry['type'] === 'Point') {
            $result['longitude'] = $geometry['coordinates'][0] ?? null;
            $result['latitude'] = $geometry['coordinates'][1] ?? null;
        }

        // Apply transformer if set
        if ($this->transformer) {
            $result = ($this->transformer)($result, $feature);
        }

        return $result;
    }

    /**
     * Parse a geometry-only GeoJSON.
     */
    protected function parseGeometry(array $geometry): array
    {
        $result = [
            'id' => null,
            'geometry' => $geometry,
            'geometry_type' => $geometry['type'],
            'properties' => [],
        ];

        if ($geometry['type'] === 'Point') {
            $result['longitude'] = $geometry['coordinates'][0] ?? null;
            $result['latitude'] = $geometry['coordinates'][1] ?? null;
        }

        return $result;
    }

    /**
     * Set property mapping for model import.
     *
     * @param  array<string, string>  $mapping  Model column => GeoJSON property path
     */
    public function mapProperties(array $mapping): static
    {
        $this->propertyMapping = $mapping;

        return $this;
    }

    /**
     * Set the geometry column name.
     */
    public function geometryColumn(string $column): static
    {
        $this->geometryColumn = $column;

        return $this;
    }

    /**
     * Store geometry as GeoJSON format.
     */
    public function storeAsGeoJson(bool $store = true): static
    {
        $this->storeAsGeoJson = $store;

        return $this;
    }

    /**
     * Set custom transformer for features.
     *
     * @param  \Closure(array $result, array $original): array  $transformer
     */
    public function transform(\Closure $transformer): static
    {
        $this->transformer = $transformer;

        return $this;
    }

    /**
     * Import features into an Eloquent model.
     *
     * @param  class-string  $modelClass
     * @param  Collection<int, array>  $features
     * @return Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    public function importToModel(string $modelClass, Collection $features): Collection
    {
        return $features->map(function ($feature) use ($modelClass) {
            $attributes = $this->mapFeatureToModel($feature);

            return $modelClass::create($attributes);
        });
    }

    /**
     * Map a feature to model attributes.
     */
    protected function mapFeatureToModel(array $feature): array
    {
        $attributes = [];

        // Map properties
        foreach ($this->propertyMapping as $column => $propertyPath) {
            $attributes[$column] = $this->getNestedValue($feature, $propertyPath);
        }

        // Map geometry
        if ($this->storeAsGeoJson) {
            $attributes[$this->geometryColumn] = $feature['geometry'];
        } elseif (isset($feature['latitude'], $feature['longitude'])) {
            $attributes[$this->geometryColumn] = [
                'lat' => $feature['latitude'],
                'lng' => $feature['longitude'],
            ];
        }

        return $attributes;
    }

    /**
     * Get nested value from array using dot notation.
     */
    protected function getNestedValue(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Validate GeoJSON structure.
     */
    public function validate(string $geoJson): array
    {
        $errors = [];

        try {
            $data = json_decode($geoJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = 'Invalid JSON format';

                return $errors;
            }

            if (! isset($data['type'])) {
                $errors[] = 'Missing "type" property';
            }

            $validTypes = ['FeatureCollection', 'Feature', 'Point', 'LineString', 'Polygon', 'MultiPoint', 'MultiLineString', 'MultiPolygon', 'GeometryCollection'];

            if (isset($data['type']) && ! in_array($data['type'], $validTypes)) {
                $errors[] = "Invalid type: {$data['type']}";
            }

            if ($data['type'] === 'FeatureCollection' && ! isset($data['features'])) {
                $errors[] = 'FeatureCollection missing "features" array';
            }

            if ($data['type'] === 'Feature' && ! isset($data['geometry'])) {
                $errors[] = 'Feature missing "geometry" property';
            }
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }
}
