<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis\Support\Map;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * ClusterLayer - Enhanced marker clustering with viewport-based loading.
 *
 * Optimized for large datasets (>100k points) with:
 * - Server-side clustering for initial load
 * - Viewport-based lazy loading
 * - Custom cluster rendering
 * - Spiderfying configuration
 *
 * @example
 * ClusterLayer::make()
 *     ->fromModel(Infrastructure::class, 'location')
 *     ->maxClusterRadius(80)
 *     ->spiderfyOnMaxZoom(true)
 *     ->showCoverageOnHover(true)
 *     ->enableViewportLoading()
 *     ->chunkSize(1000);
 */
class ClusterLayer
{
    /**
     * Array of marker data.
     */
    protected array $markers = [];

    /**
     * Maximum radius for clustering in pixels.
     */
    protected int $maxClusterRadius = 80;

    /**
     * Whether to spiderfy clusters at max zoom.
     */
    protected bool $spiderfyOnMaxZoom = true;

    /**
     * Whether to show coverage polygon on hover.
     */
    protected bool $showCoverageOnHover = true;

    /**
     * Whether to zoom to bounds when clicking a cluster.
     */
    protected bool $zoomToBoundsOnClick = true;

    /**
     * Whether to enable single marker mode.
     */
    protected bool $singleMarkerMode = false;

    /**
     * Disable clustering at this zoom level.
     */
    protected int $disableClusteringAtZoom = 18;

    /**
     * Animate adding markers.
     */
    protected bool $animate = true;

    /**
     * Whether to animate adding markers.
     */
    protected bool $animateAddingMarkers = true;

    /**
     * Spiderfy distance multiplier.
     */
    protected int $spiderfyDistanceMultiplier = 1;

    /**
     * Maximum number of markers per cluster icon.
     */
    protected ?int $maxClusterSize = null;

    /**
     * Enable viewport-based loading.
     */
    protected bool $viewportLoading = false;

    /**
     * Chunk size for loading markers.
     */
    protected int $chunkSize = 1000;

    /**
     * URL for viewport-based loading.
     */
    protected ?string $loadUrl = null;

    /**
     * Custom icon callback.
     */
    protected ?\Closure $iconCallback = null;

    /**
     * Custom popup callback.
     */
    protected ?\Closure $popupCallback = null;

    /**
     * Whether the layer is visible.
     */
    protected bool $visible = true;

    /**
     * Layer name for controls.
     */
    protected string $name = 'Markers';

    /**
     * Icon size classes by marker count.
     */
    protected array $iconSizeClasses = [
        'small' => ['class' => 'marker-cluster-small', 'maxCount' => 10],
        'medium' => ['class' => 'marker-cluster-medium', 'maxCount' => 100],
        'large' => ['class' => 'marker-cluster-large', 'maxCount' => PHP_INT_MAX],
    ];

    /**
     * Create a new ClusterLayer instance.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Set markers directly.
     *
     * @param  array<int, array{lat: float, lng: float, ...}>  $markers
     */
    public function markers(array $markers): static
    {
        $this->markers = $markers;

        return $this;
    }

    /**
     * Load markers from an Eloquent model.
     *
     * @param  class-string<Model>  $modelClass
     * @param  string  $locationColumn  JSON column or 'lat,lng' for separate columns
     * @param  \Closure|null  $query  Optional query modifier
     * @param  \Closure|null  $transform  Optional marker transformer
     */
    public function fromModel(
        string $modelClass,
        string $locationColumn = 'location',
        ?\Closure $query = null,
        ?\Closure $transform = null
    ): static {
        $builder = $modelClass::query();

        if ($query) {
            $query($builder);
        }

        if ($this->viewportLoading) {
            // Store model info for lazy loading
            $this->markers = [
                '_lazy' => true,
                'model' => $modelClass,
                'column' => $locationColumn,
            ];

            return $this;
        }

        $records = $builder->get();

        $this->markers = $records->map(function ($record) use ($locationColumn, $transform) {
            $coords = $this->extractCoordinates($record, $locationColumn);

            if (! $coords) {
                return null;
            }

            $marker = [
                'lat' => $coords['lat'],
                'lng' => $coords['lng'],
                'id' => $record->getKey(),
            ];

            if ($transform) {
                $marker = $transform($record, $marker);
            } else {
                // Default transformation
                $marker['title'] = $record->name ?? $record->title ?? "#{$record->getKey()}";
                $marker['popup'] = $this->buildDefaultPopup($record);
            }

            return $marker;
        })->filter()->values()->toArray();

        return $this;
    }

