<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Concerns;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\Enums\TileLayer;
use EduardoRibeiroDev\FilamentLeaflet\Support\Markers\Marker;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;

trait HasMapState
{
    use HasMapConfig {
        getGeoJsonTooltip as getParentGeoJsonTooltip;
        getGeoJsonUrl as getParentGeoJsonUrl;
        getMapData as getParentMapData;
    }

    protected array $geoJsonData = [];
    protected ?string $geoJsonTooltip = null;
    protected array $markers = [];
    protected array $shapes = [];

    protected ?string $latitudeFieldName = 'latitude';
    protected ?string $longitudeFieldName = 'longitude';
    protected bool $storeAsJson = false;

    protected ?Marker $pickMarker = null;
    protected ?Closure $onMapClickCallback = null;
    protected ?Closure $onLayerClickCallback = null;

    public function center(float|array|Closure $latitudeOrCoordinates, float|Closure $longitude): static
    {
        $latitudeOrCoordinates = $this->evaluate($latitudeOrCoordinates);

        if (is_array($latitudeOrCoordinates)) {
            $this->mapCenter = $latitudeOrCoordinates;
        } else {
            $this->mapCenter = [
                $latitudeOrCoordinates,
                $this->evaluate($longitude),
            ];
        }

        return $this;
    }

    public function autoCenter(bool|Closure $autoCenter = true): static
    {
        $this->autoCenter = $this->evaluate($autoCenter);

        return $this;
    }

    public function height(int|Closure $height): static
    {
        $this->mapHeight = $this->evaluate($height);

        return $this;
    }

    public function mapDraggable(bool|Closure $draggable = true): static
    {
        $this->mapDraggable = $this->evaluate($draggable);

        return $this;
    }

    public function mapZoomable(bool|Closure $zoomable = true): static
    {
        $this->mapZoomable = $this->evaluate($zoomable);

        return $this;
    }

    public function static(bool|Closure $isStatic = true): static
    {
        $isStatic = $this->evaluate($isStatic);
        $this->mapDraggable(!$isStatic);
        $this->mapZoomable(!$isStatic);

        return $this;
    }

    public function recenterTimeout(null|int|Closure $milliseconds): static
    {
        $this->recenterMapTimeout = $this->evaluate($milliseconds);

        return $this;
    }

    public function zoom(int|Closure $zoomLevel): static
    {
        $this->defaultZoom = $this->evaluate($zoomLevel);

        return $this;
    }

    public function attributionControl(bool|Closure $enabled = true): static
    {
        $this->hasAttributionControl = $this->evaluate($enabled);

        return $this;
    }

    public function fullscreenControl(bool|Closure $enabled = true): static
    {
        $this->hasFullscreenControl = $this->evaluate($enabled);

        return $this;
    }

    public function searchControl(bool|Closure $enabled = true): static
    {
        $this->hasSearchControl = $this->evaluate($enabled);

        return $this;
    }

    public function scaleControl(bool|Closure $enabled = true): static
    {
        $this->hasScaleControl = $this->evaluate($enabled);

        return $this;
    }

    public function zoomControl(bool|Closure $enabled = true): static
    {
        $this->hasZoomControl = $this->evaluate($enabled);

        return $this;
    }

    /** @deprecated */
    public function drawControl(bool|Closure $enabled = true): static
    {
        $this->hasDrawControl = $this->evaluate($enabled);

        return $this;
    }

    public function drawMarkerControl(bool|Closure $enabled = true): static
    {
        $this->hasDrawMarkerControl = $this->evaluate($enabled);

        return $this;
    }

    public function drawCircleMarkerControl(bool|Closure $enabled = true): static
    {
        $this->hasDrawCircleMarkerControl = $this->evaluate($enabled);

        return $this;
    }

    public function drawCircleControl(bool|Closure $enabled = true): static
    {
        $this->hasDrawCircleControl = $this->evaluate($enabled);

        return $this;
    }

    public function drawPolylineControl(bool|Closure $enabled = true): static
    {
        $this->hasDrawPolylineControl = $this->evaluate($enabled);

        return $this;
    }

    public function drawRectangleControl(bool|Closure $enabled = true): static
    {
        $this->hasDrawRectangleControl = $this->evaluate($enabled);

        return $this;
    }

    public function drawPolygonControl(bool|Closure $enabled = true): static
    {
        $this->hasDrawPolygonControl = $this->evaluate($enabled);

        return $this;
    }

    public function drawTextControl(bool|Closure $enabled = true): static
    {
        $this->hasDrawTextControl = $this->evaluate($enabled);

        return $this;
    }

    public function editLayersControl(bool|Closure $enabled = true): static
    {
        $this->hasEditLayersControl = $this->evaluate($enabled);

        return $this;
    }

    public function dragLayersControl(bool|Closure $enabled = true): static
    {
        $this->hasDragLayersControl = $this->evaluate($enabled);

        return $this;
    }

    public function removeLayersControl(bool|Closure $enabled = true): static
    {
        $this->hasRemoveLayersControl = $this->evaluate($enabled);

        return $this;
    }

    public function rotateLayersControl(bool|Closure $enabled = true): static
    {
        $this->hasRotateLayersControl = $this->evaluate($enabled);

        return $this;
    }

