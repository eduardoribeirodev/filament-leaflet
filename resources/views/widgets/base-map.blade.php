@php
    $config = $this->getWidgetData();
    $widgetId = $this->getId();
    $mapId = "mapa-{$widgetId}";
@endphp


<x-filament-widgets::widget>

    <x-filament::section>
        <x-slot name="heading">
            {{ $this->getHeading() }}
        </x-slot>

        <div id="{{ $mapId }}" wire:ignore></div>
    </x-filament::section>

    {{-- Obtém apenas o formulário da ação (modal) --}}
    {{ $this->createMarkerAction->getFormToSubmit() }}
    <x-filament-actions::modals />

    {{-- Estilos CSS --}}
    <style>
        #{{ $mapId }} {
            height: {{ $this->getMapHeight() }}px;
            width: 100%;
            z-index: 0;
            border-radius: 5px;
            overflow: hidden;
        }

        .leaflet-bottom.leaflet-right {
            display: none;
        }

        .info-{{ $widgetId }} {
            padding: 8px 10px;
            font: 14px/16px Arial, Helvetica, sans-serif;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
            border-radius: 5px;
            color: #333;
            border: 1px solid #ccc;
        }

        .custom-marker-popup-{{ $widgetId }} {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            max-width: 300px;
        }

        .custom-marker-popup-{{ $widgetId }} h4 {
            margin: 0 0 8px 0;
            color: #333;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
        }

        .custom-marker-popup-{{ $widgetId }} p {
            margin: 4px 0;
            color: #666;
            line-height: 1.4;
        }

        .custom-marker-popup-{{ $widgetId }} .field-label {
            font-weight: bold;
            color: #444;
        }

        .custom-marker-popup-{{ $widgetId }} a {
            color: #0066cc;
            text-decoration: none;
        }

        .custom-marker-popup-{{ $widgetId }} a:hover {
            text-decoration: underline;
        }

        {!! $this->getCustomStyles() !!}
    </style>

    {{-- Script principal --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const MapWidget{{ $widgetId }} = {
                config: @json($config),
                widgetId: '{{ $widgetId }}',
                mapId: '{{ $mapId }}',
                map: null,
                markerLayers: [],
                markerGroups: {},
                geoJsonLayer: null,
                info: null,

                init() {
                    this.createMap();
                    this.addTileLayer();

                    if (this.config.geoJsonData.length) {
                        this.setupInfoControl();
                        this.loadGeoJson();
                    }

                    if (this.config.markers.length) {
                        this.addMarkers();
                    }

                    this.setupEventHandlers();
                    this.setupLivewireListeners();
                    this.map.invalidateSize();
                    {!! $this->afterMapInit() !!}
                },

                createMap() {
                    this.map = L.map(this.mapId, this.config.mapConfig || {})
                        .setView(this.config.defaultCoord, this.config.defaultZoom);
                },

                addTileLayer() {
                    L.tileLayer(this.config.tileLayerUrl, {
                        maxZoom: this.config.zoomConfig.max,
                        minZoom: this.config.zoomConfig.min,
                        attribution: this.config.attribution || ''
                    }).addTo(this.map);
                },

                setupInfoControl() {
                    this.info = L.control();
                    this.info.onAdd = () => {
                        const div = L.DomUtil.create('div', `info-${this.widgetId}`);
                        this.info._div = div;
                        div.style.display = 'none';
                        return div;
                    };

                    this.info.update = (props) => {
                        if (!this.info._div) return;

                        if (props) {
                            this.info._div.style.display = 'block';
                            let text = this.config.infoText
                                .replace('{state}', props.name)
                                .replace('{density}', props.density);
                            this.info._div.innerHTML = text;
                        } else {
                            this.info._div.style.display = 'none';
                        }
                    };

                    this.info.addTo(this.map);
                },

                async loadGeoJson() {
                    if (!this.config.geoJsonUrl) return;

                    try {
                        const response = await fetch(this.config.geoJsonUrl);
                        const data = await response.json();

                        const features = Object.entries(this.config.geoJsonData)
                            .filter(([estado]) => data[estado])
                            .map(([estado, densidade]) => ({
                                type: "Feature",
                                id: estado,
                                properties: {
                                    name: data[estado].name,
                                    density: densidade
                                },
                                geometry: {
                                    type: "Polygon",
                                    coordinates: data[estado].coordinates
                                }
                            }));

                        this.geoJsonLayer = L.geoJson({
                            type: 'FeatureCollection',
                            features
                        }, {
                            style: (feature) => this.getFeatureStyle(feature),
                            onEachFeature: (feature, layer) => {
                                layer.on({
                                    mouseover: (e) => this.info?.update(e.target.feature
                                        .properties),
                                    mouseout: () => this.info?.update(),
                                    click: (e) => this.map.fitBounds(e.target
                                    .getBounds())
                                });
                            }
                        }).addTo(this.map);
                    } catch (error) {
                        console.error('Erro ao carregar GeoJSON:', error);
                    }
                },

                getFeatureStyle(feature) {
                    const values = Object.values(this.config.geoJsonData);
                    const percentage = feature.properties.density / Math.max(values);
                    const index = Math.max(0, Math.ceil(percentage * this.config.geoJsonColors.length) - 1);

                    return {
                        fillColor: this.config.geoJsonColors[index],
                        weight: 2,
                        opacity: 1,
                        color: 'white',
                        dashArray: '3',
                        fillOpacity: 0.8
                    };
                },

                addMarkers() {
                    if (!this.config.markers?.length) return;

                    this.config.markers.forEach(data => {
                        if (!data.lat || !data.lng) {
                            console.warn('Marker sem coordenadas:', data);
                            return;
                        }

                        const marker = L.marker([data.lat, data.lng], {
                            icon: this.createIcon(data),
                            title: data.title || '',
                            draggable: data.draggable || false
                        });

                        if (data.title || data.popupContent) {
                            marker.bindPopup(this.createPopupContent(data), data.popupOptions);
                        }

                        if (data.tooltip) {
                            marker.bindTooltip(data.tooltip.content || data.tooltip, {
                                permanent: data.tooltip.permanent || false,
                                direction: data.tooltip.direction || 'auto',
                                ...(data.tooltip.options || {})
                            });
                        }

                        marker.on('click', () => @this.onMarkerClick(data.id));

                        if (data.group) {
                            this.markerGroups[data.group] = this.markerGroups[data.group] || L
                                .layerGroup();
                            this.markerGroups[data.group].addLayer(marker);
                        } else {
                            marker.addTo(this.map);
                        }

                        this.markerLayers.push({
                            marker,
                            data
                        });
                    });

                    Object.values(this.markerGroups).forEach(group => group.addTo(this.map));
                },

                createIcon(marker) {
                    const options = {
                        iconSize: marker.iconSize || [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    };

                    if (marker.icon) {
                        options.iconUrl = marker.icon;
                    } else {
                        const path = '/vendor/filament-leaflet/images';
                        options.iconUrl = `${path}/marker-icon-2x-${marker.color}.png`;
                        options.shadowUrl = `${path}/marker-shadow.png`;
                    }

                    return L.icon(options);
                },

                createPopupContent(marker) {
                    let html = `<div class="custom-marker-popup-${this.widgetId}">`;

                    if (marker.title) html += `<h4>${marker.title}</h4>`;
                    if (marker.popupContent) html += marker.popupContent;

                    if (marker.popupData) {
                        Object.entries(marker.popupData).forEach(([key, value]) => {
                            html += `<p><span class="field-label">${key}:</span> ${value}</p>`;
                        });
                    }

                    return html + '</div>';
                },

                setupEventHandlers() {
                    this.map.on('click', (e) => {
                        const coords = e.latlng.toString().match(/-?\d+\.\d+/g);
                        @this.onMapClick(...coords);
                    });
                },

                setupLivewireListeners() {
                    window.addEventListener('update-leaflet-{{ $widgetId }}', (event) => {
                        this.updateMapData(event.detail.config);
                    });
                },

                updateMapData(newConfig) {
                    this.config = newConfig;
                    this.clearAllMarkers();

                    if (this.config.markers.length) this.addMarkers();

                    if (this.config.geoJsonData.length) {
                        if (this.geoJsonLayer) this.map.removeLayer(this.geoJsonLayer);
                        this.loadGeoJson();
                    }
                },

                clearAllMarkers() {
                    this.markerLayers.forEach(({
                        marker
                    }) => this.map.removeLayer(marker));
                    this.markerLayers = [];
                    Object.values(this.markerGroups).forEach(group => this.map.removeLayer(group));
                    this.markerGroups = {};
                },

                // Métodos públicos
                showMarkerGroup(name) {
                    this.markerGroups[name]?.addTo(this.map);
                },

                hideMarkerGroup(name) {
                    if (this.markerGroups[name]) this.map.removeLayer(this.markerGroups[name]);
                },

                toggleMarkerGroup(name) {
                    const group = this.markerGroups[name];
                    if (group) {
                        this.map.hasLayer(group) ? this.hideMarkerGroup(name) : this.showMarkerGroup(name);
                    }
                },

                fitToMarkers() {
                    if (!this.markerLayers.length) return;
                    const group = L.featureGroup(this.markerLayers.map(({
                        marker
                    }) => marker));
                    this.map.fitBounds(group.getBounds().pad(0.1));
                },

                fitToGroup(name) {
                    this.markerGroups[name]?.getBounds &&
                        this.map.fitBounds(this.markerGroups[name].getBounds().pad(0.1));
                },

                getMarkerById(id) {
                    return this.markerLayers.find(({
                        data
                    }) => data.id === id);
                }
            };

            MapWidget{{ $widgetId }}.init();
            window.MapWidget{{ $widgetId }} = MapWidget{{ $widgetId }};
        });
    </script>

    {!! $this->getAdditionalScripts() !!}
</x-filament-widgets::widget>
