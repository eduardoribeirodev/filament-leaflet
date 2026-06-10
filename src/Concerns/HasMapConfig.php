<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Concerns;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\Enums\GeoSearchProvider;
use EduardoRibeiroDev\FilamentLeaflet\Enums\TileLayer;
use EduardoRibeiroDev\FilamentLeaflet\Layers\BaseLayer;
use EduardoRibeiroDev\FilamentLeaflet\LayerGroups\BaseLayerGroup;
use EduardoRibeiroDev\FilamentLeaflet\LayerGroups\LayerGroup;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Marker;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Shapes\Shape;
use Livewire\Attributes\On;

trait HasMapConfig
{
    // Configurações padrão do mapa
    protected array|Closure|null $mapCenter = null;
    protected bool|Closure $autoCenter = false;
    protected bool|Closure $fitBounds = false;
    protected int|Closure $defaultZoom = 4;
    protected int|Closure $mapHeight = 598;
    protected int|Closure|null $recenterMapTimeout = null;
    protected bool|Closure $mapDraggable = true;
    protected bool|Closure $mapZoomable = true;

    // Configurações de controles
    protected bool|Closure $hasAttributionControl = false;
    protected bool|Closure $hasFullscreenControl = false;
    protected bool|Closure $hasScaleControl = false;
    protected bool|Closure $hasZoomControl = true;

    // Configurações de GeoSearch
    protected bool|Closure $hasGeoSearchControl = false;
    protected GeoSearchProvider|string|Closure $geoSearchProvider = GeoSearchProvider::Nominatim;
    protected string|Closure|null $geoSearchApiKey = null;

    // Controles do Geoman
    protected bool|Closure $hasDrawMarkerControl = false;
    protected bool|Closure $hasDrawCircleMarkerControl = false;
    protected bool|Closure $hasDrawCircleControl = false;
    protected bool|Closure $hasDrawPolylineControl = false;
    protected bool|Closure $hasDrawRectangleControl = false;
    protected bool|Closure $hasDrawPolygonControl = false;
    protected bool|Closure $hasDrawTextControl = false;
    protected bool|Closure $hasEditLayersControl = false;
    protected bool|Closure $hasDragLayersControl = false;
    protected bool|Closure $hasRemoveLayersControl = false;
    protected bool|Closure $hasRotateLayersControl = false;
    protected bool|Closure $hasCutPolygonControl = false;

    protected int|Closure $maxZoom = 19;
    protected int|Closure $minZoom = 2;

    protected TileLayer|string|array|Closure $tileLayersUrl = TileLayer::OpenStreetMap;

    // Configurações do GeoJSON Density
    protected string|Closure|null $geoJsonUrl = null;
    protected array|Closure $geoJsonColors = [
        '#FED976',
        '#FEB24C',
        '#FD8D3C',
        '#FC4E2A',
        '#E31A1C',
        '#BD0026',
        '#800026'
    ];

    // Cache de layers e grupos
    private ?array $cachedLayerData = null;

    /**
     * Retorna as coordenadas centrais do mapa.
     */
    protected function getMapCenter(): array
    {
        return $this->evaluate($this->mapCenter) ?? config('filament-leaflet.default_map_center', [0, 0]);
    }

    /**
     * Define se o mapa deve ser centralizado na localização atual do usuário.
     */
    protected function getAutoCenter(): bool
    {
        return $this->evaluate($this->autoCenter);
    }

    /**
     * Define se o mapa deve fazer zoom automático para mostrar todos os markers.
     */
    protected function getFitBounds(): bool
    {
        return $this->evaluate($this->fitBounds);
    }

    /**
     * Retorna o zoom inicial padrão.
     */
    protected function getDefaultZoom(): int
    {
        return $this->evaluate($this->defaultZoom);
    }

    /**
     * Retorna a altura do mapa em pixels.
     */
    protected function getMapHeight(): int
    {
        return $this->evaluate($this->mapHeight);
    }

    /**
     * Define se o mapa pode ser arrastado.
     */
    public function getMapDraggable(): bool
    {
        return $this->evaluate($this->mapDraggable);
    }

    /**
     * Define se o mapa pode ser ampliado/reduzido.
     */
    public function getMapZoomable(): bool
    {
        return $this->evaluate($this->mapZoomable);
    }

    /**
     * Define se o controle de atribuição deve ser exibido.
     */
    protected function hasAttributionControl(): bool
    {
        return $this->evaluate($this->hasAttributionControl);
    }

    /**
     * Define se o controle de fullscreen deve ser exibido.
     */
    protected function hasFullscreenControl(): bool
    {
        return $this->evaluate($this->hasFullscreenControl);
    }

