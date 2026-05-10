<?php

namespace EduardoRibeiroDev\FilamentLeaflet\LayerGroups;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\LayerGroups\BaseLayerGroup;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Marker;
use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasColor;
use Illuminate\Database\Eloquent\Model;

class MarkerCluster extends BaseLayerGroup
{
    use HasColor;

    protected ?int $maxClusterRadius = null;
    protected ?bool $showCoverageOnHover = null;
    protected ?bool $zoomToBoundsOnClick = null;
    protected ?bool $spiderfyOnMaxZoom = null;
    protected ?bool $removeOutsideVisibleBounds = null;
    protected ?int $disableClusteringAtZoom = null;
    protected ?int $animate = null;

    /** @var Marker|array[] */
    protected ?array $modelMarkers = null;
    protected ?string $group = null;

    // Model Binding Configuration
    protected ?string $model = null;
    protected ?Closure $modifyQueryCallback = null;
    protected ?Closure $mapRecordCallback = null;
    protected ?bool $syncRecords = null;

    // Mapeamento de colunas
    protected ?string $coordsColumn = null;
    protected ?string $titleColumn = null;
    protected ?string $descriptionColumn = null;
    protected ?array $popupFieldsColumns = null;
    protected ?string $iconUrl = null;

    /**
     * Create a new MarkerCluster instance. You can optionally pass an array of Marker instances to initialize the cluster with.
     * @param array<Marker> $markers An optional array of Marker instances to initialize the cluster with. You can add markers to the cluster later using the marker() or markers() methods, or by binding an Eloquent model with the fromModel() method.
     * @return static
     */
    public static function make(array $markers = []): static
    {
        return new static($markers);
    }

    /**
     * Convenience method to create a MarkerCluster instance directly from an Eloquent model. This method allows you to specify the model class, the coordinate attribute column, as well as optional columns for title, description, and popup fields. You can also provide callbacks to modify the query and map records to markers, and set a custom icon URL for the markers in this cluster.
     * @param string $model The Eloquent model class that will be used to fetch data for the markers in this cluster. The model should have a coordinate attribute that returns a Coordinate instance.
     * @param string|null $coordsColumn The name of the attribute in the model that contains the coordinates. Default is null.
     * @param string|null $titleColumn The name of the column in the model that contains the title for the marker popups. Default is null.
     * @param string|null $descriptionColumn The name of the column in the model that contains the description for the marker popups. Default is null.
     * @param array|null $popupFieldsColumns An array of column names in the model that should be included as fields in the marker popups. Default is null.
     * @param null|string|Closure|array $color The color to be used for the markers in this cluster. This can be a string representing a color (e.g., 'red', '#ff0000') or an instance of the Color enum. Default is null.
     * @param bool|null $syncRecords Whether to sync changes back to the records when the markers are edited on the map. If true, any changes made to the markers on the map (such as dragging to a new location) will be saved back to the corresponding records in the database. Default is null.
     * @param string|null $iconUrl The URL of the icon to be used for each marker in this cluster. Default is null.
     * @param Closure|null $mapRecordCallback A callback to map each Eloquent record to a Marker instance. The callback should accept an instance of Illuminate\Database\Eloquent\Model and return a Marker instance. This allows you to customize how each record is transformed into a marker, including setting custom properties or using different columns for the marker's attributes. Default is null.
     * @param Closure|null $modifyQueryCallback A callback to modify the Eloquent query used to fetch records for the markers. The callback should accept an instance of Illuminate\Database\Eloquent\Builder and return the modified query builder. This allows you to apply additional constraints, eager loading, or any other query modifications before the records are fetched and transformed into markers. Default is null.
     * @return static
     */
    public static function fromModel(
        string $model,
        ?string $coordsColumn = null,
        ?string $titleColumn = null,
        ?string $descriptionColumn = null,
        ?array $popupFieldsColumns = null,
        null|string|Closure|array $color = null,
        ?bool $syncRecords = null,
        ?string $iconUrl = null,
        ?Closure $mapRecordCallback = null,
        ?Closure $modifyQueryCallback = null
    ): static {
        $instance = new static;

        $instance->model = $model;
        $instance->coordsColumn = $coordsColumn;
        $instance->titleColumn = $titleColumn;
        $instance->descriptionColumn = $descriptionColumn;
        $instance->popupFieldsColumns = $popupFieldsColumns;
        $instance->color($color);
        $instance->iconUrl($iconUrl);
        $instance->syncRecords = $syncRecords;
        $instance->mapRecordUsing($mapRecordCallback);
        $instance->modifyQueryUsing($modifyQueryCallback);

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

    /**
     * Add a marker to the cluster. You can pass either a Marker instance or an array of marker properties that will be used to create a new Marker instance. If you pass an array, it should contain the necessary properties to create a Marker, such as 'lat', 'lng', 'title', etc.
     * @param Marker|array $marker A Marker instance or an array of marker properties to be added to the cluster. If an array is provided, it should contain the necessary properties to create a Marker, such as 'lat', 'lng', 'title', etc.
     * @return $this
     */
    public function marker(Marker|array $marker): static
    {
        $this->layers[] = $marker;
        return $this;
    }

    /**
     * Add multiple markers to the cluster at once. You can pass an array of Marker instances or an array of arrays, where each inner array contains the properties for a marker that will be used to create a new Marker instance. If you pass an array of arrays, each inner array should contain the necessary properties to create a Marker, such as 'lat', 'lng', 'title', etc.
     * @param array<Marker>|array<array> $markers An array of Marker instances or an array of arrays, where each inner array contains the properties for a marker that will be used to create a new Marker instance. If you pass an array of arrays, each inner array should contain the necessary properties to create a Marker, such as 'lat', 'lng', 'title', etc.
     * @return $this
     */
    public function markers(array $markers): static
    {
        foreach ($markers as $marker) {
            $this->marker($marker);
        }
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
                record: $record,
                coordsColumn: $this->coordsColumn,
                titleColumn: $this->titleColumn,
                descriptionColumn: $this->descriptionColumn,
                popupFieldsColumns: $this->popupFieldsColumns,
                color: $this->color,
                syncRecord: $this->syncRecords,
                iconUrl: $this->iconUrl,
                mapRecordCallback: $this->mapRecordCallback
            )
        )->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Configurações do Cluster
    |--------------------------------------------------------------------------
    */

