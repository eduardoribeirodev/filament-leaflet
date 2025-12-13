<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Widgets;

use EduardoRibeiroDev\FilamentLeaflet\Enums\Color;
use EduardoRibeiroDev\FilamentLeaflet\Enums\TileLayer;
use EduardoRibeiroDev\FilamentLeaflet\Support\Layer;
use EduardoRibeiroDev\FilamentLeaflet\Support\Shapes\Shape;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Exception;
use Filament\Forms\Components\Hidden;

abstract class MapWidget extends Widget implements HasSchemas, HasActions
{
    use InteractsWithSchemas;
    use InteractsWithActions;

    protected static bool $isLazy = false;

    protected string $view = 'filament-leaflet::widgets.map-widget';

    protected static ?string $heading = null;

    // Configurações padrão do mapa
    protected static array $mapCenter = [-14.235, -51.9253]; // Centro do Brasil
    protected static int $defaultZoom = 4;
    protected static int $mapHeight = 504;
    protected static bool $hasAttributionControl = false;
    protected static int $maxZoom = 18;
    protected static int $minZoom = 2;

    /** @var TileLayer|string[] */
    protected static array $tileLayersUrl = [TileLayer::OpenStreetMap];

    // Configurações do GeoJSON Density
    protected static ?string $geoJsonUrl = null;
    protected static array $geoJsonColors = [
        '#FED976',
        '#FEB24C',
        '#FD8D3C',
        '#FC4E2A',
        '#E31A1C',
        '#BD0026',
        '#800026'
    ];

    // Configurações dos marcadores
    protected static ?string $markerModel = null;
    protected static ?string $markerResource = null;
    protected static string $latitudeColumnName = 'latitude';
    protected static string $longitudeColumnName = 'longitude';
    protected static ?string $jsonCoordinatesColumnName = null;
    protected static int $formColumns = 2;

    /**
     * Retorna o título do widget
     */
    public function getHeading(): string
    {
        return static::$heading ?? __('Map');
    }

    /**
     * Retorna as coordenadas centrais do mapa.
     */
    public static function getMapCenter(): array
    {
        return static::$mapCenter;
    }

    /**
     * Retorna o zoom inicial padrão.
     */
    public static function getDefaultZoom(): int
    {
        return static::$defaultZoom;
    }

    /**
     * Retorna a altura do mapa em pixels.
     */
    public static function getMapHeight(): int
    {
        return static::$mapHeight;
    }

    /**
     * Define se o controle de atribuição deve ser exibido.
     */
    public static function hasAttributionControl(): bool
    {
        return static::$hasAttributionControl;
    }

    /**
     * Retorna as URLs das camadas de tiles
     */
    public static function getTileLayersUrl(): array
    {
        return static::$tileLayersUrl;
    }

    /**
     * Retorna as opções de configuração de Zoom.
     */
    public static function getZoomOptions(): array
    {
        return [
            'max' => static::$maxZoom,
            'min' => static::$minZoom,
        ];
    }

