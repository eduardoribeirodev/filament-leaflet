<?php

declare(strict_types=1);

namespace App\Support\Spatial;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * ViewportLoader - Efficient viewport-based data loading for maps.
 *
 * Provides:
 * - Viewport-based query filtering
 * - Automatic clustering for dense areas
 * - Progressive loading for large datasets
 * - Server-side simplification
 *
 * @example
 * $loader = new ViewportLoader(Infrastructure::class);
 * $data = $loader
 *     ->geometryColumn('location')
 *     ->withinBounds($request->bounds)
 *     ->cluster(true, 50)
 *     ->limit(1000)
 *     ->load();
 */
class ViewportLoader
{
    /**
     * Model class to query.
     */
    protected string $modelClass;

    /**
     * Geometry column name.
     */
    protected string $geometryColumn = 'location';

    /**
     * Current viewport bounds.
     */
    protected ?array $bounds = null;

    /**
     * Current zoom level.
     */
    protected ?int $zoom = null;

    /**
     * Enable clustering.
     */
    protected bool $clustering = false;

    /**
     * Cluster radius in pixels.
     */
    protected int $clusterRadius = 50;

    /**
     * Maximum results to return.
     */
    protected int $limit = 1000;

    /**
     * Columns to select.
     */
    protected array $columns = ['*'];

    /**
     * Custom query constraints.
     */
    protected ?\Closure $constraints = null;

    /**
     * Enable caching.
     */
    protected bool $caching = false;

    /**
     * Cache TTL.
     */
    protected int $cacheTtl = 300;

    /**
     * Simplification tolerance (for polylines/polygons).
     */
    protected ?float $simplifyTolerance = null;

    /**
     * Create a new ViewportLoader instance.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
    }

    /**
     * Create a new instance.
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function make(string $modelClass): static
    {
        return new static($modelClass);
    }

    /**
     * Set the geometry column.
     */
    public function geometryColumn(string $column): static
    {
        $this->geometryColumn = $column;

        return $this;
    }

    /**
     * Set viewport bounds.
     *
     * @param  array{minLat: float, maxLat: float, minLng: float, maxLng: float}  $bounds
     */
    public function withinBounds(array $bounds): static
    {
        $this->bounds = $bounds;

        return $this;
    }

    /**
     * Set viewport from request.
     */
    public function fromRequest(Request $request): static
    {
        if ($request->has('bounds')) {
            $bounds = $request->input('bounds');

            if (is_string($bounds)) {
                $bounds = json_decode($bounds, true);
            }

            $this->bounds = $bounds;
        }

        if ($request->has('zoom')) {
            $this->zoom = (int) $request->input('zoom');
        }

        return $this;
    }

    /**
     * Set zoom level.
     */
    public function zoom(int $zoom): static
    {
        $this->zoom = $zoom;

        return $this;
    }

    /**
     * Enable clustering.
     */
    public function cluster(bool $enabled = true, int $radius = 50): static
    {
        $this->clustering = $enabled;
        $this->clusterRadius = $radius;

        return $this;
    }

    /**
     * Set maximum results.
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Set columns to select.
     */
    public function select(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Add custom query constraints.
     */
    public function where(\Closure $constraints): static
    {
        $this->constraints = $constraints;

        return $this;
    }

    /**
     * Enable caching.
     */
    public function cache(bool $enabled = true, int $ttl = 300): static
    {
        $this->caching = $enabled;
        $this->cacheTtl = $ttl;

        return $this;
    }

    /**
     * Set simplification tolerance for geometries.
     */
    public function simplify(float $tolerance): static
    {
        $this->simplifyTolerance = $tolerance;

        return $this;
    }

    /**
     * Load data for the viewport.
     */
    public function load(): Collection
    {
        if ($this->caching && $this->bounds) {
            $cache = new SpatialCache;

            return $cache->ttl($this->cacheTtl)->viewport(
                $this->modelClass,
                $this->bounds,
                fn () => $this->executeQuery()
            );
        }

        return $this->executeQuery();
    }

    /**
     * Load and return as JSON response.
     */
    public function toResponse(): JsonResponse
    {
        $data = $this->load();

        if ($this->clustering && $this->shouldCluster($data)) {
            $clustered = $this->clusterResults($data);

            return response()->json([
                'type' => 'clustered',
                'clusters' => $clustered['clusters'],
                'points' => $clustered['points'],
                'total' => $data->count(),
            ]);
        }

        return response()->json([
            'type' => 'points',
            'data' => $data,
            'total' => $data->count(),
        ]);
    }

    /**
     * Load as GeoJSON FeatureCollection.
     */
    public function toGeoJson(): array
    {
        $data = $this->load();

        $features = $data->map(function ($item) {
            $geometry = $this->extractGeometry($item);

            if (! $geometry) {
                return null;
            }

            return [
                'type' => 'Feature',
                'id' => $item->getKey(),
                'geometry' => $geometry,
                'properties' => $this->extractProperties($item),
            ];
        })->filter()->values()->toArray();

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    /**
     * Execute the query.
     */
    protected function executeQuery(): Collection
    {
        $query = $this->modelClass::query();

        // Select columns
        $query->select($this->columns);

        // Apply bounds filter
        if ($this->bounds) {
            $query = $this->applyBoundsFilter($query);
        }

        // Apply custom constraints
        if ($this->constraints) {
            ($this->constraints)($query);
        }

        // Limit results
        $query->limit($this->limit);

        return $query->get();
    }

    /**
     * Apply bounds filter to query.
     */
    protected function applyBoundsFilter(Builder $query): Builder
    {
        $minLat = $this->bounds['minLat'];
        $maxLat = $this->bounds['maxLat'];
        $minLng = $this->bounds['minLng'];
        $maxLng = $this->bounds['maxLng'];

        // Check if we have separate lat/lng columns
        $model = new $this->modelClass;

        if ($model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), 'latitude')) {
            return $query
                ->whereBetween('latitude', [$minLat, $maxLat])
                ->whereBetween('longitude', [$minLng, $maxLng]);
        }

        // Filter by JSON geometry column - use database-agnostic approach
        $driver = $model->getConnection()->getDriverName();
        $col = $this->geometryColumn;

        if ($driver === 'pgsql') {
            // PostgreSQL JSON syntax
            return $query->where(function ($q) use ($col, $minLat, $maxLat, $minLng, $maxLng) {
                $q->whereRaw("({$col}->>'lat')::float BETWEEN ? AND ?", [$minLat, $maxLat])
                    ->whereRaw("({$col}->>'lng')::float BETWEEN ? AND ?", [$minLng, $maxLng]);
            });
        }

        // SQLite / MySQL - use json_extract
        return $query->where(function ($q) use ($col, $minLat, $maxLat, $minLng, $maxLng) {
            $q->whereRaw("CAST(json_extract({$col}, '$.lat') AS REAL) BETWEEN ? AND ?", [$minLat, $maxLat])
                ->whereRaw("CAST(json_extract({$col}, '$.lng') AS REAL) BETWEEN ? AND ?", [$minLng, $maxLng]);
        });
    }

