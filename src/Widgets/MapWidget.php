<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Widgets;

use EduardoRibeiroDev\FilamentLeaflet\Enums\MarkerColor;
use EduardoRibeiroDev\FilamentLeaflet\Support\Marker;
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
use Error;

abstract class MapWidget extends Widget implements HasSchemas, HasActions
{
    use InteractsWithSchemas;
    use InteractsWithActions;

    protected static bool $isLazy = false;

    protected string $view = 'filament-leaflet::widgets.base-map';

    protected static ?string $heading = null;

    // Configurações padrão do mapa
    protected static array $mapCenter = [-14.235, -51.9253]; // Centro do Brasil
    protected static int $defaultZoom = 4;
    protected static int $mapHeight = 504;
    protected static bool $hasAttributionControl = false;
    protected static string $tileLayerUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    protected static int $maxZoom = 18;
    protected static int $minZoom = 2;

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
    protected static string $latitudeColumnName = 'latitude';
    protected static string $longitudeColumnName = 'longitude';
    protected static ?string $jsonCoordinatesColumnName = null;
    protected static int $formColumns = 2;

    /**
     * Estado interno para cliques
     */
    public ?float $clickedLatitude = null;
    public ?float $clickedLongitude = null;

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
     * Retorna a URL da camada de tiles (Tile Layer).
     */
    public static function getTileLayerUrl(): string
    {
        return static::$tileLayerUrl;
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
     */
    public function getMarkers(): array
    {
        return [];
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
     * Executado quando um marker é clicado.
     */
    public final function onMarkerClick(string $markerId): void
    {
        foreach ($this->getMarkers() as $marker) {
            if (!$marker instanceof Marker) {
                continue;
            }

            if ($marker->clickAction && $marker->getId() == $markerId) {
                $callback = $marker->clickAction;
                $callback($marker);
            }
        }
    }

    /**
     * Executado quando o mapa é clicado (área vazia).
     */
    public function onMapClick(float $latitude, float $longitude): void
    {
        $this->clickedLatitude = $latitude;
        $this->clickedLongitude = $longitude;

        if (static::$markerModel) {
            $this->mountCreateAction();
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
                ->options(MarkerColor::class),

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
        return $schema
            ->schema(static::getFormComponents())
            ->columns(static::getFormColumns());
    }

    /**
     * Retorna a Action de criação de marker.
     */
    public function createMarkerAction(): Action
    {
        return CreateAction::make('createMarker')
            ->model(self::getMarkerModel())
            ->schema(fn(Schema $schema) => static::getFormSchema($schema))
            ->mutateDataUsing(fn(array $data) => $this->mutateFormDataBeforeCreate($data))
            ->using(function ($model, $data) {

                if ($model === null) {
                    throw new Exception('The $markerModel should be defined in the class ' . static::class);
                }

                try {
                    $newRecord = $model::create($data);
                    $this->refreshMap();
                    $this->dispatch('marker-created');
                    $this->afterMarkerCreated($newRecord);
                } catch (Error | Exception $e) {
                    throw new Exception('Error on creating Marker: ' . $e->getMessage());
                }
            });
    }

    /**
     * Monta (abre) a modal da action de criação.
     */
    protected final function mountCreateAction(): void
    {
        $this->mountAction('createMarker');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $coordsData = [
            static::getLatitudeColumnName() => $this->getClickedLatitude(),
            static::getLongitudeColumnName() => $this->getClickedLongitude()
        ];

        if (static::shouldSaveCoordinatesAsJson()) {
            $jsonColumn = static::getJsonCoordinatesColumnName();
            $data[$jsonColumn] = $coordsData;
        } else {
            $data = array_merge($data, $coordsData);
        }

        return $data;
    }

    protected function afterMarkerCreated(Model $record): void {}

    /**
     * Formata os markers para o formato esperado pelo JS.
     */
    private function prepareMarkers(): array
    {
        $markers = $this->getMarkers();

        if (empty($markers)) {
            return [];
        }

        return collect($markers)->map(function ($marker) {
            if ($marker instanceof Marker) {
                $marker->validate();
                return $marker->toArray();
            }
            return $marker;
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
            'markers'       => $this->prepareMarkers(),
            'infoText'      => static::getGeoJsonTooltip(),
            'tileLayerUrl'  => static::getTileLayerUrl(),
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

    public static function getFormColumns(): int
    {
        return static::$formColumns;
    }

    public function getClickedLatitude(): ?float
    {
        return $this->clickedLatitude;
    }

    public function getClickedLongitude(): ?float
    {
        return $this->clickedLongitude;
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
