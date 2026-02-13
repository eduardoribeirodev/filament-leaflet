<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis\Support\Map;

/**
 * VectorTileLayer - Mapbox Vector Tiles (MVT) support.
 *
 * Supports serving vector tiles from:
 * - Mapbox GL
 * - PostGIS ST_AsMVT
 * - MBTiles
 * - Custom tile servers
 *
 * @example
 * VectorTileLayer::make('https://tiles.example.com/{z}/{x}/{y}.pbf')
 *     ->vectorTileLayerStyles([
 *         'water' => ['fillColor' => '#00f', 'fillOpacity' => 0.5],
 *         'buildings' => ['fillColor' => '#888', 'stroke' => true],
 *     ])
 *     ->interactive(['buildings', 'roads'])
 *     ->minZoom(10)
 *     ->maxZoom(20);
 */
class VectorTileLayer
{
    /**
     * Tile URL template.
     */
    protected string $url;

    /**
     * Style definitions for vector layers.
     */
    protected array $vectorTileLayerStyles = [];

    /**
     * Interactive layers (clickable).
     */
    protected array $interactive = [];

    /**
     * Minimum zoom level.
     */
    protected int $minZoom = 0;

    /**
     * Maximum zoom level.
     */
    protected int $maxZoom = 22;

    /**
     * Maximum native zoom (tile zoom).
     */
    protected ?int $maxNativeZoom = null;

    /**
     * Tile bounds [[sw_lat, sw_lng], [ne_lat, ne_lng]].
     */
    protected ?array $bounds = null;

    /**
     * Attribution text.
     */
    protected string $attribution = '';

    /**
     * Whether the layer is visible.
     */
    protected bool $visible = true;

    /**
     * Layer name for controls.
     */
    protected string $name = 'Vector Tiles';

    /**
     * Z-index for ordering.
     */
    protected ?int $zIndex = null;

    /**
     * Fetch options for tile requests.
     */
    protected array $fetchOptions = [];

    /**
     * Subdomains for load balancing.
     */
    protected array $subdomains = [];

    /**
     * Feature ID property name.
     */
    protected string $idProperty = 'id';

    /**
     * Tooltip callback per layer.
     */
    protected array $tooltips = [];

    /**
     * Popup callback per layer.
     */
    protected array $popups = [];

    /**
     * Create a new VectorTileLayer instance.
     */
    public static function make(string $url): static
    {
        $instance = new static;
        $instance->url = $url;

        return $instance;
    }

    /**
     * Set styles for vector layers.
     *
     * @param  array<string, array>  $styles  Layer name => style properties
     */
    public function vectorTileLayerStyles(array $styles): static
    {
        $this->vectorTileLayerStyles = $styles;

        return $this;
    }

    /**
     * Add or update a single layer style.
     */
    public function layerStyle(string $layer, array $style): static
    {
        $this->vectorTileLayerStyles[$layer] = $style;

        return $this;
    }

    /**
     * Set interactive (clickable) layers.
     */
    public function interactive(array $layers): static
    {
        $this->interactive = $layers;

        return $this;
    }

    /**
     * Set minimum zoom level.
     */
    public function minZoom(int $zoom): static
    {
        $this->minZoom = $zoom;

        return $this;
    }

    /**
     * Set maximum zoom level.
     */
    public function maxZoom(int $zoom): static
    {
        $this->maxZoom = $zoom;

        return $this;
    }

    /**
     * Set zoom range.
     */
    public function zoomRange(int $min, int $max): static
    {
        $this->minZoom = $min;
        $this->maxZoom = $max;

        return $this;
    }

    /**
     * Set maximum native zoom (tile server max).
     */
    public function maxNativeZoom(int $zoom): static
    {
        $this->maxNativeZoom = $zoom;

        return $this;
    }

    /**
     * Set tile bounds.
     */
    public function bounds(float $swLat, float $swLng, float $neLat, float $neLng): static
    {
        $this->bounds = [[$swLat, $swLng], [$neLat, $neLng]];

        return $this;
    }

