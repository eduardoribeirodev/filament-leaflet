<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis\Support\Map;

use Illuminate\Support\Collection;

/**
 * LayerManager - Manages multiple map layers.
 *
 * Provides a unified interface for adding, configuring, and controlling
 * multiple layer types (heatmap, WMS, clusters, vector tiles).
 *
 * @example
 * LayerManager::make()
 *     ->addHeatmap('density', fn($layer) => $layer->fromModel(Point::class))
 *     ->addWMS('satellite', 'https://wms.example.com', fn($layer) => $layer->layers('imagery'))
 *     ->addCluster('markers', fn($layer) => $layer->fromModel(Marker::class))
 *     ->setBaseLayers(['OpenStreetMap', 'Satellite'])
 *     ->toArray();
 */
class LayerManager
{
    /**
     * Collection of layers.
     */
    protected Collection $layers;

    /**
     * Base layer configurations.
     */
    protected array $baseLayers = [];

    /**
     * Default base layer.
     */
    protected ?string $defaultBaseLayer = null;

    /**
     * Layer control position.
     */
    protected string $controlPosition = 'topright';

    /**
     * Whether layer control is collapsed.
     */
    protected bool $controlCollapsed = true;

    /**
     * Create a new LayerManager instance.
     */
    public function __construct()
    {
        $this->layers = collect();
    }

    /**
     * Create a new LayerManager instance.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Add a heatmap layer.
     *
     * @param  string  $name  Layer identifier
     * @param  \Closure(HeatmapLayer): HeatmapLayer  $configure
     */
    public function addHeatmap(string $name, \Closure $configure): static
    {
        $layer = HeatmapLayer::make()->name($name);
        $configure($layer);
        $this->layers->put($name, $layer);

        return $this;
    }

    /**
     * Add a WMS layer.
     *
     * @param  string  $name  Layer identifier
     * @param  string  $baseUrl  WMS server URL
     * @param  \Closure(WMSLayer): WMSLayer  $configure
     */
    public function addWMS(string $name, string $baseUrl, \Closure $configure): static
    {
        $layer = WMSLayer::make($baseUrl)->name($name);
        $configure($layer);
        $this->layers->put($name, $layer);

        return $this;
    }

    /**
     * Add a cluster layer.
     *
     * @param  string  $name  Layer identifier
     * @param  \Closure(ClusterLayer): ClusterLayer  $configure
     */
    public function addCluster(string $name, \Closure $configure): static
    {
        $layer = ClusterLayer::make()->name($name);
        $configure($layer);
        $this->layers->put($name, $layer);

        return $this;
    }

    /**
     * Add a vector tile layer.
     *
     * @param  string  $name  Layer identifier
     * @param  string  $url  Tile URL template
     * @param  \Closure(VectorTileLayer): VectorTileLayer  $configure
     */
    public function addVectorTile(string $name, string $url, \Closure $configure): static
    {
        $layer = VectorTileLayer::make($url)->name($name);
        $configure($layer);
        $this->layers->put($name, $layer);

        return $this;
    }

    /**
     * Add a custom layer.
     */
    public function addLayer(string $name, object $layer): static
    {
        $this->layers->put($name, $layer);

        return $this;
    }

    /**
     * Remove a layer.
     */
    public function removeLayer(string $name): static
    {
        $this->layers->forget($name);

        return $this;
    }

    /**
     * Get a layer by name.
     */
    public function getLayer(string $name): ?object
    {
        return $this->layers->get($name);
    }

    /**
     * Check if a layer exists.
     */
    public function hasLayer(string $name): bool
    {
        return $this->layers->has($name);
    }

    /**
     * Set layer visibility.
     */
    public function setLayerVisibility(string $name, bool $visible): static
    {
        $layer = $this->layers->get($name);

        if ($layer && method_exists($layer, 'visible')) {
            $layer->visible($visible);
        }

        return $this;
    }

    /**
     * Set base layer options.
     */
    public function setBaseLayers(array $baseLayers): static
    {
        $this->baseLayers = $baseLayers;

        return $this;
    }

    /**
     * Add a base layer.
     */
    public function addBaseLayer(string $name, string $url, array $options = []): static
    {
        $this->baseLayers[$name] = [
            'url' => $url,
            'options' => $options,
        ];

        return $this;
    }

    /**
     * Set the default base layer.
     */
    public function defaultBaseLayer(string $name): static
    {
        $this->defaultBaseLayer = $name;

        return $this;
    }

    /**
     * Configure layer control.
     */
    public function layerControl(string $position = 'topright', bool $collapsed = true): static
    {
        $this->controlPosition = $position;
        $this->controlCollapsed = $collapsed;

        return $this;
    }

    /**
     * Get all layers.
     */
    public function getLayers(): Collection
    {
        return $this->layers;
    }

    /**
     * Get layer count.
     */
    public function count(): int
    {
        return $this->layers->count();
    }

    /**
     * Get layers by type.
     */
    public function getLayersByType(string $type): Collection
    {
        return $this->layers->filter(function ($layer) use ($type) {
            return match ($type) {
                'heatmap' => $layer instanceof HeatmapLayer,
                'wms' => $layer instanceof WMSLayer,
                'cluster' => $layer instanceof ClusterLayer,
                'vectorTile' => $layer instanceof VectorTileLayer,
                default => false,
            };
        });
    }

    /**
     * Get only visible layers.
     */
    public function getVisibleLayers(): Collection
    {
        return $this->layers->filter(function ($layer) {
            $array = $layer->toArray();

            return $array['visible'] ?? true;
        });
    }

    /**
     * Get combined bounds of all layers with markers.
     *
     * @return array{minLat: float, maxLat: float, minLng: float, maxLng: float}|null
     */
    public function getBounds(): ?array
    {
        $allBounds = [];

        foreach ($this->layers as $layer) {
            if ($layer instanceof ClusterLayer) {
                $bounds = $layer->getBounds();
                if ($bounds) {
                    $allBounds[] = $bounds;
                }
            }

            if ($layer instanceof HeatmapLayer) {
                $points = $layer->getPoints();
                if (! empty($points)) {
                    $lats = array_column($points, 0);
                    $lngs = array_column($points, 1);
                    $allBounds[] = [
                        'minLat' => min($lats),
                        'maxLat' => max($lats),
                        'minLng' => min($lngs),
                        'maxLng' => max($lngs),
                    ];
                }
            }
        }

        if (empty($allBounds)) {
            return null;
        }

        return [
            'minLat' => min(array_column($allBounds, 'minLat')),
            'maxLat' => max(array_column($allBounds, 'maxLat')),
            'minLng' => min(array_column($allBounds, 'minLng')),
            'maxLng' => max(array_column($allBounds, 'maxLng')),
        ];
    }

    /**
     * Convert to array for JavaScript.
     */
    public function toArray(): array
    {
        return [
            'layers' => $this->layers->map(fn ($layer) => $layer->toArray())->values()->toArray(),
            'baseLayers' => $this->baseLayers,
            'defaultBaseLayer' => $this->defaultBaseLayer,
            'control' => [
                'position' => $this->controlPosition,
                'collapsed' => $this->controlCollapsed,
            ],
        ];
    }

    /**
     * Convert to JSON.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
