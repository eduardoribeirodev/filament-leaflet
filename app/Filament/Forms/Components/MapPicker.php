<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use EduardoRibeiroDev\FilamentLeaflet\Fields\MapPicker as BaseMapPicker;

/**
 * Extended MapPicker that properly handles polygon and shape data.
 *
 * Fixes the bug where the original MapPicker crashes when state is null
 * or when drawing shapes (polygons, circles) instead of simple markers.
 */
class MapPicker extends BaseMapPicker
{
    /**
     * Whether to store the full geometry data (including shapes).
     */
    protected bool $storeFullGeometry = false;

    /**
     * Enable storing full geometry data (polygons, circles, etc.).
     */
    public function storeFullGeometry(bool $value = true): static
    {
        $this->storeFullGeometry = $value;

        return $this;
    }

    /**
     * Get the map center coordinates.
     *
     * Overrides parent to handle various state formats (lat/lng, latitude/longitude, polygon data).
     */
    protected function getMapCenter(): array
    {
        $state = $this->getState();

        if (! $state || ! is_array($state)) {
            return $this->mapCenter;
        }

        // Try standard latitude/longitude field names
        if (isset($state[$this->latitudeFieldName], $state[$this->longitudeFieldName])) {
            return [
                $state[$this->latitudeFieldName],
                $state[$this->longitudeFieldName],
            ];
        }

        // Try common shortcuts lat/lng
        if (isset($state['lat'], $state['lng'])) {
            return [
                $state['lat'],
                $state['lng'],
            ];
        }

        // For polygon/shape data, try to find center from points
        if (isset($state['points']) && is_array($state['points']) && count($state['points']) > 0) {
            return $this->calculateCentroid($state['points']);
        }

        // For GeoJSON format
        if (isset($state['type'], $state['coordinates'])) {
            return $this->getGeoJsonCenter($state);
        }

        return $this->mapCenter;
    }

    /**
     * Calculate centroid from array of points.
     */
    protected function calculateCentroid(array $points): array
    {
        if (empty($points)) {
            return $this->mapCenter;
        }

        $latSum = 0;
        $lngSum = 0;
        $count = count($points);

        foreach ($points as $point) {
            // Points can be [lat, lng] or ['lat' => x, 'lng' => y]
            if (isset($point[0], $point[1])) {
                $latSum += $point[0];
                $lngSum += $point[1];
            } elseif (isset($point['lat'], $point['lng'])) {
                $latSum += $point['lat'];
                $lngSum += $point['lng'];
            }
        }

        return [$latSum / $count, $lngSum / $count];
    }

    /**
     * Get center from GeoJSON geometry.
     */
    protected function getGeoJsonCenter(array $geoJson): array
    {
        $type = $geoJson['type'] ?? '';
        $coords = $geoJson['coordinates'] ?? [];

        switch ($type) {
            case 'Point':
                // GeoJSON Point is [lng, lat]
                return [$coords[1] ?? 0, $coords[0] ?? 0];

            case 'LineString':
            case 'MultiPoint':
                if (! empty($coords)) {
                    $lngSum = array_sum(array_column($coords, 0));
                    $latSum = array_sum(array_column($coords, 1));
                    $count = count($coords);

                    return [$latSum / $count, $lngSum / $count];
                }
                break;

            case 'Polygon':
                // Use exterior ring
                $ring = $coords[0] ?? [];
                if (! empty($ring)) {
                    $lngSum = array_sum(array_column($ring, 0));
                    $latSum = array_sum(array_column($ring, 1));
                    $count = count($ring);

                    return [$latSum / $count, $lngSum / $count];
                }
                break;
        }

        return $this->mapCenter;
    }

    protected function setUp(): void
    {
        // Call grandparent setup to skip the buggy parent implementation
        $this->height(284);

        $this->saveRelationshipsUsing(function ($record, $state) {
            $columnName = $this->getName();

            // Handle null state gracefully
            if ($state === null) {
                $record->{$columnName} = null;
                $record->save();

                return;
            }

            // If storing full geometry (for polygons, circles, etc.)
            if ($this->storeFullGeometry) {
                $record->{$columnName} = $state;
                $record->save();

                return;
            }

            // Original behavior for simple lat/lng points
            if ($this->storeAsJson) {
                // Check if state has the expected keys
                $lat = $state[$this->latitudeFieldName] ?? $state['lat'] ?? null;
                $lng = $state[$this->longitudeFieldName] ?? $state['lng'] ?? null;

                if ($lat !== null && $lng !== null) {
                    $record->{$columnName} = [
                        $this->latitudeFieldName => $lat,
                        $this->longitudeFieldName => $lng,
                    ];
                } else {
                    // State has different structure (polygon, circle, etc.)
                    // Store the full state as-is
                    $record->{$columnName} = $state;
                }
            } else {
                $lat = $state[$this->latitudeFieldName] ?? $state['lat'] ?? null;
                $lng = $state[$this->longitudeFieldName] ?? $state['lng'] ?? null;

                if ($lat !== null && $lng !== null) {
                    $record->{$this->latitudeFieldName} = $lat;
                    $record->{$this->longitudeFieldName} = $lng;
                }
            }

            $record->save();
        });

        $this->afterStateHydrated(function ($record) {
            if (! $record) {
                return;
            }

            $columnName = $this->getName();

            if ($this->storeAsJson || $this->storeFullGeometry) {
                $this->state($record->{$columnName});
            } else {
                $lat = $record->{$this->latitudeFieldName} ?? null;
                $lng = $record->{$this->longitudeFieldName} ?? null;

                if ($lat !== null && $lng !== null) {
                    $this->state([
                        $this->latitudeFieldName => $lat,
                        $this->longitudeFieldName => $lng,
                    ]);
                }
            }
        });
    }
}
