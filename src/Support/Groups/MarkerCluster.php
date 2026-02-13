<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Groups;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\Enums\Color;
use EduardoRibeiroDev\FilamentLeaflet\Support\BaseLayerGroup;
use EduardoRibeiroDev\FilamentLeaflet\Support\Markers\Marker;
use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasColor;
use Illuminate\Database\Eloquent\Model;

class MarkerCluster extends BaseLayerGroup
{
    use HasColor;

    /** @var Marker|array[] */
    protected ?array $modelMarkers = null;
    protected ?string $group = null;

    // Model Binding Configuration
    protected ?string $model = null;
    protected ?Closure $modifyQueryCallback = null;
    protected ?Closure $mapRecordCallback = null;

    // Mapeamento de colunas
    protected ?string $latColumn = null;
    protected ?string $lngColumn = null;
    protected ?string $jsonColumn = null;
    protected ?string $titleColumn = null;
    protected ?string $descriptionColumn = null;
    protected ?array $popupFieldsColumns = null;
    protected ?string $iconUrl = null;

    /**
     * @param array<Marker> $markers
     */
    public static function make(?array $markers = null): static
    {
        return new static($markers);
    }

    /**
     * Configura o cluster para carregar marcadores a partir de um Model.
     */
    public static function fromModel(
        string $model,
        string $latColumn = 'latitude',
        string $lngColumn = 'longitude',
        ?string $jsonColumn = null,
        ?string $titleColumn = null,
        ?string $descriptionColumn = null,
        ?array $popupFieldsColumns = null,
        null|string|Color $color = null,
        ?string $iconUrl = null,
        ?Closure $mapRecordCallback = null,
        ?Closure $modifyQueryCallback = null
    ): static {
        $instance = new static([]);

        $instance->model = $model;
        $instance->latColumn = $latColumn;
        $instance->lngColumn = $lngColumn;
        $instance->jsonColumn = $jsonColumn;
        $instance->titleColumn = $titleColumn;
        $instance->descriptionColumn = $descriptionColumn;
        $instance->popupFieldsColumns = $popupFieldsColumns;
        $instance->color($color);
        $instance->iconUrl = $iconUrl;

        // Callbacks
        if ($mapRecordCallback) {
            $instance->mapRecordUsing($mapRecordCallback);
        }

        if ($modifyQueryCallback) {
            $instance->modifyQueryUsing($modifyQueryCallback);
        }

        return $instance;
    }

    /*
    |--------------------------------------------------------------------------
    | Métodos abstratos do Layer Group
    |--------------------------------------------------------------------------
    */

    public function getType(): string
    {
        return 'cluster';
    }

    /*
    |--------------------------------------------------------------------------
    | Gerenciamento de Marcadores
    |--------------------------------------------------------------------------
    */

    public function marker(Marker|array $marker): static
    {
        $this->layers[] = $marker;
        return $this;
    }

    public function markers(array $markers): static
    {
        foreach ($markers as $marker) {
            $this->marker($marker);
        }
        return $this;
    }

    public function clearMarkers(): static
    {
        $this->layers = [];
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Lógica de Resolução dos Marcadores
    |--------------------------------------------------------------------------
    */

    /**
     * Retorna a combinação dos marcadores manuais e dos marcadores vindos do Model.
     * @return array<Marker>
     */
    public function getLayers(): array
    {
        if ($this->model && !$this->modelMarkers) {
            $this->modelMarkers = $this->resolveModelMarkers();
            $this->layers = array_merge($this->layers, $this->modelMarkers);
        }

        return parent::getLayers();
    }

    /**
     * Executa a query e transforma os registros em Markers.
     */
    protected function resolveModelMarkers(): array
    {
        $query = $this->model::query();

        if (is_callable($this->modifyQueryCallback)) {
            $query = call_user_func($this->modifyQueryCallback, $query);
        }

        return $query->get()->map(
            fn(Model $record) => Marker::fromRecord(
                $record,
                $this->latColumn,
                $this->lngColumn,
                $this->jsonColumn,
                $this->titleColumn,
                $this->descriptionColumn,
                $this->popupFieldsColumns,
                $this->color,
                $this->iconUrl,
                $this->mapRecordCallback
            )
        )->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Configurações do Cluster
    |--------------------------------------------------------------------------
    */

    public function maxClusterRadius(int $radius): static
    {
        return $this->option('maxClusterRadius', $radius);
    }

    public function showCoverageOnHover(bool $show = true): static
    {
        return $this->option('showCoverageOnHover', $show);
    }

    public function zoomToBoundsOnClick(bool $zoom = true): static
    {
        return $this->option('zoomToBoundsOnClick', $zoom);
    }

    public function spiderfyOnMaxZoom(bool $spiderfy = true): static
    {
        return $this->option('spiderfyOnMaxZoom', $spiderfy);
    }

    public function removeOutsideVisibleBounds(bool $remove = true): static
    {
        return $this->option('removeOutsideVisibleBounds', $remove);
    }

    public function disableClusteringAtZoom(int $zoomLevel): static
    {
        return $this->option('disableClusteringAtZoom', $zoomLevel);
    }

    public function animate(int $animate): static
    {
        return $this->option('animate', $animate);
    }

    /*
    |--------------------------------------------------------------------------
    | Vínculo com Model
    |--------------------------------------------------------------------------
    */

    public function model(string $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function modifyQueryUsing(?Closure $callback): static
    {
        $this->modifyQueryCallback = $callback;
        return $this;
    }

    public function mapRecordUsing(?Closure $callback): static
    {
        $this->mapRecordCallback = $callback;
        return $this;
    }

    public function iconUrl(string $url): static
    {
        $this->iconUrl = $url;
        return $this;
    }
}
