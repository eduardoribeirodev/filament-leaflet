import L from "leaflet";

import 'leaflet/dist/leaflet.css';
import "@geoman-io/leaflet-geoman-free/dist/leaflet-geoman.css";
import 'leaflet.markercluster/dist/MarkerCluster.css'
import 'leaflet.markercluster/dist/MarkerCluster.Default.css'
import 'leaflet.fullscreen/dist/Control.FullScreen.css';
import 'leaflet-geosearch/dist/geosearch.css';
import '../css/index.css';

import "@geoman-io/leaflet-geoman-free";
import 'leaflet.markercluster'
import { FullScreen } from 'leaflet.fullscreen';
import { EsriProvider, GeoSearchControl } from 'leaflet-geosearch';

export class LeafletMapCore {
    constructor(config, imgsPath = '/vendor/filament-leaflet/images') {
        this.map = null;
        this.config = config;
        this.imgsPath = imgsPath;
        this.layers = [];
        this.layerGroups = {};
        this.baseLayers = {};
        this.geoJsonLayer = null;
        this.info = null;
        this.layerControl = null;
        this.isDrawing = false;
        this.callbacks = {};
    }

    /**
     * Inicializa o mapa com todas as configurações
     */
    init() {
        this.createMap();
        this.addTileLayers();
        this.addLayerGroups();
        this.setupMapControls();

        if (Object.keys(this.config.geoJsonData)?.length) {
            this.setupInfoControl();
            this.loadGeoJson();
        }

        this.addLayers();
        this.setupLayerControl();
    }

    /**
     * Obtém tradução do sistema
     */
    getTranslation(key, defaultText = '') {
        return (window.filamentData?.translations?.[key]) || defaultText;
    }

    /**
     * Cria o mapa base
     */
    createMap() {
        this.map = L.map(this.config.mapId, this.config.mapConfig || {})
            .setView(this.config.defaultCoord, this.config.defaultZoom);

        if (this.config.autoCenter) {
            this.map.locate({ setView: true });
        }

        const resizeObserver = new ResizeObserver(() => {
            if (!this.map) {
                return;
            }

            Alpine.raw(this.map).invalidateSize();
        });

        resizeObserver.observe(Alpine.raw(this.map._container));
    }

    /**
     * Adiciona camadas de tiles ao mapa
     */
    addTileLayers() {
        this.config.tileLayersUrl.forEach(([label, tileLayerUrl, attribution], index) => {
            const layer = L.tileLayer(tileLayerUrl, {
                maxZoom: this.config.zoomConfig.max,
                minZoom: this.config.zoomConfig.min,
                attribution: attribution || ''
            });

            this.baseLayers[label] = Alpine.raw(layer);

            if (index === 0) {
                Alpine.raw(layer).addTo(Alpine.raw(this.map));
            }
        });
    }

