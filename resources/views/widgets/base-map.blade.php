@php
    $config = $this->getWidgetData();
    $widgetId = $this->getId();
    $mapId = "mapa-{$widgetId}";
    $imgsPath = '/vendor/filament-leaflet/images';
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

        .info-{{ $widgetId }} {
            padding: 8px 10px;
            font: 14px/16px Arial, Helvetica, sans-serif;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
            border-radius: 5px;
            color: #333;
            border: 1px solid #ccc;
        }

        .custom-popup-{{ $widgetId }} {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            max-width: 300px;
        }

        .custom-popup-{{ $widgetId }} h4 {
            margin: 0 0 8px 0;
            color: #333;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
        }

        .custom-popup-{{ $widgetId }} p {
            margin: 4px 0;
            color: #666;
            line-height: 1.4;
        }

        .custom-popup-{{ $widgetId }} .field-label {
            font-weight: bold;
            color: #444;
        }

        .custom-popup-{{ $widgetId }} a {
            color: #0066cc;
            text-decoration: none;
        }

        .custom-popup-{{ $widgetId }} a:hover {
            text-decoration: underline;
        }

        /* Target the layer control button inside your specific map widget */
        #{{ $mapId }} .leaflet-control-layers-toggle {
            background-image: url('{{ $imgsPath }}/layers-2x.png') !important;
            background-size: 26px 26px;
            background-position: center;
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
                layers: [],
                layerGroups: {},
                baseLayers: {},
                geoJsonLayer: null,
                info: null,
                layerControl: null,

                init() {
                    this.createMap();
                    this.addTileLayers();

                    if (this.config.geoJsonData?.length) {
                        this.setupInfoControl();
                        this.loadGeoJson();
                    }

                    this.addLayers();
                    this.setupEventHandlers();
                    this.setupLivewireListeners();
                    this.setupLayerControl();

                    this.map.invalidateSize();
                    {!! $this->afterMapInit() !!}
                },

                createMap() {
                    this.map = L.map(this.mapId, this.config.mapConfig || {})
                        .setView(this.config.defaultCoord, this.config.defaultZoom);
                },

                addTileLayers() {
                    this.config.tileLayersUrl.forEach(([label, tileLayerUrl, attribution], index) => {
                        const layer = L.tileLayer(tileLayerUrl, {
                            maxZoom: this.config.zoomConfig.max,
                            minZoom: this.config.zoomConfig.min,
                            attribution: attribution || ''
                        });

                        this.baseLayers[label] = layer;

                        if (index === 0) {
                            layer.addTo(this.map);
                        }
                    });
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
                        console.error('Erro GeoJSON:', error);
                    }
                },

                getFeatureStyle(feature) {
                    const values = Object.values(this.config.geoJsonData);
                    const percentage = feature.properties.density / Math.max(...values);
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

                addLayers() {
                    if (!this.config.layers?.length) return;

                    this.config.layers.forEach(layerData => {
                        let layer = null;

                        // Cria o layer baseado no tipo
                        switch (layerData.type) {
                            case 'marker':
                                layer = this.createMarker(layerData);
                                break;
                            case 'cluster':
                                layer = this.createCluster(layerData);
                                break;
                            case 'circle':
                                layer = this.createCircle(layerData);
                                break;
                            case 'rectangle':
                                layer = this.createRectangle(layerData);
                                break;
                            case 'polygon':
                                layer = this.createPolygon(layerData);
                                break;
                            case 'polyline':
                                layer = this.createPolyline(layerData);
                                break;
                            default:
                                console.warn(`Tipo de layer desconhecido: ${layerData.type}`);
                                return;
                        }

                        if (!layer) return;

                        // Adiciona popup se existir
                        if (layerData.popup) {
                            this.bindPopup(layer, layerData.popup);
                        }

                        // Adiciona tooltip se existir
                        if (layerData.tooltip) {
                            this.bindTooltip(layer, layerData.tooltip);
                        }

                        // Adiciona evento de click
                        if (layerData.clickAction) {
                            layer.on('click', () => @this.onLayerClick(layerData.id));
                        }

                        // Adiciona ao grupo ou direto no mapa
                        if (layerData.group) {
                            this.layerGroups[layerData.group] = this.layerGroups[layerData.group] || L.layerGroup();
                            this.layerGroups[layerData.group].addLayer(layer);
                        } else {
                            layer.addTo(this.map);
                        }

                        // Salva referência
                        this.layers.push({
                            layer,
                            data: layerData
                        });
                    });

                    // Adiciona os grupos ao mapa
                    Object.values(this.layerGroups).forEach(group => group.addTo(this.map));
                },

                /**
                 * Cria um marcador
                 */
                createMarker(data) {
                    if (!data.lat || !data.lng) return null;

                    const marker = L.marker([data.lat, data.lng], {
                        icon: this.createIcon(data),
                        title: data.title || data.popup?.title || data.tooltip?.content || '',
                        draggable: data.draggable || false
                    });

                    return marker;
                },

                /**
                 * Cria um cluster de marcadores
                 */
                createCluster(clusterData) {
                    const cluster = L.markerClusterGroup(clusterData.config || {});

                    if (!clusterData.markers?.length) return null;

                    clusterData.markers.forEach(markerData => {
                        const marker = this.createMarker(markerData);
                        if (!marker) return;

                        if (markerData.popup) {
                            this.bindPopup(marker, markerData.popup);
                        }

                        if (markerData.tooltip) {
                            this.bindTooltip(marker, markerData.tooltip);
                        }

                        cluster.addLayer(marker);
                    });

                    return cluster;
                },

                /**
                 * Cria um círculo
                 */
                createCircle(data) {
                    if (!data.center || !data.radius) return null;
                    return L.circle(data.center, data.radius, data.options || {});
                },

                /**
                 * Cria um retângulo
                 */
                createRectangle(data) {
                    if (!data.bounds) return null;
                    return L.rectangle(data.bounds, data.options || {});
                },

                /**
                 * Cria um polígono
                 */
                createPolygon(data) {
                    if (!data.points) return null;
                    return L.polygon(data.points, data.options || {});
                },

                /**
                 * Cria uma polyline
                 */
                createPolyline(data) {
                    if (!data.points) return null;
                    return L.polyline(data.points, data.options || {});
                },

                /**
                 * Cria o ícone do marcador
                 */
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
                        options.iconUrl = `{{ $imgsPath }}/marker-icon-2x-${marker.color}.png`;
                        options.shadowUrl = `{{ $imgsPath }}/marker-shadow.png`;
                    }

                    return L.icon(options);
                },

                /**
                 * Vincula um popup a um layer
                 */
                bindPopup(layer, popupConfig) {
                    let html = `<div class="custom-popup-${this.widgetId}">`;

                    if (popupConfig.title) {
                        html += `<h4>${popupConfig.title}</h4>`;
                    }

                    if (popupConfig.content) {
                        html += popupConfig.content;
                    }
                    
                    if (popupConfig.fields && Object.keys(popupConfig.fields).length > 0) {
                        Object.entries(popupConfig.fields).forEach(([key, value]) => {
                            html += `<p><span class="field-label">${key}:</span> ${value}</p>`;
                        });
                    }

                    html += '</div>';

                    layer.bindPopup(html, popupConfig.options || {});
                },

                /**
                 * Vincula um tooltip a um layer
                 */
                bindTooltip(layer, tooltipConfig) {
                    const content = tooltipConfig.content;
                    const options = tooltipConfig.options || {};

                    layer.bindTooltip(content, options);
                },

                setupLayerControl() {
                    const hasBaseLayers = Object.keys(this.baseLayers).length > 0;
                    const hasOverlays = Object.keys(this.layerGroups).length > 0;

                    if (!hasBaseLayers && !hasOverlays) {
                        return;
                    }

                    if (this.layerControl) {
                        this.map.removeControl(this.layerControl);
                    }

                    this.layerControl = L.control.layers(
                        this.baseLayers,
                        this.layerGroups
                    ).addTo(this.map);
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
                    this.clearLayers();

                    // Recarrega todos os layers
                    if (this.config.layers?.length) {
                        this.addLayers();
                    }

                    if (this.config.geoJsonData?.length) {
                        if (this.geoJsonLayer) this.map.removeLayer(this.geoJsonLayer);
                        this.loadGeoJson();
                    }

                    this.setupLayerControl();
                },

                clearLayers() {
                    // Limpa todos os layers
                    this.layers.forEach(({
                        layer
                    }) => {
                        if (layer.removeFrom) {
                            layer.removeFrom(this.map);
                        } else {
                            this.map.removeLayer(layer);
                        }
                    });
                    this.layers = [];

                    // Limpa grupos
                    Object.values(this.layerGroups).forEach(group => this.map.removeLayer(group));
                    this.layerGroups = {};

                    if (this.layerControl) {
                        this.map.removeControl(this.layerControl);
                        this.layerControl = null;
                    }
                },

                fitToLayers() {
                    const layersToFit = this.layers
                        .map(({
                            layer
                        }) => layer)
                        .filter(layer => layer.getLatLng || layer.getBounds);

                    if (!layersToFit.length) return;

                    const group = L.featureGroup(layersToFit);
                    this.map.fitBounds(group.getBounds().pad(0.1));
                },

                fitToGroup(name) {
                    const group = this.layerGroups[name];
                    if (group?.getBounds) {
                        this.map.fitBounds(group.getBounds().pad(0.1));
                    }
                },

                getLayerById(id) {
                    return this.layers.find(({
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
