<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Concerns;

use EduardoRibeiroDev\FilamentLeaflet\Enums\TileLayer;
use EduardoRibeiroDev\FilamentLeaflet\Support\BaseLayer;
use EduardoRibeiroDev\FilamentLeaflet\Support\BaseLayerGroup;
use EduardoRibeiroDev\FilamentLeaflet\Support\Groups\LayerGroup;
use EduardoRibeiroDev\FilamentLeaflet\Support\Shapes\Shape;
use Livewire\Attributes\On;

trait HasMapConfig
{
    // Configurações padrão do mapa
    protected array $mapCenter = [-14.235, -51.9253]; // Centro do Brasil
    protected bool $autoCenter = false;
    protected int $defaultZoom = 4;
    protected int $mapHeight = 598;
    protected ?int $recenterMapTimeout = null;
    protected bool $mapDraggable = true;
    protected bool $mapZoomable = true;

    // Configurações de controles
    protected bool $hasAttributionControl = false;
    protected bool $hasFullscreenControl = false;
    protected bool $hasSearchControl = false;
    protected bool $hasScaleControl = false;
    protected bool $hasZoomControl = true;

    // Controles do Geoman
    protected bool $hasDrawMarkerControl = false;
    protected bool $hasDrawCircleMarkerControl = false;
    protected bool $hasDrawCircleControl = false;
    protected bool $hasDrawPolylineControl = false;
    protected bool $hasDrawRectangleControl = false;
    protected bool $hasDrawPolygonControl = false;
    protected bool $hasDrawTextControl = false;
    protected bool $hasEditLayersControl = false;
    protected bool $hasDragLayersControl = false;
    protected bool $hasRemoveLayersControl = false;
    protected bool $hasRotateLayersControl = false;
    protected bool $hasCutPolygonControl = false;
    /** @deprecated */
    protected bool $hasDrawControl = false;

    protected int $maxZoom = 18;
    protected int $minZoom = 2;

    /** @var TileLayer|string|array */
    protected TileLayer|string|array $tileLayersUrl = TileLayer::OpenStreetMap;

    // Configurações do GeoJSON Density
    protected ?string $geoJsonUrl = null;
    protected array $geoJsonColors = [
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
        return $this->mapCenter;
    }

    /**
     * Define se o mapa deve ser centralizado na localização atual do usuário.
     */
    protected function getAutoCenter(): bool
    {
        return $this->autoCenter;
    }

    /**
     * Retorna o zoom inicial padrão.
     */
    protected function getDefaultZoom(): int
    {
        return $this->defaultZoom;
    }

    /**
     * Retorna a altura do mapa em pixels.
     */
    protected function getMapHeight(): int
    {
        return $this->mapHeight;
    }

    /**
     * Define se o mapa pode ser arrastado.
     */
    public function getMapDraggable(): bool
    {
        return $this->mapDraggable;
    }

    /**
     * Define se o mapa pode ser ampliado/reduzido.
     */
    public function getMapZoomable(): bool
    {
        return $this->mapZoomable;
    }

    /**
     * Define se o controle de atribuição deve ser exibido.
     */
    protected function hasAttributionControl(): bool
    {
        return $this->hasAttributionControl;
    }

    /**
     * Define se o controle de fullscreen deve ser exibido.
     */
    protected function hasFullscreenControl(): bool
    {
        return $this->hasFullscreenControl;
    }

    /**
     * Define se o controle de search deve ser exibido.
     */
    protected function hasSearchControl(): bool
    {
        return $this->hasSearchControl;
    }

    /**
     * Define se o controle de escala deve ser exibido.
     */
    protected function hasScaleControl(): bool
    {
        return $this->hasScaleControl;
    }

    /**
     * Define se o controle de zoom deve ser exibido.
     */
    protected function hasZoomControl(): bool
    {
        return $this->hasZoomControl;
    }

    /**
     * @deprecated Use os métodos específicos de controle de desenho.
     * Define se o controle de desenho deve ser exibido.
     */
    protected function hasDrawControl(): bool
    {
        return $this->hasDrawControl;
    }

    /**
     * Define se o controle de desenhar marcadores deve ser exibido.
     */
    protected function hasDrawMarkerControl(): bool
    {
        return $this->hasDrawMarkerControl;
    }

    /**
     * Define se o controle de desenhar marcadores de círculo deve ser exibido.
     */
    protected function hasDrawCircleMarkerControl(): bool
    {
        return $this->hasDrawCircleMarkerControl;
    }