    /**
     * Configura o controle de informações
     */
    setupInfoControl() {
        this.info = L.control();
        this.info.onAdd = () => {
            const div = L.DomUtil.create('div', 'info');
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

        this.info.addTo(Alpine.raw(this.map));
    }

    /**
     * Carrega dados GeoJSON
     */
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
                        mouseover: (e) => this.info?.update(e.target.feature.properties),
                        mouseout: () => this.info?.update(),
                        click: (e) => Alpine.raw(this.map).fitBounds(e.target.getBounds())
                    });
                }
            }).addTo(Alpine.raw(this.map));
        } catch (error) {
            console.error('Erro GeoJSON:', error);
        }
    }

    /**
     * Obtém o estilo de uma feature GeoJSON
     */
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
    }

    /**
     * Adiciona camadas ao mapa
     * Callback onLayerClick pode ser passado para personalizar o comportamento
     */
    addLayers() {
        const layers = Alpine.raw(this.config.layers);
        if (!layers?.length) return;

        layers.forEach(layerData => {
            let layer = null;

            switch (layerData.type) {
                case 'marker':
                    layer = this.createMarker(layerData);
                    break;
                case 'circle':
                    layer = this.createCircle(layerData);
                    break;
                case 'circleMarker':
                    layer = this.createCircleMarker(layerData);
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

            layer.options.layerId = layerData.id || null;
            layer.options.group = layerData.group || null;

            if (layerData.popup) {
                this.bindPopup(layer, layerData.popup);
            }

            if (layerData.tooltip) {
                this.bindTooltip(layer, layerData.tooltip);
            }

            if (layerData.clickAction && this.callbacks.onLayerClick) {
                layer.on('click', () => {
                    this.callbacks.onLayerClick(layer.options.layerId);
                });
            }

            if (layerData.onMouseOver) {
                layer.on('mouseover', function () {
                    eval(layerData.onMouseOver);
                });
            }

            if (layerData.onMouseOut) {
                layer.on('mouseout', function () {
                    eval(layerData.onMouseOut);
                });
            }

            if (layerData.group) {
                Alpine.raw(this.layerGroups[layerData.group]['layer']).addLayer(layer);
            } else {
                layer.addTo(Alpine.raw(this.map));
            }

            Alpine.raw(this.layers).push(layer);
        });
    }

    /**
     * Adiciona grupos de camadas
     */
    addLayerGroups() {
        const groups = Alpine.raw(this.config.layerGroups);

        if (!Object.keys(groups)?.length > 0) return;

        groups.forEach(layerGroupData => {
            let layerGroup = null;

            layerGroupData.options.pmIgnore = true;
            switch (layerGroupData.type) {
                case 'group':
                    layerGroup = L.layerGroup(layerGroupData.options);
                    break;
                case 'feature':
                    layerGroup = L.featureGroup(layerGroupData.options);
                    break;
                case 'cluster':
                    layerGroup = L.markerClusterGroup({
                        ...layerGroupData.options,
                        spiderLegPolylineOptions: { pmIgnore: true }
                    });
                    break;
            }

            if (!layerGroup) return;

            layerGroup.addTo(Alpine.raw(this.map));

            this.layerGroups[layerGroupData.id] = {
                'name': layerGroupData.name,
                'layer': layerGroup
            };
        });
    }

    /**
     * Cria um marcador
     */
    createMarker(data) {
        if (data.lat == undefined || data.lng == undefined) return null;

        return L.marker([data.lat, data.lng], {
            icon: this.createIcon(data),
            draggable: data.draggable || false,
            pmIgnore: data.pmIgnore || false
        });
    }

    /**
     * Cria um círculo
     */
    createCircle(data) {
        if (!data.center) return null;
        return L.circle(data.center, data.options || {});
    }

    /**
     * Cria um marcador circular
     */
    createCircleMarker(data) {
        if (!data.center) return null;
        return L.circleMarker(data.center, data.options || {});
    }

    /**
     * Cria um retângulo
     */
    createRectangle(data) {
        if (!data.bounds) return null;
        return L.rectangle(data.bounds, data.options || {});
    }

    /**
     * Cria um polígono
     */
    createPolygon(data) {
        if (!data.points) return null;
        return L.polygon(data.points, data.options || {});
    }

    /**
     * Cria uma polilinha
     */
    createPolyline(data) {
        if (!data.points) return null;
        return L.polyline(data.points, data.options || {});
    }

    /**
     * Cria um ícone para marcadores
     */
    createIcon(marker) {
        const options = {
            iconSize: marker.iconSize || [25, 41],
            iconAnchor: marker.iconSize ? [marker.iconSize[0] / 2, marker.iconSize[1]] : [12, 41],
            popupAnchor: marker.iconSize ? [1, (marker.iconSize[1] / 1.25) * -1] : [1, -34],
            shadowSize: marker.iconSize ? [Math.max(...marker.iconSize), Math.max(...marker.iconSize)] : [41, 41]
        };

        if (marker.icon) {
            options.iconUrl = marker.icon;
        } else {
            const color = marker.color || 'blue';
            options.iconUrl = `${this.imgsPath}/marker-icon-2x-${color}.png`;
            options.shadowUrl = `${this.imgsPath}/marker-shadow.png`;
        }

        return L.icon(options);
    }

    /**
     * Vincula popup a uma camada
     */
    bindPopup(layer, popupConfig) {
        let html = '<div class="custom-popup">';

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
    }

    /**
     * Vincula tooltip a uma camada
     */
    bindTooltip(layer, tooltipConfig) {
        const content = tooltipConfig.content;
        const options = tooltipConfig.options || {};

        layer.bindTooltip(content, options);
    }

    /**
     * Configura o controle de camadas
     */
    setupLayerControl() {
        let layerGroups = Object.values(this.layerGroups)
            .filter((group) => group && group.name)
            .map((group) => [group.name, group.layer]);

        layerGroups = Object.fromEntries(layerGroups);
        let baseLayers = Alpine.raw(this.baseLayers);

        const hasBaseLayers = Object.keys(baseLayers)?.length > 1;
        const hasLayerGroups = Object.keys(layerGroups)?.length > 0;

        if (!hasBaseLayers && !hasLayerGroups) {
            return;
        }

        if (this.layerControl) {
            Alpine.raw(this.map).removeControl(Alpine.raw(this.layerControl));
        }

        this.layerControl = L.control.layers(
            baseLayers,
            layerGroups,
        );

        Alpine.raw(this.layerControl).addTo(Alpine.raw(this.map));
    }

    /**
     * Configura os controles do mapa
     */
    setupMapControls() {
        if (this.config.mapControls.attributionControl) {
            this.setupAttributionControl();
        }

        if (this.config.mapControls.scaleControl) {
            this.setupScaleControl();
        }

        if (this.config.mapControls.zoomControl) {
            this.setupZoomControl();
        }

        if (this.config.mapControls.fullscreenControl) {
            this.setupFullscreenControl();
        }

        if (this.config.mapControls.searchControl) {
            this.setupSearchControl();
        }

        if (this.config.mapControls.drawControls) {
            this.setupDrawControl();
        }
    }

    /**
     * Configura controle de atribuição
     */
    setupAttributionControl() {
        const attribution = new L.control.attribution();
        attribution.addTo(Alpine.raw(this.map));
    }

    /**
     * Configura controle de escala
     */
    setupScaleControl() {
        const scale = new L.control.scale();
        scale.addTo(Alpine.raw(this.map));
    }

    /**
     * Configura controle de zoom
     */
    setupZoomControl() {
        const zoom = new L.control.zoom();
        zoom.addTo(Alpine.raw(this.map));
    }

    /**
     * Configura controle de busca
     */
    setupSearchControl() {
        const provider = new EsriProvider();

        const search = new GeoSearchControl({
            provider: provider,
            notFoundMessage: this.getTranslation('address_not_found', ''),
            searchLabel: this.getTranslation('enter_address', ''),

            marker: {
                icon: this.createIcon({
                    color: 'blue'
                }),
                draggable: false,
            },
        });

        search.addTo(Alpine.raw(this.map));
    }

    /**
     * Configura controle de tela cheia
     */
    setupFullscreenControl() {
        const fullscreen = new FullScreen({
            title: this.getTranslation('full_screen', ''),
            titleCancel: this.getTranslation('exit_full_screen', ''),
            forceSeparateButton: true,
        });

        fullscreen.addTo(Alpine.raw(this.map));
    }

    /**
     * Configura controle de desenho
     */
    setupDrawControl() {
        Alpine.raw(this.map.pm).setLang(window.filamentData?.language);

        Alpine.raw(this.map.pm).setGlobalOptions({
            snappable: true,
            snapDistance: 20,
            markerStyle: {
                icon: this.createIcon({
                    color: 'blue'
                })
            }
        });

        Alpine.raw(this.map.pm).addControls(this.config.mapControls.drawControls);
    }

    /**
     * Configura event handlers base
     * Callbacks podem ser passados para personalizar comportamento
     */
    setupEventHandlers(callbacks = {}) {
        this.callbacks = callbacks;

        // MapClick
        Alpine.raw(this.map).on('click', (e) => {
            if (this.isDrawing) {
                return
            };

            const coords = e.latlng;

            if (this.callbacks.onMapClick) {
                this.callbacks.onMapClick(coords.lat, coords.lng);
            }
        });

        // MapRecenter
        let onmapMoveTimeout = null;
        Alpine.raw(this.map).on('move', () => {
            if (!this.config.mapConfig.recenterMapTimeout) {
                return;
            }

            clearTimeout(onmapMoveTimeout);

            const mapCenter = this.map.getCenter();
            const mapZoom = this.map.getZoom();
            if (
                Math.abs(mapCenter.lat - this.config.defaultCoord[0]) < 1 &&
                Math.abs(mapCenter.lng - this.config.defaultCoord[1]) < 1 &&
                Math.abs(mapZoom - this.config.defaultZoom) < 1
            ) {
                return;
            }

            onmapMoveTimeout = setTimeout(() => {
                this.map.flyTo(this.config.defaultCoord, this.config.defaultZoom);
            }, this.config.mapConfig.recenterMapTimeout);
        });

        // Geoman
        Alpine.raw(this.map).on('pm:globaldrawmodetoggled', (e) => {
            this.isDrawing = e.enabled;
        });
        
        Alpine.raw(this.map).on('pm:globaleditmodetoggled', (e) => {
            this.isDrawing = e.enabled;
        });

        Alpine.raw(this.map).on('pm:globaldragmodetoggled', (e) => {
            this.isDrawing = e.enabled;
        });

        Alpine.raw(this.map).on('pm:globalremovalmodetoggled', (e) => {
            this.isDrawing = e.enabled;
        });

        Alpine.raw(this.map).on('pm:globalcutmodetoggled', (e) => {
            this.isDrawing = e.enabled;
        });

        Alpine.raw(this.map).on('pm:globalrotatemodetoggled', (e) => {
            this.isDrawing = e.enabled;
        });
    }

    /**
     * Atualiza os dados do mapa
     */
    updateMapData(newConfig) {
        this.config = newConfig;

        if (Object.keys(this.config.geoJsonData)?.length) {
            this.setupInfoControl();
            this.loadGeoJson();
        }

        this.clearLayers();
        this.addLayerGroups();
        this.addLayers();
        this.setupLayerControl();
    }

    /**
     * Limpa todas as camadas do mapa
     */
    clearLayers() {
        this.layers.forEach(layer => {
            if (layer.options.group) {
                Alpine.raw(this.layerGroups[layer.options.group].layer).removeLayer(Alpine.raw(layer));
            } else {
                Alpine.raw(this.map).removeLayer(Alpine.raw(layer));
            }
        });

        this.layers = [];

        Object.values(this.layerGroups).forEach(({ layer }) => {
            Alpine.raw(this.map).removeLayer(Alpine.raw(layer));
        });

        this.layerGroups = {};

        if (this.layerControl) {
            Alpine.raw(this.map).removeControl(this.layerControl);
            this.layerControl = null;
        }
    }

    /**
     * Destrói o mapa e limpa recursos
     */
    destroy() {
        if (this.map) {
            this.clearLayers();
            Alpine.raw(this.map).remove();
            this.map = null;
        }
    }
}