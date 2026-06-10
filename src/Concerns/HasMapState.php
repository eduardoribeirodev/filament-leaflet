<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Concerns;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\Enums\GeoSearchProvider;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\Coordinate;
use EduardoRibeiroDev\FilamentLeaflet\Enums\TileLayer;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Marker;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Shapes\Shape;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;

trait HasMapState
{
    use HasMapConfig {
        getGeoJsonTooltip as getParentGeoJsonTooltip;
        getGeoJsonUrl as getParentGeoJsonUrl;
        getMapData as getParentMapData;
        getMapCenter as getParentMapCenter;
    }

    protected array|Closure $geoJsonData = [];
    protected string|Closure|null $geoJsonTooltip = null;
    protected array|Closure $markers = [];
    protected array|Closure $shapes = [];

    protected Marker|Closure|null $pickMarker = null;
    protected Marker|Closure|null $defaultPickMarker = null;
    protected ?Closure $onMapClickCallback = null;
    protected ?Closure $onLayerClickCallback = null;



    /**
     * Set the center of the map. The center can be defined using either a single parameter that is an array of [latitude, longitude] or by providing latitude and longitude as separate parameters. The method will store the provided parameters and evaluate them later when the map center is requested.
     * @param float|array|Closure $latitudeOrCoordinates The latitude for the map's center or an array containing both latitude and longitude. This can also be a Closure that returns either a float (latitude) or an array of [latitude, longitude].
     * @param float|Closure|null $longitude The longitude for the map's center. This parameter is required if the first parameter is a float representing latitude. It can also be a Closure that returns a float (longitude). If the first parameter is an array of coordinates, this parameter should be null.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     * @throws \InvalidArgumentException If the first parameter is a float and the second parameter (longitude) is not provided.
     */
    public function center(float|array|Closure $latitudeOrCoordinates, null|float|Closure $longitude = null): static
    {
        if ($latitudeOrCoordinates instanceof Closure && $longitude === null) {
            $this->mapCenter = $latitudeOrCoordinates;

            return $this;
        }

        if (is_array($latitudeOrCoordinates)) {
            $this->mapCenter = $latitudeOrCoordinates;

            return $this;
        }

        if ($longitude === null) {
            throw new \InvalidArgumentException('Longitude must be provided when using latitude and longitude as separate parameters.');
        }

        $this->mapCenter = $longitude instanceof Closure
            ? fn() => [
                $this->evaluate($latitudeOrCoordinates),
                $this->evaluate($longitude),
            ]
            : [
                $latitudeOrCoordinates,
                $longitude,
            ];

        return $this;
    }

