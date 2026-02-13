<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis\Support\Map;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * HeatmapLayer - Configures a heatmap visualization for map data.
 *
 * Uses Leaflet.heat plugin for rendering intensity-based visualizations.
 * Ideal for showing density of points, population distribution, etc.
 *
 * @example
 * HeatmapLayer::make()
 *     ->fromModel(Infrastructure::class, 'location')
 *     ->radius(25)
 *     ->blur(15)
 *     ->maxIntensity(1.0)
 *     ->gradient([
 *         0.4 => 'blue',
 *         0.6 => 'lime',
 *         0.8 => 'yellow',
 *         1.0 => 'red',
 *     ]);
 */
class HeatmapLayer
{
    /**
     * Array of data points: [[lat, lng, intensity], ...]
     */
    protected array $points = [];

    /**
     * Radius of each point in pixels.
     */
    protected int $radius = 25;

    /**
     * Blur radius in pixels.
     */
    protected int $blur = 15;

    /**
     * Maximum point intensity (for scaling).
     */
    protected float $maxIntensity = 1.0;

    /**
     * Minimum opacity of the heatmap.
     */
    protected float $minOpacity = 0.05;

    /**
     * Maximum zoom level where points have maximum intensity.
     */
    protected ?int $maxZoom = null;

    /**
     * Color gradient stops: [intensity => color].
     */
    protected array $gradient = [
        0.4 => 'blue',
        0.65 => 'lime',
        0.8 => 'yellow',
        1.0 => 'red',
    ];

    /**
     * Whether the layer is visible.
     */
    protected bool $visible = true;

    /**
     * Layer name for controls.
     */
    protected string $name = 'Heatmap';

    /**
     * Create a new HeatmapLayer instance.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Set data points directly.
     *
     * @param  array<int, array{0: float, 1: float, 2?: float}>  $points  Array of [lat, lng, intensity?]
     */
    public function points(array $points): static
    {
        $this->points = $points;

        return $this;
    }

    /**
     * Load points from an Eloquent model.
     *
     * @param  class-string<Model>  $modelClass
     * @param  string  $locationColumn  JSON column name or 'latitude,longitude' for separate columns
     * @param  string|null  $intensityColumn  Optional column for point intensity
     * @param  \Closure|null  $query  Optional query modifier
     */
    public function fromModel(
        string $modelClass,
        string $locationColumn = 'location',
        ?string $intensityColumn = null,
        ?\Closure $query = null
    ): static {
        $builder = $modelClass::query();

        if ($query) {
            $query($builder);
        }

        $records = $builder->get();

        $this->points = $records->map(function ($record) use ($locationColumn, $intensityColumn) {
            $coords = $this->extractCoordinates($record, $locationColumn);

            if (! $coords) {
                return null;
            }

            $intensity = $intensityColumn ? ($record->{$intensityColumn} ?? 1.0) : 1.0;

            return [$coords['lat'], $coords['lng'], $intensity];
        })->filter()->values()->toArray();

        return $this;
    }

    /**
     * Load points from a Collection.
     */
    public function fromCollection(
        Collection $collection,
        string $locationColumn = 'location',
        ?string $intensityColumn = null
    ): static {
        $this->points = $collection->map(function ($item) use ($locationColumn, $intensityColumn) {
            $coords = $this->extractCoordinates($item, $locationColumn);

            if (! $coords) {
                return null;
            }

            $intensity = $intensityColumn ? ($item->{$intensityColumn} ?? $item[$intensityColumn] ?? 1.0) : 1.0;

            return [$coords['lat'], $coords['lng'], $intensity];
        })->filter()->values()->toArray();

        return $this;
    }

