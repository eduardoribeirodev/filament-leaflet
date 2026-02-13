<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis\Support\Geo;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * GeoJsonExporter - Export application data to GeoJSON format.
 *
 * @example
 * $exporter = new GeoJsonExporter();
 * $geoJson = $exporter
 *     ->fromModel(Location::class)
 *     ->geometryColumn('location')
 *     ->properties(['name', 'description', 'created_at'])
 *     ->export();
 *
 * // Or from collection
 * $geoJson = $exporter->fromCollection($locations)->export();
 */
class GeoJsonExporter
{
    /**
     * Source data.
     */
    protected Collection|EloquentCollection|null $data = null;

    /**
     * Geometry column/key name.
     */
    protected string $geometryColumn = 'location';

    /**
     * Properties to include in export.
     */
    protected array $properties = [];

    /**
     * Whether to include all properties.
     */
    protected bool $includeAllProperties = false;

    /**
     * Properties to exclude.
     */
    protected array $excludeProperties = ['password', 'remember_token'];

    /**
     * Custom geometry transformer.
     */
    protected ?\Closure $geometryTransformer = null;

    /**
     * Custom properties transformer.
     */
    protected ?\Closure $propertiesTransformer = null;

    /**
     * CRS (Coordinate Reference System) to include.
     */
    protected ?array $crs = null;

    /**
     * Whether to pretty print JSON.
     */
    protected bool $prettyPrint = false;

    /**
     * Load data from an Eloquent model.
     *
     * @param  class-string<Model>  $modelClass
     * @param  \Closure|null  $query  Optional query modifier
     */
    public function fromModel(string $modelClass, ?\Closure $query = null): static
    {
        $builder = $modelClass::query();

        if ($query) {
            $query($builder);
        }

        $this->data = $builder->get();

        return $this;
    }

    /**
     * Load data from a collection.
     */
    public function fromCollection(Collection|EloquentCollection|array $data): static
    {
        $this->data = $data instanceof Collection ? $data : collect($data);

        return $this;
    }

    /**
     * Set the geometry column/key name.
     */
    public function geometryColumn(string $column): static
    {
        $this->geometryColumn = $column;

        return $this;
    }

    /**
     * Set specific properties to include.
     */
    public function properties(array $properties): static
    {
        $this->properties = $properties;
        $this->includeAllProperties = false;

        return $this;
    }

    /**
     * Include all properties except excluded ones.
     */
    public function allProperties(): static
    {
        $this->includeAllProperties = true;

        return $this;
    }

    /**
     * Set properties to exclude.
     */
    public function except(array $properties): static
    {
        $this->excludeProperties = array_merge($this->excludeProperties, $properties);

        return $this;
    }

    /**
     * Set custom geometry transformer.
     *
     * @param  \Closure(mixed $geometry, mixed $item): array  $transformer
     */
    public function transformGeometry(\Closure $transformer): static
    {
        $this->geometryTransformer = $transformer;

        return $this;
    }

    /**
     * Set custom properties transformer.
     *
     * @param  \Closure(array $properties, mixed $item): array  $transformer
     */
    public function transformProperties(\Closure $transformer): static
    {
        $this->propertiesTransformer = $transformer;

        return $this;
    }

    /**
     * Set CRS for the output.
     */
    public function crs(string $name, string $type = 'name'): static
    {
        $this->crs = [
            'type' => $type,
            'properties' => ['name' => $name],
        ];

        return $this;
    }

    /**
     * Use WGS84 CRS (EPSG:4326).
     */
    public function wgs84(): static
    {
        return $this->crs('urn:ogc:def:crs:OGC:1.3:CRS84');
    }

    /**
     * Enable pretty printing.
     */
    public function prettyPrint(bool $pretty = true): static
    {
        $this->prettyPrint = $pretty;

        return $this;
    }