    /**
     * Set the maximum radius that a cluster will cover from the central marker (in pixels). The default is 80. You can use this to make the clustering more or less aggressive. A smaller value will result in more clusters, while a larger value will result in fewer clusters.
     * @param int $radius The maximum radius that a cluster will cover from the central marker (in pixels). The default is 80. You can use this to make the clustering more or less aggressive. A smaller value will result in more clusters, while a larger value will result in fewer clusters.
     * @return $this
     */
    public function maxClusterRadius(int $radius): static
    {
        $this->maxClusterRadius = $radius;
        return $this;
    }

    /**
     * Set whether to show the coverage area of a cluster when hovering over it.
     * @param bool $show Whether to show the coverage area of a cluster when hovering over it.
     * @return $this
     */
    public function showCoverageOnHover(bool $show = true): static
    {
        $this->showCoverageOnHover = $show;
        return $this;
    }

    /**
     * Set whether to zoom to the bounds of a cluster when clicking on it.
     * @param bool $zoom Whether to zoom to the bounds of a cluster when clicking on it.
     * @return $this
     */
    public function zoomToBoundsOnClick(bool $zoom = true): static
    {
        $this->zoomToBoundsOnClick = $zoom;
        return $this;
    }

    /**
     * Set whether to spiderfy the cluster markers when the cluster is at its maximum zoom level and contains more than one marker. Spiderfying means that the markers will be spread out in a spider-like pattern around the cluster center, allowing the user to see and interact with each individual marker. This can be useful when you have many markers in a cluster and want to provide a way for users to access them at maximum zoom.
     * @param bool $spiderfy Whether to spiderfy the cluster markers when the cluster is at its maximum zoom level and contains more than one marker. Spiderfying means that the markers will be spread out in a spider-like pattern around the cluster center, allowing the user to see and interact with each individual marker. This can be useful when you have many markers in a cluster and want to provide a way for users to access them at maximum zoom.
     * @return $this
     */
    public function spiderfyOnMaxZoom(bool $spiderfy = true): static
    {
        $this->spiderfyOnMaxZoom = $spiderfy;
        return $this;
    }