    /**
     * Set attribution.
     */
    public function attribution(string $attribution): static
    {
        $this->attribution = $attribution;

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
     * Set z-index.
     */
    public function zIndex(int $zIndex): static
    {
        $this->zIndex = $zIndex;

        return $this;
    }

    /**
     * Set subdomains for load balancing.
     */
    public function subdomains(array $subdomains): static
    {
        $this->subdomains = $subdomains;

        return $this;
    }

    /**
     * Set fetch options for tile requests.
     */
    public function fetchOptions(array $options): static
    {
        $this->fetchOptions = $options;

        return $this;
    }

    /**
     * Add authentication header.
     */
    public function withAuth(string $token, string $type = 'Bearer'): static
    {
        $this->fetchOptions['headers'] = [
            'Authorization' => "{$type} {$token}",
        ];

        return $this;
    }

    /**
     * Set the feature ID property name.
     */
    public function idProperty(string $property): static
    {
        $this->idProperty = $property;

        return $this;
    }

    /**
     * Add tooltip for a layer.
     */
    public function tooltip(string $layer, string $template): static
    {
        $this->tooltips[$layer] = $template;

        return $this;
    }

    /**
     * Add popup for a layer.
     */
    public function popup(string $layer, string $template): static
    {
        $this->popups[$layer] = $template;

        return $this;
    }

    /**
     * Configure for PostGIS ST_AsMVT endpoint.
     */
    public function postGIS(string $baseUrl, string $table, array $columns = ['*']): static
    {
        $columnsStr = implode(',', $columns);
        $this->url = "{$baseUrl}/{$table}/{z}/{x}/{y}.pbf?columns={$columnsStr}";

        return $this;
    }

    /**
     * Configure for Mapbox tiles.
     */
    public function mapbox(string $tilesetId, string $accessToken): static
    {
        $this->url = "https://api.mapbox.com/v4/{$tilesetId}/{z}/{x}/{y}.vector.pbf?access_token={$accessToken}";
        $this->attribution = '© <a href="https://www.mapbox.com/">Mapbox</a>';

        return $this;
    }

    /**
     * Configure for MBTiles file.
     */
    public function mbtiles(string $baseUrl, string $filename): static
    {
        $this->url = "{$baseUrl}/mbtiles/{$filename}/{z}/{x}/{y}.pbf";

        return $this;
    }

    /**
     * Set predefined style for common layer types.
     */
    public function presetStyle(string $layerType, string $preset = 'default'): static
    {
        $presets = [
            'buildings' => [
                'default' => [
                    'fillColor' => '#d4d4d4',
                    'fillOpacity' => 0.7,
                    'stroke' => true,
                    'color' => '#888',
                    'weight' => 1,
                ],
                'dark' => [
                    'fillColor' => '#333',
                    'fillOpacity' => 0.8,
                    'stroke' => true,
                    'color' => '#000',
                    'weight' => 1,
                ],
            ],
            'water' => [
                'default' => [
                    'fillColor' => '#a8d4f0',
                    'fillOpacity' => 0.8,
                    'stroke' => false,
                ],
                'dark' => [
                    'fillColor' => '#1a365d',
                    'fillOpacity' => 0.9,
                    'stroke' => false,
                ],
            ],
            'roads' => [
                'default' => [
                    'stroke' => true,
                    'color' => '#fbbf24',
                    'weight' => 2,
                ],
                'dark' => [
                    'stroke' => true,
                    'color' => '#f59e0b',
                    'weight' => 2,
                ],
            ],
            'landuse' => [
                'default' => [
                    'fillColor' => '#bbf7d0',
                    'fillOpacity' => 0.5,
                    'stroke' => false,
                ],
            ],
        ];

        if (isset($presets[$layerType][$preset])) {
            $this->vectorTileLayerStyles[$layerType] = $presets[$layerType][$preset];
        }

        return $this;
    }

    /**
     * Get the URL.
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Convert to array for JavaScript.
     */
    public function toArray(): array
    {
        $result = [
            'type' => 'vectorTile',
            'name' => $this->name,
            'visible' => $this->visible,
            'url' => $this->url,
            'options' => [
                'vectorTileLayerStyles' => $this->vectorTileLayerStyles,
                'interactive' => $this->interactive,
                'minZoom' => $this->minZoom,
                'maxZoom' => $this->maxZoom,
                'attribution' => $this->attribution,
                'getFeatureId' => "function(f) { return f.properties.{$this->idProperty}; }",
            ],
        ];

        if ($this->maxNativeZoom !== null) {
            $result['options']['maxNativeZoom'] = $this->maxNativeZoom;
        }

        if ($this->bounds !== null) {
            $result['options']['bounds'] = $this->bounds;
        }

        if ($this->zIndex !== null) {
            $result['options']['zIndex'] = $this->zIndex;
        }

        if (! empty($this->subdomains)) {
            $result['options']['subdomains'] = $this->subdomains;
        }

        if (! empty($this->fetchOptions)) {
            $result['options']['fetchOptions'] = $this->fetchOptions;
        }

        if (! empty($this->tooltips)) {
            $result['tooltips'] = $this->tooltips;
        }

        if (! empty($this->popups)) {
            $result['popups'] = $this->popups;
        }

        return $result;
    }

    /**
     * Convert to JSON.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
