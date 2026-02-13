<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis\Support\Map;

/**
 * WMSLayer - Web Map Service layer integration.
 *
 * Supports WMS 1.1.1 and 1.3.0 standards for displaying map data
 * from external GIS servers (GeoServer, MapServer, ArcGIS Server, etc.)
 *
 * @example
 * WMSLayer::make('https://wms.example.com/geoserver/wms')
 *     ->layers('workspace:layer_name')
 *     ->format('image/png')
 *     ->transparent()
 *     ->styles('default_style')
 *     ->attribution('© Example WMS Provider');
 */
class WMSLayer
{
    /**
     * WMS server base URL.
     */
    protected string $baseUrl;

    /**
     * Comma-separated list of layer names.
     */
    protected string $layers = '';

    /**
     * Image format for tiles.
     */
    protected string $format = 'image/png';

    /**
     * Whether tiles should be transparent.
     */
    protected bool $transparent = true;

    /**
     * WMS version (1.1.1 or 1.3.0).
     */
    protected string $version = '1.1.1';

    /**
     * Comma-separated list of styles.
     */
    protected string $styles = '';

    /**
     * Coordinate Reference System.
     */
    protected string $crs = 'EPSG:4326';

    /**
     * Attribution text.
     */
    protected string $attribution = '';

    /**
     * Layer opacity (0-1).
     */
    protected float $opacity = 1.0;

    /**
     * Minimum zoom level.
     */
    protected int $minZoom = 0;

    /**
     * Maximum zoom level.
     */
    protected int $maxZoom = 18;

    /**
     * Whether the layer is visible.
     */
    protected bool $visible = true;

    /**
     * Layer name for controls.
     */
    protected string $name = 'WMS Layer';

    /**
     * Additional WMS parameters.
     */
    protected array $customParams = [];

    /**
     * GetFeatureInfo query layers.
     */
    protected ?string $queryLayers = null;

    /**
     * Whether GetFeatureInfo is enabled.
     */
    protected bool $featureInfoEnabled = false;

    /**
     * GetFeatureInfo format.
     */
    protected string $featureInfoFormat = 'application/json';

    /**
     * Z-index for layer ordering.
     */
    protected ?int $zIndex = null;

    /**
     * Create a new WMSLayer instance.
     */
    public static function make(string $baseUrl): static
    {
        $instance = new static;
        $instance->baseUrl = $baseUrl;

        return $instance;
    }

    /**
     * Set the layer names to request.
     */
    public function layers(string $layers): static
    {
        $this->layers = $layers;

        return $this;
    }

    /**
     * Set the image format.
     */
    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Enable transparency.
     */
    public function transparent(bool $transparent = true): static
    {
        $this->transparent = $transparent;

        return $this;
    }

    /**
     * Set the WMS version.
     */
    public function version(string $version): static
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Set the styles.
     */
    public function styles(string $styles): static
    {
        $this->styles = $styles;

        return $this;
    }

    /**
     * Set the CRS/SRS.
     */
    public function crs(string $crs): static
    {
        $this->crs = $crs;

        return $this;
    }

    /**
     * Set the attribution text.
     */
    public function attribution(string $attribution): static
    {
        $this->attribution = $attribution;

        return $this;
    }

    /**
     * Set the layer opacity.
     */
    public function opacity(float $opacity): static
    {
        $this->opacity = max(0, min(1, $opacity));

        return $this;
    }

    /**
     * Set the zoom range.
     */
    public function zoomRange(int $min, int $max): static
    {
        $this->minZoom = $min;
        $this->maxZoom = $max;

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
     * Set the layer name for controls.
     */
    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Add custom WMS parameters.
     */
    public function params(array $params): static
    {
        $this->customParams = array_merge($this->customParams, $params);

        return $this;
    }

    /**
     * Enable GetFeatureInfo support.
     */
    public function enableFeatureInfo(?string $queryLayers = null, string $format = 'application/json'): static
    {
        $this->featureInfoEnabled = true;
        $this->queryLayers = $queryLayers ?? $this->layers;
        $this->featureInfoFormat = $format;

        return $this;
    }

    /**
     * Set the z-index for layer ordering.
     */
    public function zIndex(int $zIndex): static
    {
        $this->zIndex = $zIndex;

        return $this;
    }

    /**
     * Configure for a GeoServer instance.
     */
    public function geoServer(string $workspace, string $layer, ?string $style = null): static
    {
        $this->layers = "{$workspace}:{$layer}";

        if ($style) {
            $this->styles = $style;
        }

        return $this;
    }

    /**
     * Configure TIME dimension for temporal WMS.
     */
    public function time(string $time): static
    {
        $this->customParams['TIME'] = $time;

        return $this;
    }

    /**
     * Configure ELEVATION dimension.
     */
    public function elevation(string|float $elevation): static
    {
        $this->customParams['ELEVATION'] = (string) $elevation;

        return $this;
    }

    /**
     * Configure CQL filter (GeoServer).
     */
    public function cqlFilter(string $filter): static
    {
        $this->customParams['CQL_FILTER'] = $filter;

        return $this;
    }

    /**
     * Configure environment variables (GeoServer SLD).
     */
    public function env(array $env): static
    {
        $envString = collect($env)
            ->map(fn ($value, $key) => "{$key}:{$value}")
            ->implode(';');

        $this->customParams['ENV'] = $envString;

        return $this;
    }

    /**
     * Build GetCapabilities URL.
     */
    public function getCapabilitiesUrl(): string
    {
        $params = [
            'SERVICE' => 'WMS',
            'VERSION' => $this->version,
            'REQUEST' => 'GetCapabilities',
        ];

        return $this->baseUrl.'?'.http_build_query($params);
    }

    /**
     * Build GetLegendGraphic URL.
     */
    public function getLegendUrl(?string $layer = null, int $width = 20, int $height = 20): string
    {
        $params = [
            'SERVICE' => 'WMS',
            'VERSION' => $this->version,
            'REQUEST' => 'GetLegendGraphic',
            'LAYER' => $layer ?? explode(',', $this->layers)[0],
            'FORMAT' => 'image/png',
            'WIDTH' => $width,
            'HEIGHT' => $height,
        ];

        return $this->baseUrl.'?'.http_build_query($params);
    }

    /**
     * Get the base URL.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get the layer names.
     */
    public function getLayers(): string
    {
        return $this->layers;
    }

    /**
     * Convert to array for JavaScript.
     */
    public function toArray(): array
    {
        $params = [
            'layers' => $this->layers,
            'format' => $this->format,
            'transparent' => $this->transparent,
            'version' => $this->version,
            'styles' => $this->styles,
        ];

        // Use SRS for 1.1.1 and CRS for 1.3.0
        if (version_compare($this->version, '1.3.0', '>=')) {
            $params['crs'] = $this->crs;
        } else {
            $params['srs'] = $this->crs;
        }

        $params = array_merge($params, $this->customParams);

        $result = [
            'type' => 'wms',
            'name' => $this->name,
            'visible' => $this->visible,
            'baseUrl' => $this->baseUrl,
            'params' => $params,
            'options' => [
                'opacity' => $this->opacity,
                'minZoom' => $this->minZoom,
                'maxZoom' => $this->maxZoom,
                'attribution' => $this->attribution,
            ],
        ];

        if ($this->zIndex !== null) {
            $result['options']['zIndex'] = $this->zIndex;
        }

        if ($this->featureInfoEnabled) {
            $result['featureInfo'] = [
                'enabled' => true,
                'queryLayers' => $this->queryLayers,
                'format' => $this->featureInfoFormat,
            ];
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