    /**
     * Export to GeoJSON string.
     */
    public function export(): string
    {
        $featureCollection = $this->toArray();

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        if ($this->prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($featureCollection, $flags);
    }

    /**
     * Export to GeoJSON array.
     */
    public function toArray(): array
    {
        if ($this->data === null) {
            throw new \RuntimeException('No data loaded. Call fromModel() or fromCollection() first.');
        }

        $features = $this->data->map(fn ($item) => $this->itemToFeature($item))->filter()->values()->toArray();

        $result = [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];

        if ($this->crs) {
            $result['crs'] = $this->crs;
        }

        return $result;
    }

    /**
     * Convert a single item to a GeoJSON Feature.
     */
    protected function itemToFeature(mixed $item): ?array
    {
        $geometry = $this->extractGeometry($item);

        if ($geometry === null) {
            return null;
        }

        $properties = $this->extractProperties($item);

        $feature = [
            'type' => 'Feature',
            'geometry' => $geometry,
            'properties' => $properties,
        ];

        // Add ID if available
        $id = $this->extractId($item);
        if ($id !== null) {
            $feature['id'] = $id;
        }

        return $feature;
    }

    /**
     * Extract geometry from item.
     */
    protected function extractGeometry(mixed $item): ?array
    {
        $geometry = $this->getValue($item, $this->geometryColumn);

        if ($geometry === null) {
            return null;
        }

        // Apply custom transformer if set
        if ($this->geometryTransformer) {
            return ($this->geometryTransformer)($geometry, $item);
        }

        // Handle different geometry formats
        return $this->normalizeGeometry($geometry);
    }

    /**
     * Normalize geometry to GeoJSON format.
     */
    protected function normalizeGeometry(mixed $geometry): ?array
    {
        if (is_string($geometry)) {
            $decoded = json_decode($geometry, true);
            if ($decoded) {
                $geometry = $decoded;
            } else {
                return null;
            }
        }

        if (! is_array($geometry)) {
            return null;
        }

        // Already GeoJSON format
        if (isset($geometry['type'], $geometry['coordinates'])) {
            return $geometry;
        }

        // lat/lng format
        if (isset($geometry['lat'], $geometry['lng'])) {
            return [
                'type' => 'Point',
                'coordinates' => [(float) $geometry['lng'], (float) $geometry['lat']],
            ];
        }

        // latitude/longitude format
        if (isset($geometry['latitude'], $geometry['longitude'])) {
            return [
                'type' => 'Point',
                'coordinates' => [(float) $geometry['longitude'], (float) $geometry['latitude']],
            ];
        }

        // Polygon with points array
        if (isset($geometry['type']) && $geometry['type'] === 'polygon' && isset($geometry['points'])) {
            $coordinates = array_map(
                fn ($p) => [(float) ($p['lng'] ?? $p['longitude']), (float) ($p['lat'] ?? $p['latitude'])],
                $geometry['points']
            );

            // Close the ring if not closed
            if ($coordinates[0] !== $coordinates[count($coordinates) - 1]) {
                $coordinates[] = $coordinates[0];
            }

            return [
                'type' => 'Polygon',
                'coordinates' => [$coordinates],
            ];
        }

        return null;
    }

    /**
     * Extract properties from item.
     */
    protected function extractProperties(mixed $item): array
    {
        $itemArray = $item instanceof Model ? $item->toArray() : (array) $item;

        // Remove geometry column from properties
        unset($itemArray[$this->geometryColumn]);

        if ($this->includeAllProperties) {
            $properties = array_diff_key($itemArray, array_flip($this->excludeProperties));
        } elseif (! empty($this->properties)) {
            $properties = array_intersect_key($itemArray, array_flip($this->properties));
        } else {
            $properties = $itemArray;
        }

        // Apply custom transformer if set
        if ($this->propertiesTransformer) {
            $properties = ($this->propertiesTransformer)($properties, $item);
        }

        return $properties;
    }

    /**
     * Extract ID from item.
     */
    protected function extractId(mixed $item): mixed
    {
        if ($item instanceof Model) {
            return $item->getKey();
        }

        return $item['id'] ?? null;
    }

    /**
     * Get a value from item.
     */
    protected function getValue(mixed $item, string $key): mixed
    {
        if ($item instanceof Model) {
            return $item->getAttribute($key);
        }

        if (is_array($item)) {
            return $item[$key] ?? null;
        }

        return $item->{$key} ?? null;
    }

    /**
     * Save to file.
     */
    public function toFile(string $path): bool
    {
        $content = $this->export();

        return file_put_contents($path, $content) !== false;
    }

    /**
     * Download as response (for Laravel controllers).
     */
    public function download(string $filename = 'export.geojson'): \Illuminate\Http\Response
    {
        $content = $this->export();

        return response($content)
            ->header('Content-Type', 'application/geo+json')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
}