    /**
     * Retorna as configurações gerais do Leaflet.
     */
    public static function getMapOptions(): array
    {
        return [
            'scrollWheelZoom' => true,
            'doubleClickZoom' => true,
            'dragging' => true,
            'zoomControl' => true,
            'attributionControl' => static::hasAttributionControl(),
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
     * @return Layer[]
     */
    protected function getLayers(): array
    {
        return array_merge(
            $this->getMarkers(),
            $this->getShapes()
        );
    }

    /**
     * Processa os layers garantindo IDs únicos.
     * 
     * @return Layer[]
     */
    private function getProcessedLayers(): array
    {
        $indexes = [];
        $layers = $this->getLayers();

        return collect($layers)
            ->map(function (Layer $layer) use (&$indexes) {
                if (!$layer->getId()) {
                    $type = $layer->getType();

                    $indexes[$type] = ($indexes[$type] ?? 0) + 1;

                    $id = $type . '-' . $indexes[$type];
                    $layer->id($id);
                }

                return $layer;
            })->all();
    }

    /**
     * Retorna dados de densidade para o GeoJSON (ex: colorir estados).
     */
    public function getGeoJsonData(): array
    {
        return [];
    }

    /**
     * Retorna a paleta de cores para a densidade.
     */
    public function getGeoJsonColors(): array
    {
        return static::$geoJsonColors;
    }

    /**
     * Retorna a URL do arquivo GeoJSON.
     */
    public function getGeoJsonUrl(): ?string
    {
        if (static::$geoJsonUrl) {
            return static::$geoJsonUrl;
        }

        return asset('vendor/filament-leaflet/maps/brazil.json');
    }

    /**
     * Retorna o template HTML para o tooltip do GeoJSON.
     */
    public static function getGeoJsonTooltip(): string
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
    protected function getLayerById(string $id): ?Layer
    {
        foreach ($this->getProcessedLayers() as $layer) {
            if ($layer->getId() == $id) {
                return $layer;
            }
        }

        return null;
    }

    /**
     * Evento disparado quando um layer é clicado
     */
    public function onLayerClick(string $layerId): void
    {
        // Busca o layer e executa sua ação
        $layer = $this->getLayerById($layerId);

        if ($layer)
            $layer->execClickAction();
    }

    /**
     * Executado quando o mapa é clicado.
     */
    public function onMapClick(float $latitude, float $longitude): void
    {
        if (static::$markerModel) {
            $this->mountAction('createMarker', [
                static::getLatitudeColumnName() => $latitude,
                static::getLongitudeColumnName() => $longitude
            ]);
        }
    }

    /**
     * Atualiza o mapa (dispara evento para o frontend).
     */
    #[On('update-map')]
    public function refreshMap(): void
    {
        $this->dispatch('update-leaflet-' . $this->getId(), config: $this->getWidgetData());
    }

    // === CREATE ACTION & FORM ===

    /**
     * Define os componentes do formulário de criação.
     */
    protected static function getFormComponents(): array
    {
        return [
            TextInput::make('name')
                ->translateLabel()
                ->required()
                ->maxLength(255),

            Select::make('color')
                ->translateLabel()
                ->native(false)
                ->options(Color::class),

            Textarea::make('description')
                ->translateLabel()
                ->maxLength(1000)
                ->columnSpanFull(),
        ];
    }

    /**
     * Define o schema do formulário de criação.
     */
    protected static function getFormSchema(Schema $schema): Schema
    {
        if (static::getMarkerResource()) {
            $schema = static::getMarkerResource()::form($schema);
        } else {
            $schema->schema(static::getFormComponents());
        }

        static::ensureFormHasCoordinateFields($schema);

        return $schema->columns(static::getFormColumns());
    }

    private static function ensureFormHasCoordinateFields(Schema &$form): void
    {
        $hasLat = $form->getComponent(static::getLatitudeColumnName());
        $hasLng = $form->getComponent(static::getLongitudeColumnName());

        if ($hasLat && $hasLng) {
            return;
        }

        $components = $form->getComponents();

        if (!$hasLat) {
            $components[] = Hidden::make(static::getLatitudeColumnName());
        }

        if (!$hasLng) {
            $components[] = Hidden::make(static::getLongitudeColumnName());
        }

        $form->schema($components);
    }

    /**
     * Retorna a Action de criação de marker.
     */
    public function createMarkerAction(): Action
    {
        return CreateAction::make('createMarker')
            ->model(self::getMarkerModel())
            ->mountUsing(function (Schema $form, array $arguments) {
                $form->fill();

                $data = array_merge(
                    $form->getRawState(),
                    $arguments
                );

                $form->fill($data);
            })
            ->schema(fn(Schema $schema) => static::getFormSchema($schema))
            ->mutateDataUsing(fn(array $data) => $this->mutateFormDataBeforeCreate($data))
            ->using(function (?string $model, array $data) {

                if ($model === null) {
                    throw new Exception('The $markerModel should be defined in the class ' . static::class);
                }

                try {
                    $newRecord = $model::create($data);
                    $this->refreshMap();
                    $this->dispatch('marker-created');
                    $this->afterMarkerCreated($newRecord);
                } catch (Exception $e) {
                    throw new Exception('Error on creating Marker: ' . $e->getMessage());
                }
            });
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Se a configuração for para salvar em JSON, converte os campos planos para array
        if (static::shouldSaveCoordinatesAsJson()) {
            $latCol = static::getLatitudeColumnName();
            $lngCol = static::getLongitudeColumnName();
            $jsonCol = static::getJsonCoordinatesColumnName();

            $data[$jsonCol] = [
                'latitude' => $data[$latCol],
                'longitude' => $data[$lngCol]
            ];

            unset($data[$latCol], $data[$lngCol]);
        }

        return $data;
    }

    protected function afterMarkerCreated(Model $record): void {}

    /**
     * Prepara os dados para o Frontend (JS).
     */
    private function preparedLayers(): array
    {
        return collect($this->getProcessedLayers())
            ->filter(fn(Layer $layer) => $layer->isValid())
            ->toArray();
    }

    /**
     * Formata os tileLayers para o formato esperado pelo JS.
     */
    private static function preparedTileLayersUrl(): array
    {
        return collect(static::getTileLayersUrl())
            ->map(function ($layer, $key) {
                $label = match (true) {
                    is_string($key) => $key,
                    $layer instanceof TileLayer => $layer->getLabel(),
                    default => 'Layer ' . ($key + 1)
                };

                $url = ($layer instanceof TileLayer) ? $layer->value : $layer;
                $attribution  = ($layer instanceof TileLayer) ? $layer->getAttribution() : null;

                return [$label, $url, $attribution];
            })->toArray();
    }

    /**
     * Retorna todos os dados de configuração para o componente JS.
     */
    public final function getWidgetData(): array
    {
        return [
            'defaultCoord'  => static::getMapCenter(),
            'defaultZoom'   => static::getDefaultZoom(),
            'mapHeight'     => static::getMapHeight(),
            'geoJsonColors' => $this->getGeoJsonColors(),
            'geoJsonData'   => $this->getGeoJsonData(),
            'infoText'      => static::getGeoJsonTooltip(),
            'tileLayersUrl' => static::preparedTileLayersUrl(),
            'layers'        => $this->preparedLayers(),
            'zoomConfig'    => static::getZoomOptions(),
            'mapConfig'     => static::getMapOptions(),
            'geoJsonUrl'    => $this->getGeoJsonUrl(),
        ];
    }

    // === ACCESSORS / HELPERS ===

    public static function getMarkerModel(): ?string
    {
        return static::$markerModel;
    }

    public static function getMarkerResource(): ?string
    {
        return static::$markerResource;
    }

    public static function getFormColumns(): int
    {
        return static::$formColumns;
    }

    public static function shouldSaveCoordinatesAsJson(): bool
    {
        return !is_null(static::getJsonCoordinatesColumnName());
    }

    public static function getLatitudeColumnName(): string
    {
        return static::$latitudeColumnName;
    }

    public static function getLongitudeColumnName(): string
    {
        return static::$longitudeColumnName;
    }

    public static function getJsonCoordinatesColumnName(): ?string
    {
        return static::$jsonCoordinatesColumnName;
    }

    public function getAdditionalScripts(): string
    {
        return '';
    }

    public function afterMapInit(): string
    {
        return '';
    }

    public function getCustomStyles(): string
    {
        return "";
    }
}