    /**
     * Define se o controle de search deve ser exibido.
     */
    protected function hasGeoSearchControl(): bool
    {
        return $this->evaluate($this->hasGeoSearchControl);
    }

    /**
     * Retorna o provedor de geosearch configurado.
     */
    protected function getGeoSearchProvider(): GeoSearchProvider
    {
        $geoSearchProvider = $this->evaluate($this->geoSearchProvider);

        return $geoSearchProvider instanceof GeoSearchProvider
            ? $geoSearchProvider
            : GeoSearchProvider::from($geoSearchProvider);
    }

    /**
     * Retorna a chave de API para geosearch.
     */
    protected function getGeoSearchApiKey(): ?string
    {
        if ($this->geoSearchApiKey !== null) {
            return $this->evaluate($this->geoSearchApiKey);
        }

        $geoSearchProvider = $this->getGeoSearchProvider();
        $envVariable = $geoSearchProvider->getApiKeyEnvVariable();

        if ($envVariable) {
            return env($envVariable);
        }

        return null;
    }

    /**
     * Define se o controle de escala deve ser exibido.
     */
    protected function hasScaleControl(): bool
    {
        return $this->evaluate($this->hasScaleControl);
    }

    /**
     * Define se o controle de zoom deve ser exibido.
     */
    protected function hasZoomControl(): bool
    {
        return $this->evaluate($this->hasZoomControl);
    }

    /**
     * Define se o controle de desenhar marcadores deve ser exibido.
     */
    protected function hasDrawMarkerControl(): bool
    {
        return $this->evaluate($this->hasDrawMarkerControl);
    }

    /**
     * Define se o controle de desenhar marcadores de círculo deve ser exibido.
     */
    protected function hasDrawCircleMarkerControl(): bool
    {
        return $this->evaluate($this->hasDrawCircleMarkerControl);
    }

    /**
     * Define se o controle de desenhar círculos deve ser exibido.
     */
    protected function hasDrawCircleControl(): bool
    {
        return $this->evaluate($this->hasDrawCircleControl);
    }

    /**
     * Define se o controle de desenhar polilinhas deve ser exibido.
     */
    protected function hasDrawPolylineControl(): bool
    {
        return $this->evaluate($this->hasDrawPolylineControl);
    }

    /**
     * Define se o controle de desenhar retângulos deve ser exibido.
     */
    protected function hasDrawRectangleControl(): bool
    {
        return $this->evaluate($this->hasDrawRectangleControl);
    }

    /**
     * Define se o controle de desenhar polígonos deve ser exibido.
     */
    protected function hasDrawPolygonControl(): bool
    {
        return $this->evaluate($this->hasDrawPolygonControl);
    }

    /**
     * Define se o controle de desenhar texto deve ser exibido.
     */
    protected function hasDrawTextControl(): bool
    {
        return $this->evaluate($this->hasDrawTextControl);
    }

    /**
     * Define se o modo de edição deve ser habilitado.
     */
    protected function hasEditLayersControl(): bool
    {
        return $this->evaluate($this->hasEditLayersControl);
    }

    /**
     * Define se o modo de arrastar deve ser habilitado.
     */
    protected function hasDragLayersControl(): bool
    {
        return $this->evaluate($this->hasDragLayersControl);
    }

    /**
     * Define se o modo de remover deve ser habilitado.
     */
    protected function hasRemoveLayersControl(): bool
    {
        return $this->evaluate($this->hasRemoveLayersControl);
    }

    /**
     * Define se o modo de rotação deve ser habilitado.
     */
    protected function hasRotateLayersControl(): bool
    {
        return $this->evaluate($this->hasRotateLayersControl);
    }

    /**
     * Define se o controle de cortar polígonos deve ser exibido.
     */
    protected function hasCutPolygonControl(): bool
    {
        return $this->evaluate($this->hasCutPolygonControl);
    }

    /**
     * Define um timeout para recentralizar o mapa após ele ser descentalizado.
     */
    public function getRecenterMapTimeout(): ?int
    {
        return $this->evaluate($this->recenterMapTimeout);
    }

    /**
     * Retorna as URLs das camadas de tiles
     */
    protected function getTileLayersUrl(): TileLayer|string|array
    {
        return $this->evaluate($this->tileLayersUrl);
    }

    /**
     * Retorna as opções de configuração de Zoom.
     */
    protected final function getZoomOptions(): array
    {
        return [
            'max' => $this->evaluate($this->maxZoom),
            'min' => $this->evaluate($this->minZoom),
        ];
    }