    /**
     * Define se o controle de desenhar círculos deve ser exibido.
     */
    protected function hasDrawCircleControl(): bool
    {
        return $this->hasDrawCircleControl;
    }

    /**
     * Define se o controle de desenhar polilinhas deve ser exibido.
     */
    protected function hasDrawPolylineControl(): bool
    {
        return $this->hasDrawPolylineControl;
    }

    /**
     * Define se o controle de desenhar retângulos deve ser exibido.
     */
    protected function hasDrawRectangleControl(): bool
    {
        return $this->hasDrawRectangleControl;
    }

    /**
     * Define se o controle de desenhar polígonos deve ser exibido.
     */
    protected function hasDrawPolygonControl(): bool
    {
        return $this->hasDrawPolygonControl;
    }

    /**
     * Define se o controle de desenhar texto deve ser exibido.
     */
    protected function hasDrawTextControl(): bool
    {
        return $this->hasDrawTextControl;
    }

    /**
     * Define se o modo de edição deve ser habilitado.
     */
    protected function hasEditLayersControl(): bool
    {
        return $this->hasEditLayersControl;
    }

    /**
     * Define se o modo de arrastar deve ser habilitado.
     */
    protected function hasDragLayersControl(): bool
    {
        return $this->hasDragLayersControl;
    }

    /**
     * Define se o modo de remover deve ser habilitado.
     */
    protected function hasRemoveLayersControl(): bool
    {
        return $this->hasRemoveLayersControl;
    }

    /**
     * Define se o modo de rotação deve ser habilitado.
     */
    protected function hasRotateLayersControl(): bool
    {
        return $this->hasRotateLayersControl;
    }

    /**
     * Define se o controle de cortar polígonos deve ser exibido.
     */
    protected function hasCutPolygonControl(): bool
    {
        return $this->hasCutPolygonControl;
    }

    /**
     * Define um timeout para recentralizar o mapa após ele ser descentalizado.
     */
    public function getRecenterMapTimeout(): ?int
    {
        return $this->recenterMapTimeout;
    }

    /**
     * Retorna as URLs das camadas de tiles
     */
    protected function getTileLayersUrl(): TileLayer|string|array
    {
        return $this->tileLayersUrl;
    }

    /**
     * Retorna as opções de configuração de Zoom.
     */
    protected function getZoomOptions(): array
    {
        return [
            'max' => $this->maxZoom,
            'min' => $this->minZoom,
        ];
    }

    /**
     * Retorna as configurações gerais do Leaflet.
     */
    protected function getMapOptions(): array
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
    protected function getMapControls(): array
    {
        return [
            'attributionControl' => $this->hasAttributionControl(),
            'scaleControl'       => $this->hasScaleControl(),
            'zoomControl'        => $this->hasZoomControl(),
            'drawControls'       => $this->getDrawControls(),
            'fullscreenControl'  => $this->hasFullscreenControl(),
            'searchControl'      => $this->hasSearchControl(),
        ];
    }

    /**
     * Retorna as configurações dos controles de desenho do Geoman.
     */
    protected function getDrawControls(): array
    {
        if ($this->hasDrawControl()) {
            return []; // habilita todos os controles individuais
        }

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
     * Evento disparado quando um layer é clicado
     */
    public function handleLayerClick(string $layerId): void
    {
        // Busca o layer e executa sua ação
        $layer = $this->getLayerById($layerId);

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
            ->filter(fn(BaseLayer $layer) => $layer->isValid())
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

                $url = ($layer instanceof TileLayer) ? $layer->value : $layer;
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
            'mapId'         => $this->getId(),
            'mapHeight'     => $this->getMapHeight(),
            'defaultCoord'  => $this->getMapCenter(),
            'autoCenter'    => $this->getAutoCenter(),
            'defaultZoom'   => $this->getDefaultZoom(),
            'geoJsonColors' => $this->getGeoJsonColors(),
            'geoJsonData'   => $this->getGeoJsonData(),
            'infoText'      => $this->getGeoJsonTooltip(),
            'tileLayersUrl' => $this->preparedTileLayersUrl(),
            'layerGroups'   => $this->preparedLayerGroups(),
            'layers'        => $this->preparedLayers(),
            'zoomConfig'    => $this->getZoomOptions(),
            'mapConfig'     => $this->getMapOptions(),
            'mapControls'   => $this->getMapControls(),
            'geoJsonUrl'    => $this->getGeoJsonUrl(),
            'customStyles'  => $this->getCustomStyles(),
            'customScripts' => $this->getCustomScripts(),
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