    /**
     * Set whether the map should automatically center on the user's position. The $autoCenter parameter is a boolean value or a Closure that returns a boolean. When set to true, the map will attempt to center on the user's current location using the browser's geolocation API. This feature is useful for applications that want to provide a personalized map experience based on the user's location. If set to false, the map will not automatically center on the user's position and will use the default center defined by the center() method or the initial configuration.
     * @param bool|Closure $autoCenter A boolean value or a Closure that returns a boolean indicating whether the map should automatically center on the user's position. If true, the map will attempt to center on the user's current location. If false, the map will not auto-center and will use the default center.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function autoCenter(bool|Closure $autoCenter = true): static
    {
        $this->autoCenter = $autoCenter;

        return $this;
    }

    /**
     * Set whether the map should automatically zoom to fit all markers and shapes. The $fitBounds parameter is a boolean value or a Closure that returns a boolean. When set to true, the map will automatically adjust its zoom level and center to display all visible layers (markers, shapes, etc.) within the bounds. This is useful for ensuring all important elements are visible on the map without manual zoom adjustments. If set to false, the map will use the default zoom and center.
     * @param bool|Closure $fitBounds A boolean value or a Closure that returns a boolean indicating whether the map should automatically fit all markers and shapes. If true, the map will zoom to fit all layers. If false, it will use default zoom settings.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function fitBounds(bool|Closure $fitBounds = true): static
    {
        $this->fitBounds = $fitBounds;

        return $this;
    }

    /**
     * Set the height of the map. The $height parameter is an integer value or a Closure that returns an integer representing the height of the map in pixels. This method allows you to define the height of the map container, which is useful for responsive design or when integrating with other UI components.
     * @param int|Closure $height The height of the map in pixels. This can be an integer value or a Closure that returns an integer.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function height(int|Closure $height): static
    {
        $this->mapHeight = $height;

        return $this;
    }

    /**
     * Set whether the map should be draggable. The $draggable parameter is a boolean value or a Closure that returns a boolean. When set to true, users will be able to click and drag the map to navigate around. If set to false, the map will be static and users will not be able to move it by dragging. This method provides a way to control the interactivity of the map based on the needs of your application.
     * @param bool|Closure $draggable A boolean value or a Closure that returns a boolean indicating whether the map should be draggable. If true, users can click and drag the map to navigate. If false, the map will be static and not draggable.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function mapDraggable(bool|Closure $draggable = true): static
    {
        $this->mapDraggable = $draggable;

        return $this;
    }

    /**
     * Set whether the map should be zoomable. The $zoomable parameter is a boolean value or a Closure that returns a boolean. When set to true, users will be able to zoom in and out of the map using mouse scroll, pinch gestures on touch devices, or zoom controls if enabled. If set to false, the map will not respond to zoom interactions, effectively keeping it at a fixed zoom level. This method allows you to control the zoom functionality of the map based on the requirements of your application.
     * @param bool|Closure $zoomable A boolean value or a Closure that returns a boolean indicating whether the map should be zoomable. If true, users can zoom in and out of the map using mouse scroll, pinch gestures, or zoom controls. If false, the map will not respond to zoom interactions.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function mapZoomable(bool|Closure $zoomable = true): static
    {
        $this->mapZoomable = $zoomable;

        return $this;
    }

    /**
     * Convenience method to turn the map into a static map. When the $isStatic parameter is set to true, the map will be configured to be non-draggable and non-zoomable, effectively making it a static map. This method provides a simple way to quickly set the map to a static state without having to call the individual methods for draggable and zoomable settings.
     * @param bool|Closure $isStatic A boolean value or a Closure that returns a boolean indicating whether the map should be static. If true, the map will be set to non-draggable and non-zoomable. If false, the map will retain its current draggable and zoomable settings.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function static(bool|Closure $isStatic = true): static
    {
        if ($isStatic instanceof Closure) {
            $this->mapDraggable = fn() => ! $this->evaluate($isStatic);
            $this->mapZoomable = fn() => ! $this->evaluate($isStatic);

            return $this;
        }

        $this->mapDraggable = ! $isStatic;
        $this->mapZoomable = ! $isStatic;

        return $this;
    }

    /**
     * Set the map recenter timeout. The $milliseconds parameter is an integer value or a Closure that returns an integer representing the amount of time in milliseconds to wait before recentering the map after a user interaction. This method allows you to control the delay before the map automatically recenters itself, which can be useful for improving user experience by preventing immediate recentering during active interactions.
     * @param int|Closure|null $milliseconds The amount of time in milliseconds to wait before recentering the map after a user interaction. This can be an integer value, a Closure that returns an integer, or null to remove any existing timeout setting.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function recenterTimeout(null|int|Closure $milliseconds): static
    {
        $this->recenterMapTimeout = $milliseconds;

        return $this;
    }

    /**
     * Set the default zoom level for the map. The $zoomLevel parameter is an integer value or a Closure that returns an integer representing the default zoom level of the map. This method allows you to define the initial zoom level when the map is first loaded, providing a way to control how much of the map is visible to users by default.
     * @param int|Closure $zoomLevel The default zoom level for the map. This can be an integer value or a Closure that returns an integer.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function zoom(int|Closure $zoomLevel): static
    {
        $this->defaultZoom = $zoomLevel;

        return $this;
    }

    /**
     * Set whether the map has a attribution control. The $enabled parameter is a boolean value or a Closure that returns a boolean indicating whether the attribution control should be displayed on the map. When set to true, the attribution control will be visible, allowing users to see the source of the map data. If set to false, the attribution control will be hidden, which can be useful for cleaner map designs or when the attribution is not necessary to display.
     * @param bool|Closure $enabled A boolean value or a Closure that returns a boolean indicating whether the attribution control should be displayed on the map. If true, the attribution control will be visible. If false, it will be hidden.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function attributionControl(bool|Closure $enabled = true): static
    {
        $this->hasAttributionControl = $enabled;

        return $this;
    }

    /**
     * Set whether the map has a fullscreen control. The $enabled parameter is a boolean value or a Closure that returns a boolean indicating whether the fullscreen control should be displayed on the map. When set to true, the fullscreen control will be visible, allowing users to toggle the map into fullscreen mode for an immersive experience. If set to false, the fullscreen control will be hidden, which can be useful for simpler map interfaces or when fullscreen functionality is not desired.
     * @param bool|Closure $enabled A boolean value or a Closure that returns a boolean indicating whether the fullscreen control should be displayed on the map. If true, the fullscreen control will be visible. If false, it will be hidden.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function fullscreenControl(bool|Closure $enabled = true): static
    {
        $this->hasFullscreenControl = $enabled;

        return $this;
    }

    /**
     * Set whether the map has a search address control. The $enabled parameter is a boolean value or a Closure that returns a boolean indicating whether the search address control should be displayed on the map. When set to true, the search address control will be visible, allowing users to search for locations by address or place name. If set to false, the search address control will be hidden, which can be useful for simpler map interfaces or when search functionality is not desired.
     * @param bool|Closure $enabled A boolean value or a Closure that returns a boolean indicating whether the search address control should be displayed on the map. If true, the search address control will be visible. If false, it will be hidden.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function geoSearchControl(bool|Closure $enabled = true): static
    {
        $this->hasGeoSearchControl = $enabled;

        return $this;
    }

    /**
     * Set the geocoding provider to use for search queries. The $provider parameter can be an instance of the GeoSearchProvider enum, a string representing the provider, or a Closure that returns either of those. This method allows you to specify which geocoding service should be used for processing search queries in the search control on the map. By providing a valid provider, you enable the search functionality to return location results based on user input.
     * @param GeoSearchProvider|string|Closure $provider The geocoding provider to use for search queries. This can be an instance of the GeoSearchProvider enum, a string representing the provider, or a Closure that returns either of those.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function geoSearchProvider(GeoSearchProvider|string|Closure $provider): static
    {
        $this->geoSearchProvider = $provider;

        return $this;
    }

    /**
     * Set the API key for the geocoding provider used in the search control. The $apiKey parameter
     * is a string value or a Closure that returns a string representing the API key required by the geocoding provider for performing search queries. This method allows you to provide the necessary credentials for geocoding services, enabling the search functionality on the map when using providers that require an API key.
     * @param string|Closure $apiKey The API key for the geocoding provider. This can be a string value or a Closure that returns a string.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function geoSearchApiKey(string|Closure $apiKey): static
    {
        $this->geoSearchApiKey = $apiKey;

        return $this;
    }

    /**
     * Set whether the map has a scale control. The $enabled parameter is a boolean value or a Closure that returns a boolean indicating whether the scale control should be displayed on the map. When set to true, the scale control will be visible, providing users with a visual representation of distances on the map. If set to false, the scale control will be hidden, which can be useful for cleaner map designs or when distance measurement is not necessary to display.
     * @param bool|Closure $enabled A boolean value or a Closure that returns a boolean indicating whether the scale control should be displayed on the map. If true, the scale control will be visible. If false, it will be hidden.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function scaleControl(bool|Closure $enabled = true): static
    {
        $this->hasScaleControl = $enabled;

        return $this;
    }

    /**
     * Set whether the map has a zoom control. The $enabled parameter is a boolean value or a Closure that returns a boolean indicating whether the zoom control should be displayed on the map. When set to true, the zoom control will be visible, allowing users to easily zoom in and out of the map using the provided buttons. If set to false, the zoom control will be hidden, which can be useful for cleaner map designs or when zoom functionality is not desired.
     * @param bool|Closure $enabled A boolean value or a Closure that returns a boolean indicating whether the zoom control should be displayed on the map. If true, the zoom control will be visible. If false, it will be hidden.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function zoomControl(bool|Closure $enabled = true): static
    {
        $this->hasZoomControl = $enabled;

        return $this;
    }

    /**
     * Set whether the map has a draw marker control. The $enabled parameter is a boolean value or a Closure that returns a boolean indicating whether the draw marker control should be displayed on the map. When set to true, the draw marker control will be visible, allowing users to add markers to the map by clicking on it. If set to false, the draw marker control will be hidden, which can be useful for cleaner map designs or when drawing functionality is not desired.
     * @param bool|Closure $enabled A boolean value or a Closure that returns a boolean indicating whether the draw marker control should be displayed on the map. If true, the draw marker control will be visible. If false, it will be hidden.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function drawMarkerControl(bool|Closure $enabled = true): static
    {
        $this->hasDrawMarkerControl = $enabled;

        return $this;
    }

    /**
     * Set whether the map has a draw circle marker control. The $enabled parameter is a boolean value or a Closure that returns a boolean indicating whether the draw circle marker control should be displayed on the map. When set to true, the draw circle marker control will be visible, allowing users to add circle markers to the map by clicking on it. If set to false, the draw circle marker control will be hidden, which can be useful for cleaner map designs or when drawing functionality is not desired.
     * @param bool|Closure $enabled A boolean value or a Closure that returns a boolean indicating whether the draw circle marker control should be displayed on the map. If true, the draw circle marker control will be visible. If false, it will be hidden.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function drawCircleMarkerControl(bool|Closure $enabled = true): static
    {
        $this->hasDrawCircleMarkerControl = $enabled;

        return $this;
    }

    /**
     * Set whether the map has a draw circle control. The $enabled parameter is a boolean value or a Closure that returns a boolean indicating whether the draw circle control should be displayed on the map. When set to true, the draw circle control will be visible, allowing users to add circles to the map by clicking on it. If set to false, the draw circle control will be hidden, which can be useful for cleaner map designs or when drawing functionality is not desired.
     * @param bool|Closure $enabled A boolean value or a Closure that returns a boolean indicating whether the draw circle control should be displayed on the map. If true, the draw circle control will be visible. If false, it will be hidden.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function drawCircleControl(bool|Closure $enabled = true): static
    {
        $this->hasDrawCircleControl = $enabled;

        return $this;
    }

    /**
     * Set whether the map has a draw polyline control. The $enabled parameter is a boolean value or a Closure that returns a boolean indicating whether the draw polyline control should be displayed on the map. When set to true, the draw polyline control will be visible, allowing users to add polylines to the map by clicking on it. If set to false, the draw polyline control will be hidden, which can be useful for cleaner map designs or when drawing functionality is not desired.
     * @param bool|Closure $enabled A boolean value or a Closure that returns a boolean indicating whether the draw polyline control should be displayed on the map. If true, the draw polyline control will be visible. If false, it will be hidden.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function drawPolylineControl(bool|Closure $enabled = true): static
    {
        $this->hasDrawPolylineControl = $enabled;

        return $this;
    }

    /**
     * Set whether the map has a draw rectangle control. The $enabled parameter is a boolean value or a Closure that returns a boolean indicating whether the draw rectangle control should be displayed on the map. When set to true, the draw rectangle control will be visible, allowing users to add rectangles to the map by clicking on it. If set to false, the draw rectangle control will be hidden, which can be useful for cleaner map designs or when drawing functionality is not desired.
     * @param bool|Closure $enabled A boolean value or a Closure that returns a boolean indicating whether the draw rectangle control should be displayed on the map. If true, the draw rectangle control will be visible. If false, it will be hidden.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function drawRectangleControl(bool|Closure $enabled = true): static
    {
        $this->hasDrawRectangleControl = $enabled;

        return $this;
    }

    /**
     * Set whether the map has a draw polygon control. The $enabled parameter is a boolean value or a Closure that returns a boolean indicating whether the draw polygon control should be displayed on the map. When set to true, the draw polygon control will be visible, allowing users to add polygons to the map by clicking on it. If set to false, the draw polygon control will be hidden, which can be useful for cleaner map designs or when drawing functionality is not desired.
     * @param bool|Closure $enabled A boolean value or a Closure that returns a boolean indicating whether the draw polygon control should be displayed on the map. If true, the draw polygon control will be visible. If false, it will be hidden.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function drawPolygonControl(bool|Closure $enabled = true): static
    {
        $this->hasDrawPolygonControl = $enabled;

        return $this;
    }

    /**
     * Set whether the map has a draw text control. The $enabled parameter is a boolean value or a Closure that returns a boolean indicating whether the draw text control should be displayed on the map. When set to true, the draw text control will be visible, allowing users to add text to the map by clicking on it. If set to false, the draw text control will be hidden, which can be useful for cleaner map designs or when drawing functionality is not desired.
     * @param bool|Closure $enabled A boolean value or a Closure that returns a boolean indicating whether the draw text control should be displayed on the map. If true, the draw text control will be visible. If false, it will be hidden.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function drawTextControl(bool|Closure $enabled = true): static
    {
        $this->hasDrawTextControl = $enabled;

        return $this;
    }

    /**
     * Set whether the map has an edit layers control. The $enabled parameter is a boolean value or a Closure that returns a boolean indicating whether the edit layers control should be displayed on the map. When set to true, the edit layers control will be visible, allowing users to edit layer properties. If set to false, the edit layers control will be hidden.
     * @param bool|Closure $enabled A boolean value or a Closure that returns a boolean indicating whether the edit layers control should be displayed on the map. If true, the edit layers control will be visible. If false, it will be hidden.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function editLayersControl(bool|Closure $enabled = true): static
    {
        $this->hasEditLayersControl = $enabled;

        return $this;
    }

    /**
     * Set whether the map has a drag layers control. The $enabled parameter is a boolean value or a Closure that returns a boolean indicating whether the drag layers control should be displayed on the map. When set to true, the drag layers control will be visible, allowing users to drag and move layers on the map. If set to false, the drag layers control will be hidden.
     * @param bool|Closure $enabled A boolean value or a Closure that returns a boolean indicating whether the drag layers control should be displayed on the map. If true, the drag layers control will be visible. If false, it will be hidden.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function dragLayersControl(bool|Closure $enabled = true): static
    {
        $this->hasDragLayersControl = $enabled;

        return $this;
    }

    /**
     * Set whether the map has a remove layers control. The $enabled parameter is a boolean value or a Closure that returns a boolean indicating whether the remove layers control should be displayed on the map. When set to true, the remove layers control will be visible, allowing users to remove layers from the map. If set to false, the remove layers control will be hidden.
     * @param bool|Closure $enabled A boolean value or a Closure that returns a boolean indicating whether the remove layers control should be displayed on the map. If true, the remove layers control will be visible. If false, it will be hidden.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function removeLayersControl(bool|Closure $enabled = true): static
    {
        $this->hasRemoveLayersControl = $enabled;

        return $this;
    }

    /**
     * Set whether the map has a rotate layers control. The $enabled parameter is a boolean value or a Closure that returns a boolean indicating whether the rotate layers control should be displayed on the map. When set to true, the rotate layers control will be visible, allowing users to rotate layers on the map. If set to false, the rotate layers control will be hidden.
     * @param bool|Closure $enabled A boolean value or a Closure that returns a boolean indicating whether the rotate layers control should be displayed on the map. If true, the rotate layers control will be visible. If false, it will be hidden.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function rotateLayersControl(bool|Closure $enabled = true): static
    {
        $this->hasRotateLayersControl = $enabled;

        return $this;
    }

    /**
     * Set whether the map has a cut polygon control. The $enabled parameter is a boolean value or a Closure that returns a boolean indicating whether the cut polygon control should be displayed on the map. When set to true, the cut polygon control will be visible, allowing users to cut polygons on the map. If set to false, the cut polygon control will be hidden.
     * @param bool|Closure $enabled A boolean value or a Closure that returns a boolean indicating whether the cut polygon control should be displayed on the map. If true, the cut polygon control will be visible. If false, it will be hidden.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function cutPolygonControl(bool|Closure $enabled = true): static
    {
        $this->hasCutPolygonControl = $enabled;

        return $this;
    }

    /**
     * Set the tile layer URLs for the map. The $urls parameter can be a single TileLayer enum value, a string URL, an array of URLs, or a Closure that returns any of these types. This method allows you to specify the tile layers that will be used to render the map, providing flexibility in choosing different map styles and sources.
     * @param TileLayer|Closure|string|array $urls A single TileLayer enum value, a string URL, an array of URLs, or a Closure that returns any of these types. This parameter specifies the tile layers for the map.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function tileLayersUrl(TileLayer|Closure|string|array $urls): static
    {
        $this->tileLayersUrl = $urls;

        return $this;
    }

    /**
     * Set the minimum zoom level for the map. The $minZoom parameter is an integer value or a Closure that returns an integer indicating the minimum zoom level allowed on the map. This method allows you to control the minimum zoom level that users can zoom out to on the map.
     * @param int|Closure $minZoom An integer value or a Closure that returns an integer indicating the minimum zoom level allowed on the map.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function minZoom(int|Closure $minZoom): static
    {
        $this->minZoom = $minZoom;

        return $this;
    }

    /**
     * Set the maximum zoom level for the map. The $maxZoom parameter is an integer value or a Closure that returns an integer indicating the maximum zoom level allowed on the map. This method allows you to control the maximum zoom level that users can zoom in to on the map.
     * @param int|Closure $maxZoom An integer value or a Closure that returns an integer indicating the maximum zoom level allowed on the map.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function maxZoom(int|Closure $maxZoom): static
    {
        $this->maxZoom = $maxZoom;

        return $this;
    }

    /**
     * Set the GeoJSON URL for the map. The $url parameter can be a string URL or a Closure that returns a string URL pointing to a GeoJSON file. This method allows you to specify the source of GeoJSON data that will be used to render features on the map, such as markers, shapes, and tooltips.
     * @param string|Closure $url A string URL or a Closure that returns a string URL pointing to a GeoJSON file.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function geoJsonUrl(string|Closure $url): static
    {
        $this->geoJsonUrl = $url;

        return $this;
    }

    /**
     * Set the GeoJSON data for the map. The $data parameter can be an array of GeoJSON features or a Closure that returns such an array. This method allows you to provide GeoJSON data directly, which will be used to render features on the map, such as markers, shapes, and tooltips. Using this method is recommended over geoJsonUrl() for better performance and flexibility.
     * @param array|Closure $data An array of GeoJSON features or a Closure that returns such an array.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function geoJsonData(array|Closure $data): static
    {
        $this->geoJsonData = $data;

        return $this;
    }

    /**
     * Set the GeoJSON colors for the map. The $colors parameter can be an array of color values or a Closure that returns such an array. This method allows you to define the colors for GeoJSON features on the map.
     * @param array|Closure $colors An array of color values or a Closure that returns such an array.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function geoJsonColors(array|Closure $colors): static
    {
        $this->geoJsonColors = $colors;

        return $this;
    }

    /**
     * Set the GeoJSON tooltip for the map. The $tooltip parameter can be a string or a Closure that returns a string. This method allows you to define the tooltip text for GeoJSON features on the map.
     * @param string|Closure|null $tooltip A string or a Closure that returns a string.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function geoJsonTooltip(string|Closure|null $tooltip): static
    {
        $this->geoJsonTooltip = $tooltip;

        return $this;
    }

    /**
     * Set the markers for the map. The $markers parameter can be an array of marker data or a Closure that returns such an array. This method allows you to define the markers that will be displayed on the map, providing a way to visualize specific locations or points of interest.
     * @param array<Marker>|Closure $markers An array of marker data or a Closure that returns such an array.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function markers(array|Closure $markers): static
    {
        $this->markers = $markers;

        return $this;
    }

    /**
     * Set the shapes for the map. The $shapes parameter can be an array of shape data or a Closure that returns such an array. This method allows you to define the shapes that will be displayed on the map, providing a way to visualize areas, boundaries, or other geometric features.
     * @param array<Shape>|Closure $shapes An array of shape data or a Closure that returns such an array.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function shapes(array|Closure $shapes): static
    {
        $this->shapes = $shapes;

        return $this;
    }

    /**
     * Set the marker to be picked on the map. The $marker parameter can be a Marker instance or a Closure that returns a Marker instance. This method allows you to define which marker should be picked on the map, providing a way to select and highlight specific markers.
     * @param Marker|Closure|null $marker A Marker instance or a Closure that returns a Marker instance.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function pickMarker(Marker|Closure|null $marker)
    {
        $this->pickMarker = $marker;

        return $this;
    }

    /**
     * Set the default marker to be picked on the map. The $marker parameter can be a Marker instance or a Closure that returns a Marker instance. This method allows you to define a default marker that will be picked on the map when no specific marker is selected, providing a fallback option for marker selection.
     * @param Marker|Closure|null $marker A Marker instance or a Closure that returns a Marker instance.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function defaultPickMarker(Marker|Closure|null $marker)
    {
        $this->defaultPickMarker = $marker;

        return $this;
    }

    /**
     * Set the callback to be executed when the map is clicked. The $callback parameter is a Closure that takes latitude and longitude coordinates as parameters. This method allows you to define a callback function that will be executed when the map is clicked, providing a way to handle map click events.
     * @param Closure|null $callback A Closure that takes latitude and longitude coordinates as parameters, or null to remove any existing callback.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function onMapClick(?Closure $callback): static
    {
        $this->onMapClickCallback = $callback;

        return $this;
    }

    /**
     * Set the callback to be executed when a map layer is clicked. The $callback parameter is a Closure that takes a layer object as a parameter. This method allows you to define a callback function that will be executed when a map layer is clicked, providing a way to handle layer click events.
     * @param Closure|null $callback A Closure that takes a layer object as a parameter, or null to remove any existing callback.
     * @return $this The current instance of the class using this trait, allowing for method chaining.
     */
    public function onLayerClick(?Closure $callback): static
    {
        $this->onLayerClickCallback = $callback;

        return $this;
    }