    /**
     * Retorna as configurações gerais do Leaflet.
     */
    protected final function getMapOptions(): array
    {
        return [
            'zoomControl'        => false, // Definido em getMapControls
            'attributionControl' => false, // Definido em getMapControls
            'scrollWheelZoom'    => $this->getMapZoomable(),
            'doubleClickZoom'    => $this->getMapZoomable(),
            'dragging'           => $this->getMapDraggable(),
            'recenterMapTimeout' => $this->getRecenterMapTimeout()
        ];
    }

    /**
     * Retorna controles definidos para o mapa.
     */
    protected final function getMapControls(): array
    {
        return [
            'attributionControl' => $this->hasAttributionControl(),
            'scaleControl'       => $this->hasScaleControl(),
            'zoomControl'        => $this->hasZoomControl(),
            'drawControls'       => $this->getDrawControls(),
            'fullscreenControl'  => $this->hasFullscreenControl(),
            'geoSearchControl'   => $this->hasGeoSearchControl(),
        ];
    }

    /**
     * Retorna configurações do geosearch para o controle de busca.
     */
    protected final function getGeoSearchConfig(): array
    {
        return [
            'provider' => $this->getGeoSearchProvider()->value,
            'apiKey'   => $this->getGeoSearchApiKey(),
        ];
    }

    /**
     * Retorna as configurações dos controles de desenho do Geoman.
     */
    protected final function getDrawControls(): array
    {
        return [
            'drawMarker'       => $this->hasDrawMarkerControl(),
            'drawCircleMarker' => $this->hasDrawCircleMarkerControl(),
            'drawCircle'       => $this->hasDrawCircleControl(),
            'drawPolyline'     => $this->hasDrawPolylineControl(),
            'drawRectangle'    => $this->hasDrawRectangleControl(),
            'drawPolygon'      => $this->hasDrawPolygonControl(),
            'drawText'         => $this->hasDrawTextControl(),
            'editMode'         => $this->hasEditLayersControl(),
            'dragMode'         => $this->hasDragLayersControl(),
            'removalMode'      => $this->hasRemoveLayersControl(),
            'rotateMode'       => $this->hasRotateLayersControl(),
            'cutPolygon'       => $this->hasCutPolygonControl(),
        ];
    }

    // === MARKERS & GEOJSON ===

    /**
     * Retorna a coleção de Markers a serem exibidos.
     * @return Marker[]
     */
    protected function getMarkers(): array
    {
        return [];
    }

    /**
     * Retorna a coleção de Shapes a serem exibidos.
     * @return Shape[]
     */
    protected function getShapes(): array
    {
        return [];
    }

    /**
     * Retorna a coleção de todos os Layers a serem exibidos.
     * @return array<BaseLayer|BaseLayerGroup>
     */
    protected function getLayers(): array
    {
        return array_merge(
            $this->getMarkers(),
            $this->getShapes()
        );
    }

    /**
     * Prepara e cacheia todos os dados de layers e grupos
     */
    private function getCachedLayerData(): ?array
    {
        if ($this->cachedLayerData === null) {

            $layers = collect($this->getLayers())
                ->flatMap(function (BaseLayer|BaseLayerGroup $layerOrGroup) {
                    if ($layerOrGroup instanceof BaseLayerGroup) {
                        return $layerOrGroup->getLayers();
                    }
                    return [$layerOrGroup];
                });

            $groupsMap = collect();

            $layers->each(function (BaseLayer &$layer) use (&$groupsMap) {
                $group = $layer->getGroup();

                if ($group === null) {
                    return;
                }

                if ($group instanceof BaseLayerGroup) {
                    // Grupo já é um objeto, adiciona ao mapa pelo ID
                    $groupsMap->put($group->getId(), $group);
                    return;
                }

                // Grupo é string (nome), cria LayerGroup se não existir
                $newGroup = null;
                if ($groupsMap->has($group)) {
                    $newGroup = $groupsMap->get($group);
                } else {
                    $newGroup = new LayerGroup(name: $group);
                    $groupsMap->put($group, $newGroup);
                }

                $layer->group($newGroup);
            });

            $this->cachedLayerData = [
                'layers' => $layers->all(),
                'groups' => $groupsMap->values()->all(),
            ];
        }

        return $this->cachedLayerData;
    }

    /**
     * Retorna os layers em cache ou criá-os, caso não existam.
     * @return array<BaseLayer>
     */
    private function getCachedLayers(): array
    {
        return $this->getCachedLayerData()['layers'];
    }

    /**
     * Retorna os layers groups em cache ou criá-os, caso não existam.
     * @return array<BaseLayerGroup>
     */
    private function getCachedLayerGroups(): array
    {
        return $this->getCachedLayerData()['groups'];
    }

    /**
     * Retorna dados de densidade para o GeoJSON (ex: colorir estados).
     */
    protected function getGeoJsonData(): array
    {
        return [];
    }