    /**
     * Extract coordinates from a record/item.
     *
     * @return array{lat: float, lng: float}|null
     */
    protected function extractCoordinates(mixed $item, string $locationColumn): ?array
    {
        // Handle array access
        if (is_array($item)) {
            if (isset($item[$locationColumn])) {
                $location = $item[$locationColumn];

                return $this->parseLocation($location);
            }

            // Check for separate lat/lng columns
            if (str_contains($locationColumn, ',')) {
                [$latCol, $lngCol] = explode(',', $locationColumn);

                if (isset($item[trim($latCol)], $item[trim($lngCol)])) {
                    return [
                        'lat' => (float) $item[trim($latCol)],
                        'lng' => (float) $item[trim($lngCol)],
                    ];
                }
            }

            return null;
        }

        // Handle object/model access
        if (is_object($item)) {
            // JSON column
            if (isset($item->{$locationColumn})) {
                $location = $item->{$locationColumn};

                return $this->parseLocation($location);
            }

            // Separate columns
            if (str_contains($locationColumn, ',')) {
                [$latCol, $lngCol] = explode(',', $locationColumn);
                $latCol = trim($latCol);
                $lngCol = trim($lngCol);

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
     * Parse a location value into lat/lng.
     *
     * @return array{lat: float, lng: float}|null
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
            // Standard lat/lng or latitude/longitude
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

            // GeoJSON Point format
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
     * Set the radius of each point in pixels.
     */
    public function radius(int $radius): static
    {
        $this->radius = $radius;

        return $this;
    }

    /**
     * Set the blur radius in pixels.
     */
    public function blur(int $blur): static
    {
        $this->blur = $blur;

        return $this;
    }

    /**
     * Set the maximum intensity value.
     */
    public function maxIntensity(float $max): static
    {
        $this->maxIntensity = $max;

        return $this;
    }

    /**
     * Set the minimum opacity.
     */
    public function minOpacity(float $opacity): static
    {
        $this->minOpacity = $opacity;

        return $this;
    }

    /**
     * Set the max zoom level for full intensity.
     */
    public function maxZoom(int $zoom): static
    {
        $this->maxZoom = $zoom;

        return $this;
    }

    /**
     * Set the color gradient.
     *
     * @param  array<float, string>  $gradient  Map of intensity (0-1) to color
     */
    public function gradient(array $gradient): static
    {
        $this->gradient = $gradient;

        return $this;
    }

    /**
     * Use a preset gradient.
     */
    public function usePresetGradient(string $preset): static
    {
        $presets = [
            'default' => [
                0.4 => 'blue',
                0.65 => 'lime',
                0.8 => 'yellow',
                1.0 => 'red',
            ],
            'hot' => [
                0.4 => '#ffffb2',
                0.6 => '#fd8d3c',
                0.8 => '#f03b20',
                1.0 => '#bd0026',
            ],
            'cool' => [
                0.4 => '#f7fcf0',
                0.6 => '#7bccc4',
                0.8 => '#2b8cbe',
                1.0 => '#084081',
            ],
            'viridis' => [
                0.2 => '#440154',
                0.4 => '#3b528b',
                0.6 => '#21918c',
                0.8 => '#5ec962',
                1.0 => '#fde725',
            ],
            'plasma' => [
                0.2 => '#0d0887',
                0.4 => '#7e03a8',
                0.6 => '#cc4778',
                0.8 => '#f89540',
                1.0 => '#f0f921',
            ],
        ];

        $this->gradient = $presets[$preset] ?? $presets['default'];

        return $this;
    }

    /**
     * Set whether the layer is visible.
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
     * Get the points array.
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    /**
     * Get the number of points.
     */
    public function count(): int
    {
        return count($this->points);
    }

    /**
     * Convert to array for JavaScript.
     */
    public function toArray(): array
    {
        return [
            'type' => 'heatmap',
            'name' => $this->name,
            'visible' => $this->visible,
            'data' => $this->points,
            'options' => [
                'radius' => $this->radius,
                'blur' => $this->blur,
                'max' => $this->maxIntensity,
                'minOpacity' => $this->minOpacity,
                'maxZoom' => $this->maxZoom,
                'gradient' => $this->gradient,
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