    /**
     * Determine if results should be clustered.
     */
    protected function shouldCluster(Collection $data): bool
    {
        if (! $this->clustering) {
            return false;
        }

        // Cluster if we have many points
        if ($data->count() > 100) {
            return true;
        }

        // Cluster based on zoom level
        if ($this->zoom !== null && $this->zoom < 14) {
            return true;
        }

        return false;
    }

    /**
     * Cluster results using grid-based clustering.
     */
    protected function clusterResults(Collection $data): array
    {
        if ($this->bounds === null) {
            return ['clusters' => [], 'points' => $data->toArray()];
        }

        // Calculate grid cell size based on cluster radius and zoom
        $latRange = $this->bounds['maxLat'] - $this->bounds['minLat'];
        $lngRange = $this->bounds['maxLng'] - $this->bounds['minLng'];

        // Assume viewport is ~1000px wide, adjust cell size by cluster radius
        $gridCols = max(1, round(1000 / $this->clusterRadius));
        $gridRows = max(1, round(600 / $this->clusterRadius));

        $cellWidth = $lngRange / $gridCols;
        $cellHeight = $latRange / $gridRows;

        $grid = [];

        foreach ($data as $item) {
            $coords = $this->extractCoordinates($item);

            if (! $coords) {
                continue;
            }

            $col = (int) floor(($coords['lng'] - $this->bounds['minLng']) / $cellWidth);
            $row = (int) floor(($coords['lat'] - $this->bounds['minLat']) / $cellHeight);

            $key = "{$row}:{$col}";

            if (! isset($grid[$key])) {
                $grid[$key] = [
                    'items' => [],
                    'sumLat' => 0,
                    'sumLng' => 0,
                ];
            }

            $grid[$key]['items'][] = $item;
            $grid[$key]['sumLat'] += $coords['lat'];
            $grid[$key]['sumLng'] += $coords['lng'];
        }

        $clusters = [];
        $points = [];

        foreach ($grid as $cell) {
            $count = count($cell['items']);

            if ($count === 1) {
                // Single point, don't cluster
                $points[] = $cell['items'][0];
            } else {
                // Create cluster
                $clusters[] = [
                    'lat' => $cell['sumLat'] / $count,
                    'lng' => $cell['sumLng'] / $count,
                    'count' => $count,
                    'ids' => collect($cell['items'])->pluck('id')->toArray(),
                ];
            }
        }

        return [
            'clusters' => $clusters,
            'points' => $points,
        ];
    }

    /**
     * Extract coordinates from an item.
     */
    protected function extractCoordinates(Model $item): ?array
    {
        $geometry = $item->{$this->geometryColumn};

        if (is_string($geometry)) {
            $geometry = json_decode($geometry, true);
        }

        if (! is_array($geometry)) {
            return null;
        }

        if (isset($geometry['lat'], $geometry['lng'])) {
            return ['lat' => (float) $geometry['lat'], 'lng' => (float) $geometry['lng']];
        }

        if (isset($geometry['latitude'], $geometry['longitude'])) {
            return ['lat' => (float) $geometry['latitude'], 'lng' => (float) $geometry['longitude']];
        }

        // GeoJSON Point
        if (isset($geometry['type'], $geometry['coordinates']) && $geometry['type'] === 'Point') {
            return ['lat' => (float) $geometry['coordinates'][1], 'lng' => (float) $geometry['coordinates'][0]];
        }

        return null;
    }

    /**
     * Extract geometry from an item.
     */
    protected function extractGeometry(Model $item): ?array
    {
        $geometry = $item->{$this->geometryColumn};

        if (is_string($geometry)) {
            $geometry = json_decode($geometry, true);
        }

        if (! is_array($geometry)) {
            return null;
        }

        // Already GeoJSON
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

        return null;
    }

    /**
     * Extract properties from an item.
     */
    protected function extractProperties(Model $item): array
    {
        $properties = $item->toArray();
        unset($properties[$this->geometryColumn]);

        return $properties;
    }

    /**
     * Get count of features in bounds without loading full data.
     */
    public function count(): int
    {
        $query = $this->modelClass::query();

        if ($this->bounds) {
            $query = $this->applyBoundsFilter($query);
        }

        if ($this->constraints) {
            ($this->constraints)($query);
        }

        return $query->count();
    }

    /**
     * Check if there's data in the bounds.
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }
}