    public function cutPolygonControl(bool|Closure $enabled = true): static
    {
        $this->hasCutPolygonControl = $this->evaluate($enabled);

        return $this;
    }

    public function tileLayersUrl(TileLayer|Closure|string|array $urls): static
    {
        $this->tileLayersUrl = $this->evaluate($urls);

        return $this;
    }

    public function minZoom(int|Closure $minZoom): static
    {
        $this->minZoom = $this->evaluate($minZoom);

        return $this;
    }

    public function maxZoom(int|Closure $maxZoom): static
    {
        $this->maxZoom = $this->evaluate($maxZoom);

        return $this;
    }

    public function geoJsonUrl(string|Closure $url): static
    {
        $this->geoJsonUrl = $this->evaluate($url);

        return $this;
    }

    public function geoJsonData(array|Closure $data): static
    {
        $this->geoJsonData = $this->evaluate($data);

        return $this;
    }

    public function geoJsonColors(array|Closure $colors): static
    {
        $this->geoJsonColors = $this->evaluate($colors);

        return $this;
    }

    public function geoJsonTooltip(string|Closure|null $tooltip): static
    {
        $this->geoJsonTooltip = $this->evaluate($tooltip);

        return $this;
    }

    public function markers(array|Closure $markers): static
    {
        $this->markers = $this->evaluate($markers);

        return $this;
    }

    public function shapes(array|Closure $shapes): static
    {
        $this->shapes = $this->evaluate($shapes);

        return $this;
    }

    public function latitudeFieldName(string|Closure|null $name): static
    {
        $this->latitudeFieldName = $this->evaluate($name);

        return $this;
    }

    public function longitudeFieldName(string|Closure|null $name): static
    {
        $this->longitudeFieldName = $this->evaluate($name);

        return $this;
    }

    public function storeAsJson(bool|Closure $value = true): static
    {
        $this->storeAsJson = $this->evaluate($value);

        return $this;
    }

    public function pickMarker(Marker|Closure|null $marker)
    {
        $this->pickMarker = $this->evaluate($marker, [
            'marker' => $this->pickMarker ?? new Marker
        ]);

        return $this;
    }

    public function onMapClick(?Closure $callback): static
    {
        $this->onMapClickCallback = $callback;

        return $this;
    }

    public function onLayerClick(?Closure $callback): static
    {
        $this->onLayerClickCallback = $callback;

        return $this;
    }

    /** ---------- GETTERS ---------- */

    function getPickMarkerData(): array
    {
        $pickMarker = null;

        if ($this->pickMarker) {
            $pickMarker = $this->pickMarker;
        } else {
            $pickMarker = new Marker;

            if ($this->isDisabled()) {
                $pickMarker->grey();
            }
        }

        return $pickMarker->toArray();
    }

    protected function getMapCenter(): array
    {
        $state = $this->getState();

        if (!$state) {
            return $this->mapCenter;
        }

        return [
            $state[$this->latitudeFieldName],
            $state[$this->longitudeFieldName]
        ];
    }

    protected function getMarkers(): array
    {
        return $this->markers;
    }

    protected function getShapes(): array
    {
        return $this->shapes;
    }

    protected function getGeoJsonTooltip(): string
    {
        if ($this->geoJsonTooltip) {
            return $this->geoJsonTooltip;
        }

        return $this->getParentGeoJsonTooltip();
    }

    protected function getGeoJsonUrl(): ?string
    {
        if ($this->geoJsonUrl) {
            return $this->geoJsonUrl;
        }

        $record = $this->getRecord();
        if ($record && method_exists($record, 'getGeoJsonUrl')) {
            return $record->getGeoJsonUrl();
        }

        return $this->getParentGeoJsonUrl();
    }

    #[ExposedLivewireMethod]
    public function handleMapClick(float $latitude, float $longitude): void
    {
        $this->evaluate($this->onMapClickCallback, [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'coordinates' => [$latitude, $longitude]
        ]);
    }

    #[ExposedLivewireMethod]
    public function handleLayerClick(string $layerId): void
    {
        $layer = $this->getLayerById($layerId);

        $this->evaluate($this->onLayerClickCallback, [
            'layer' => $layer
        ]);
    }

    public function getStatePath(bool $isAbsolute = true): ?string
    {
        if (method_exists(parent::class, 'getStatePath')) {
            return parent::getStatePath($isAbsolute);
        }

        return null;
    }

    public function getKey(bool $isAbsolute = true): ?string
    {
        if (method_exists(parent::class, 'getKey')) {
            return parent::getKey($isAbsolute);
        }

        return null;
    }

    public function getRecordKey(): ?string
    {
        if (($record = $this->getRecord())) {
            return $record->getKey();
        }

        return null;
    }

    private function getMapFieldData(): array
    {
        return [
            'pickMarker'         => $this->getPickMarkerData(),
            'latitudeFieldName'  => $this->latitudeFieldName,
            'longitudeFieldName' => $this->longitudeFieldName,
            'statePath'          => $this->getStatePath(),
            'state'              => $this->getState(),
            'name'               => $this->getName(),
            'recordKey'          => $this->getRecordKey(),
            'disabled'           => $this->isDisabled(),
            'key'                => $this->getKey(),
        ];
    }

    public function getMapData(): array
    {
        return array_merge(
            $this->getParentMapData(),
            ['state' => $this->getMapFieldData()]
        );
    }
}