    /**
     * Set whether to remove markers that are outside the visible bounds of the map.
     * @param bool $remove Whether to remove markers that are outside the visible bounds of the map.
     * @return $this
     */
    public function removeOutsideVisibleBounds(bool $remove = true): static
    {
        $this->removeOutsideVisibleBounds = $remove;
        return $this;
    }

    /**
     * Disable clustering at a specific zoom level.
     * @param int $zoomLevel The zoom level at which to disable clustering.
     * @return $this
     */
    public function disableClusteringAtZoom(int $zoomLevel): static
    {
        $this->disableClusteringAtZoom = $zoomLevel;
        return $this;
    }

    /**
     * Set the animation duration for the marker cluster.
     * @param int $animate The animation duration in milliseconds.
     * @return $this
     */
    public function animate(int $animate): static
    {
        $this->animate = $animate;
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Vínculo com Model
    |--------------------------------------------------------------------------
    */

    /**
     * Set the Eloquent model class that will be used to fetch data for the markers in this cluster. The model should have columns for latitude and longitude (or a JSON column with coordinates) that will be used to create the markers. You can also specify additional columns for title, description, and popup fields. Optionally, you can provide callbacks to modify the query and map records to markers.
     * @param string $model The Eloquent model class that will be used to fetch data for the markers in this cluster. The model should have columns for latitude and longitude (or a JSON column with coordinates) that will be used to create the markers. You can also specify additional columns for title, description, and popup fields. Optionally, you can provide callbacks to modify the query and map records to markers.
     * @return $this
     */
    public function model(string $model): static
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Set a callback to modify the Eloquent query used to fetch records for the markers. The callback should accept an instance of Illuminate\Database\Eloquent\Builder and return the modified query builder. This allows you to apply additional constraints, eager loading, or any other query modifications before the records are fetched and transformed into markers.
     * @param Closure|null $callback A callback to modify the Eloquent query used to fetch records for the markers. The callback should accept an instance of Illuminate\Database\Eloquent\Builder and return the modified query builder. This allows you to apply additional constraints, eager loading, or any other query modifications before the records are fetched and transformed into markers. If null is provided, any existing query modification callback will be removed.
     * @return $this
     */
    public function modifyQueryUsing(?Closure $callback): static
    {
        $this->modifyQueryCallback = $callback;
        return $this;
    }

    /**
     * Set a callback to map each Eloquent record to a Marker instance. The callback should accept an instance of Illuminate\Database\Eloquent\Model and return a Marker instance. This allows you to customize how each record is transformed into a marker, including setting custom properties or using different columns for the marker's attributes.
     * @param Closure|null $callback A callback to map each Eloquent record to a Marker instance. The callback should accept an instance of Illuminate\Database\Eloquent\Model and return a Marker instance. This allows you to customize how each record is transformed into a marker, including setting custom properties or using different columns for the marker's attributes. If null is provided, any existing record mapping callback will be removed.
     * @return $this
     */
    public function mapRecordUsing(?Closure $callback): static
    {
        $this->mapRecordCallback = $callback;
        return $this;
    }

    /**
     * Set the URL for the icon to be used for each marker in this cluster.
     * @param string $url The URL of the icon to be used for each marker in this cluster.
     * @return $this
     */
    public function iconUrl(?string $url): static
    {
        $this->iconUrl = $url;
        return $this;
    }

    protected function getLayerGroupOptions(): array
    {
        return array_filter([
            'maxClusterRadius' => $this->maxClusterRadius,
            'showCoverageOnHover' => $this->showCoverageOnHover,
            'zoomToBoundsOnClick' => $this->zoomToBoundsOnClick,
            'spiderfyOnMaxZoom' => $this->spiderfyOnMaxZoom,
            'removeOutsideVisibleBounds' => $this->removeOutsideVisibleBounds,
            'disableClusteringAtZoom' => $this->disableClusteringAtZoom,
            'animate' => $this->animate,
        ]);
    }
}