    /**
     * Retorna a paleta de cores para a densidade.
     */
    protected function getGeoJsonColors(): array
    {
        return $this->geoJsonColors;
    }

    /**
     * Retorna a URL do arquivo GeoJSON.
     */
    protected function getGeoJsonUrl(): ?string
    {
        if ($this->geoJsonUrl) {
            return $this->geoJsonUrl;
        }

        return asset('vendor/filament-leaflet/maps/brazil.json');
    }

    /**
     * Retorna o template HTML para o tooltip do GeoJSON.
     */
    protected function getGeoJsonTooltip(): string
    {
        return <<< HTML
            <h4>{state}</h4>
            <b>Density: {density}</b>
        HTML;
    }

    // === EVENTS & HANDLERS ===

    /**
     * Obtém um Layer pelo id
     */
    protected function getLayerById(string $id): ?BaseLayer
    {
        foreach ($this->getCachedLayers() as &$cachedLayer) {
            if ($cachedLayer->getId() == $id) {
                return $cachedLayer;
            }
        }

        return null;
    }

    /**
     * Evento disparado quando um layer é atualizado (ex: arrastado, editado).
     */
    public final function handleLayerUpdated(string $layerId, array $data): void
    {
        if (($layer = $this->getLayerById($layerId))) {
            $layer->updateLayer($data);
        }
    }

    /**
     * Evento disparado quando um layer é clicado
     */
    public function handleLayerClick(string|BaseLayer $layerId): void
    {
        $layer = $layerId instanceof BaseLayer
            ? $layerId
            : $this->getLayerById($layerId);

        if ($layer) {
            $layer->execClickAction();
        }
    }

    /**
     * Executado quando o mapa é clicado.
     */
    public function handleMapClick(float $latitude, float $longitude): void {}

    /**
     * Atualiza o mapa (dispara evento para o frontend).
     */
    #[On('refresh-maps')]
    public function refreshMap(): void
    {
        $this->dispatch('update-leaflet-' . $this->getId(), config: $this->getMapData());
    }

    /**
     * Prepara os dados para o Frontend (JS).
     */
    private function preparedLayers(): array
    {
        return collect($this->getCachedLayers())
            ->values()
            ->toArray();
    }

    /**
     * Formata os tileLayers para o formato esperado pelo JS.
     */
    private  function preparedTileLayersUrl(): array
    {
        $tileLayersUrl = $this->getTileLayersUrl();

        if (!is_array($tileLayersUrl)) {
            $tileLayersUrl = [$tileLayersUrl];
        }

        return collect($tileLayersUrl)
            ->map(function ($layer, $key) {
                $label = match (true) {
                    is_string($key) => $key,
                    $layer instanceof TileLayer => $layer->getLabel(),
                    default => 'Layer ' . ($key + 1)
                };

                $url = ($layer instanceof TileLayer) ? $layer->getUrl() : $layer;
                $attribution  = ($layer instanceof TileLayer) ? $layer->getAttribution() : null;

                return [$label, $url, $attribution];
            })->values()
            ->toArray();
    }

    /**
     * Formata os layer groups para o formato esperado pelo JS.
     */
    private function preparedLayerGroups(): array
    {
        return collect($this->getCachedLayerGroups())
            ->values()
            ->toArray();
    }

    /**
     * Retorna todos os dados de configuração para o componente JS.
     */
    public final function getMapData(): array
    {
        return [
            'mapId'           => $this->getId(),
            'mapHeight'       => $this->getMapHeight(),
            'defaultCoord'    => $this->getMapCenter(),
            'autoCenter'      => $this->getAutoCenter(),
            'fitBounds'       => $this->getFitBounds(),
            'defaultZoom'     => $this->getDefaultZoom(),
            'geoJsonColors'   => $this->getGeoJsonColors(),
            'geoJsonData'     => $this->getGeoJsonData(),
            'infoText'        => $this->getGeoJsonTooltip(),
            'tileLayersUrl'   => $this->preparedTileLayersUrl(),
            'layerGroupsData' => $this->preparedLayerGroups(),
            'layersData'      => $this->preparedLayers(),
            'zoomConfig'      => $this->getZoomOptions(),
            'mapConfig'       => $this->getMapOptions(),
            'mapControls'     => $this->getMapControls(),
            'geoSearchConfig' => $this->getGeoSearchConfig(),
            'geoJsonUrl'      => $this->getGeoJsonUrl(),
            'customStyles'    => $this->getCustomStyles(),
            'customScripts'   => $this->getCustomScripts(),
        ];
    }

    // === ACCESSORS ===

    public function getCustomScripts(): string
    {
        return '';
    }

    public function getCustomStyles(): string
    {
        return '';
    }
}