    /** ---------- GETTERS ---------- */

    function getPickMarkerData(): array
    {
        $pickMarker = $this->evaluate($this->defaultPickMarker) ?? new Marker;

        if ($this->isDisabled()) {
            $pickMarker->gray();
        }

        if ($this->pickMarker !== null) {
            $pickMarker = $this->evaluate($this->pickMarker, [
                'marker' => $pickMarker
            ]);
        }

        return $pickMarker->toArray();
    }

    protected function getMapCenter(): array
    {
        $state = $this->getState();

        if (!$state) {
            return $this->getParentMapCenter();
        }

        if (is_array($state)) {
            $state = Coordinate::fromArray($state);
        }

        return [
            'lat' => $state->lat + 0.5 ** ($this->getDefaultZoom() - 4),
            'lng' => $state->lng
        ];
    }

    protected function getMarkers(): array
    {
        return $this->evaluate($this->markers) ?? [];
    }

    protected function getShapes(): array
    {
        return $this->evaluate($this->shapes) ?? [];
    }

    protected function getGeoJsonTooltip(): string
    {
        if ($this->geoJsonTooltip !== null) {
            return $this->evaluate($this->geoJsonTooltip);
        }

        return $this->getParentGeoJsonTooltip();
    }

    protected function getGeoJsonUrl(): ?string
    {
        if ($this->geoJsonUrl !== null) {
            return $this->evaluate($this->geoJsonUrl);
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
            'coordinates' => new Coordinate($latitude, $longitude)
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

    private function getMapFieldData(): array
    {
        return [
            'pickMarker'         => $this->getPickMarkerData(),
            'state'              => $this->getState(),
            'name'               => $this->getName(),
            'disabled'           => $this->isDisabled(),
            'statePath'          => $this->getMethodIfExists('getStatePath'),
            'recordKey'          => $this->getMethodIfExists('getRecordKey'),
            'key'                => $this->getMethodIfExists('getKey'),
        ];
    }

    private function getMethodIfExists(string $method)
    {
        return method_exists($this, $method)
            ? $this->{$method}()
            : null;
    }

    public function getMapData(): array
    {
        return array_merge(
            $this->getParentMapData(),
            ['state' => $this->getMapFieldData()]
        );
    }
}