    /**
     * Load markers from a Collection.
     */
    public function fromCollection(
        Collection $collection,
        string $locationColumn = 'location',
        ?\Closure $transform = null
    ): static {
        $this->markers = $collection->map(function ($item, $index) use ($locationColumn, $transform) {
            $coords = $this->extractCoordinates($item, $locationColumn);

            if (! $coords) {
                return null;
            }

            $marker = [
                'lat' => $coords['lat'],
                'lng' => $coords['lng'],
                'id' => $item['id'] ?? $item->id ?? $index,
            ];

            if ($transform) {
                $marker = $transform($item, $marker);
            }

            return $marker;
        })->filter()->values()->toArray();

        return $this;
    }

    /**
     * Extract coordinates from an item.
     */
    protected function extractCoordinates(mixed $item, string $locationColumn): ?array
    {
        // Handle array access
        if (is_array($item)) {
            if (isset($item[$locationColumn])) {
                return $this->parseLocation($item[$locationColumn]);
            }

            if (str_contains($locationColumn, ',')) {
                [$latCol, $lngCol] = array_map('trim', explode(',', $locationColumn));

                if (isset($item[$latCol], $item[$lngCol])) {
                    return [
                        'lat' => (float) $item[$latCol],
                        'lng' => (float) $item[$lngCol],
                    ];
                }
            }

            return null;
        }

        // Handle object/model
        if (is_object($item)) {
            if (isset($item->{$locationColumn})) {
                return $this->parseLocation($item->{$locationColumn});
            }

            if (str_contains($locationColumn, ',')) {
                [$latCol, $lngCol] = array_map('trim', explode(',', $locationColumn));

                if (isset($item->{$latCol}, $item->{$lngCol})) {
                    return [
                        'lat' => (float) $item->{$latCol},
                        'lng' => (float) $item->{$lngCol},
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Parse location value.
     */
    protected function parseLocation(mixed $location): ?array
    {
        if (is_string($location)) {
            $decoded = json_decode($location, true);
            if ($decoded) {
                $location = $decoded;
            }
        }

        if (is_array($location)) {
            if (isset($location['lat'], $location['lng'])) {
                return [
                    'lat' => (float) $location['lat'],
                    'lng' => (float) $location['lng'],
                ];
            }

            if (isset($location['latitude'], $location['longitude'])) {
                return [
                    'lat' => (float) $location['latitude'],
                    'lng' => (float) $location['longitude'],
                ];
            }

            // GeoJSON Point
            if (isset($location['type'], $location['coordinates']) && $location['type'] === 'Point') {
                return [
                    'lat' => (float) $location['coordinates'][1],
                    'lng' => (float) $location['coordinates'][0],
                ];
            }
        }

        return null;
    }

    /**
     * Build default popup content.
     */
    protected function buildDefaultPopup(Model $record): string
    {
        $content = '<div class="marker-popup">';

        if (isset($record->name) || isset($record->title)) {
            $content .= '<h4 class="font-bold">'.e($record->name ?? $record->title).'</h4>';
        }

        if (isset($record->description)) {
            $content .= '<p>'.e($record->description).'</p>';
        }

        $content .= '</div>';

        return $content;
    }

    /**
     * Set maximum cluster radius.
     */
    public function maxClusterRadius(int $radius): static
    {
        $this->maxClusterRadius = $radius;

        return $this;
    }

    /**
     * Enable/disable spiderfy on max zoom.
     */
    public function spiderfyOnMaxZoom(bool $enabled = true): static
    {
        $this->spiderfyOnMaxZoom = $enabled;

        return $this;
    }

    /**
     * Enable/disable coverage polygon on hover.
     */
    public function showCoverageOnHover(bool $show = true): static
    {
        $this->showCoverageOnHover = $show;

        return $this;
    }

    /**
     * Enable/disable zoom to bounds on click.
     */
    public function zoomToBoundsOnClick(bool $enabled = true): static
    {
        $this->zoomToBoundsOnClick = $enabled;

        return $this;
    }

    /**
     * Enable single marker mode.
     */
    public function singleMarkerMode(bool $enabled = true): static
    {
        $this->singleMarkerMode = $enabled;

        return $this;
    }

    /**
     * Set zoom level to disable clustering.
     */
    public function disableClusteringAtZoom(int $zoom): static
    {
        $this->disableClusteringAtZoom = $zoom;

        return $this;
    }

    /**
     * Enable/disable animations.
     */
    public function animate(bool $animate = true): static
    {
        $this->animate = $animate;

        return $this;
    }

    /**
     * Set spiderfy distance multiplier.
     */
    public function spiderfyDistanceMultiplier(int $multiplier): static
    {
        $this->spiderfyDistanceMultiplier = $multiplier;

        return $this;
    }

    /**
     * Enable viewport-based lazy loading.
     */
    public function enableViewportLoading(string $url = '/api/markers'): static
    {
        $this->viewportLoading = true;
        $this->loadUrl = $url;

        return $this;
    }

    /**
     * Set chunk size for loading.
     */
    public function chunkSize(int $size): static
    {
        $this->chunkSize = $size;

        return $this;
    }

    /**
     * Set custom icon callback.
     *
     * @param  \Closure(array $marker): array  $callback
     */
    public function icon(\Closure $callback): static
    {
        $this->iconCallback = $callback;

        return $this;
    }

    /**
     * Set custom popup callback.
     *
     * @param  \Closure(array $marker): string  $callback
     */
    public function popup(\Closure $callback): static
    {
        $this->popupCallback = $callback;

        return $this;
    }

    /**
     * Set visibility.
     */
    public function visible(bool $visible = true): static
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Set layer name.
     */
    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set custom icon size classes.
     */
    public function iconSizeClasses(array $classes): static
    {
        $this->iconSizeClasses = $classes;

        return $this;
    }

    /**
     * Get marker count.
     */
    public function count(): int
    {
        if (isset($this->markers['_lazy'])) {
            return 0; // Unknown until loaded
        }

        return count($this->markers);
    }

    /**
     * Get markers.
     */
    public function getMarkers(): array
    {
        return $this->markers;
    }

    /**
     * Get bounds of all markers.
     *
     * @return array{minLat: float, maxLat: float, minLng: float, maxLng: float}|null
     */
    public function getBounds(): ?array
    {
        if (empty($this->markers) || isset($this->markers['_lazy'])) {
            return null;
        }

        $lats = array_column($this->markers, 'lat');
        $lngs = array_column($this->markers, 'lng');

        return [
            'minLat' => min($lats),
            'maxLat' => max($lats),
            'minLng' => min($lngs),
            'maxLng' => max($lngs),
        ];
    }

    /**
     * Convert to array for JavaScript.
     */
    public function toArray(): array
    {
        return [
            'type' => 'cluster',
            'name' => $this->name,
            'visible' => $this->visible,
            'markers' => $this->markers,
            'options' => [
                'maxClusterRadius' => $this->maxClusterRadius,
                'spiderfyOnMaxZoom' => $this->spiderfyOnMaxZoom,
                'showCoverageOnHover' => $this->showCoverageOnHover,
                'zoomToBoundsOnClick' => $this->zoomToBoundsOnClick,
                'singleMarkerMode' => $this->singleMarkerMode,
                'disableClusteringAtZoom' => $this->disableClusteringAtZoom,
                'animate' => $this->animate,
                'spiderfyDistanceMultiplier' => $this->spiderfyDistanceMultiplier,
                'iconSizeClasses' => $this->iconSizeClasses,
            ],
            'viewportLoading' => $this->viewportLoading ? [
                'enabled' => true,
                'url' => $this->loadUrl,
                'chunkSize' => $this->chunkSize,
            ] : null,
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
