import L from "leaflet";

import 'leaflet/dist/leaflet.css';
import "@geoman-io/leaflet-geoman-free/dist/leaflet-geoman.css";
import 'leaflet.markercluster/dist/MarkerCluster.css'
import 'leaflet.markercluster/dist/MarkerCluster.Default.css'
import 'leaflet.fullscreen/dist/Control.FullScreen.css';
import 'leaflet-geosearch/dist/geosearch.css';
import '../css/index.css';

import 'leaflet-hero-marker'
import "@geoman-io/leaflet-geoman-free";
import 'leaflet.markercluster'
import { FullScreen } from 'leaflet.fullscreen';
import {
    GeoSearchControl,
    EsriProvider,
    GoogleProvider,
    MapBoxProvider,
    BingProvider,
    OpenStreetMapProvider
} from 'leaflet-geosearch';

export class LeafletMapCore {
    constructor(config) {
        this.map = null;
        this.config = config;
        this.layers = new Map();
        this.layerGroups = new Map();
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

        if (this.config.fitBounds) {
            this.applyFitBounds();
        }
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
                attribution: attribution
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

    applyFitBounds() {
        const bounds = L.latLngBounds();

        // Collect bounds from all layers
        this.layers.forEach(({ layer }) => {
            const rawLayer = Alpine.raw(layer);
            if (
                rawLayer instanceof L.Marker ||
                rawLayer instanceof L.CircleMarker
            ) {
                bounds.extend(rawLayer.getLatLng());

            } else if (
                rawLayer instanceof L.Circle ||
                rawLayer instanceof L.Path
            ) {
                bounds.extend(rawLayer.getBounds());
            }
        });

        // Collect bounds from layer groups
        this.layerGroups.forEach(({ layer }) => {
            const rawLayer = Alpine.raw(layer);
            if (rawLayer && typeof rawLayer.getBounds === 'function') {
                const groupBounds = rawLayer.getBounds();
                if (groupBounds.isValid()) {
                    bounds.extend(groupBounds);
                }
            }
        });

        // Also include geoJson layer bounds if available
        if (this.geoJsonLayer) {
            const rawGeoJson = Alpine.raw(this.geoJsonLayer);
            if (rawGeoJson && typeof rawGeoJson.getBounds === 'function') {
                const geojsonBounds = rawGeoJson.getBounds();
                if (geojsonBounds.isValid()) {
                    bounds.extend(geojsonBounds);
                }
            }
        }

        // Apply fitBounds if we have any bounds to fit
        if (bounds.isValid()) {
            Alpine.raw(this.map).fitBounds(bounds, {
                padding: [50, 50],
                maxZoom: this.config.zoomConfig.max
            });
        }
    }

    removeLayer(layerData) {
        let layer = this.layers.get(layerData.id);

        if (!layer) return;

        if (layer.layer.options.group) {
            const group = this.layerGroups.get(layer.layer.options.group);
            Alpine.raw(group.layer).removeLayer(Alpine.raw(layer.layer));
        } else {
            Alpine.raw(this.map).removeLayer(Alpine.raw(layer.layer));
        }
    }

    addLayer(layerData) {
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

        layer.options.group = layerData.group || null;

        if (layerData.popup) {
            this.bindPopup(layer, layerData.popup);
        }

        if (layerData.tooltip) {
            this.bindTooltip(layer, layerData.tooltip);
        }

        layer.on('click', () => {
            if (this.callbacks.onLayerClick) {
                this.callbacks.onLayerClick(layerData.id);
            }
        });

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

        if (layerData.isEditable) {
            layer.pm.enable();
        } else {
            layer.pm.disable();
        }

        const updateHandler = (e) => {
            if (this.callbacks.onLayerUpdated) {
                const target = e.target;
                let data = {};

                if (target instanceof L.Marker) {
                    data = target.getLatLng();
                } else if (target instanceof L.Circle || target instanceof L.CircleMarker) {
                    data = {
                        ...target.getLatLng(),
                        radius: target.getRadius(),
                    };
                } else if (target instanceof L.Rectangle) {
                    const bounds = Object.values(target.getBounds()).map((latlng) => [latlng.lat, latlng.lng]);
                    data = {
                        bounds: bounds,
                    };
                } else if (target instanceof L.Polygon) {
                    const latlngs = target.getLatLngs()[0].map((latlng) => [latlng.lat, latlng.lng]);
                    data = {
                        points: latlngs,
                    };
                } else if (target instanceof L.Polyline) {
                    const latlngs = target.getLatLngs().map((latlng) => [latlng.lat, latlng.lng]);
                    data = {
                        points: latlngs,
                    };
                }

                this.callbacks.onLayerUpdated(layerData.id, data);
            }
        };

        layer.on('dragend', updateHandler);
        layer.on('pm:update', updateHandler);

        if (layerData.group) {
            const group = this.layerGroups.get(layerData.group);
            Alpine.raw(layer).addTo(Alpine.raw(group.layer));
        } else {
            Alpine.raw(layer).addTo(Alpine.raw(this.map));
        }

        Alpine.raw(this.layers).set(layerData.id, {
            layer: Alpine.raw(layer),
            data: layerData
        });
    }

    /**
     * Adiciona camadas ao mapa
     * Callback onLayerClick pode ser passado para personalizar o comportamento
     */
    addLayers() {
        const layers = Alpine.raw(this.config.layersData);
        if (!layers?.length) return;

        layers.forEach(layerData => this.addLayer(layerData));
    }

    /**
     * Adiciona grupos de camadas
     */
    addLayerGroups() {
        const groupsData = Alpine.raw(this.config.layerGroupsData);

        if (!Object.keys(groupsData)?.length > 0) return;

        groupsData.forEach(layerGroupData => {
            let layerGroup = null;

            switch (layerGroupData.type) {
                case 'group':
                    layerGroup = L.layerGroup(layerGroupData.options);
                    break;
                case 'feature':
                    layerGroup = L.featureGroup(layerGroupData.options);
                    break;
                case 'cluster':
                    layerGroup = L.markerClusterGroup(layerGroupData.options);
                    break;
                default:
                    console.warn(`Tipo de grupo desconhecido: ${layerGroupData.type}`);
                    return;
            }

            if (!layerGroup) return;

            this.layerGroups.set(layerGroupData.id, {
                name: layerGroupData.name,
                layer: layerGroup,
            });

            layerGroup.addTo(Alpine.raw(this.map));
        });
    }

    createMarker(markerData) {
        const icon = this.createIcon(markerData.icon);

        const marker = L.marker(markerData.coords, {
            icon: icon,
            draggable: markerData.draggable,
        });

        return marker;
    }

    createCircle(circleData) {
        const circle = L.circle(circleData.center, circleData.options);
        return circle;
    }

    createCircleMarker(circleMarkerData) {
        const circleMarker = L.circleMarker(circleMarkerData.center, circleMarkerData.options);
        return circleMarker;
    }

    createRectangle(rectangleData) {
        const rectangle = L.rectangle(rectangleData.bounds, rectangleData.options);
        return rectangle;
    }

    createPolygon(polygonData) {
        const polygon = L.polygon(polygonData.points, polygonData.options);
        return polygon;
    }

    createPolyline(polylineData) {
        const polyline = L.polyline(polylineData.points, polylineData.options);
        return polyline;
    }

    createIcon(iconData) {
        return L.heroIcon(iconData);
    }

    /**
     * Vincula popup a uma camada
     */
    bindPopup(layer, popupConfig) {
        let html = '<div class="custom-popup">';

        if (popupConfig.title) {
            html += `<strong>${popupConfig.title}</strong>`;
        }

        if (popupConfig.content) {
            html += `<div>${popupConfig.content}</div>`;
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
        let layerGroups = Array.from(this.layerGroups.values())
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

        if (this.config.mapControls.geoSearchControl) {
            this.setupSearchControl();
        }

        if (this.config.mapControls.drawControls) {
            this.setupDrawControl();
        }

        this.setupLayerControl();
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
        const zoom = new L.control.zoom({
            zoomInTitle: this.getTranslation('zoom_in'),
            zoomOutTitle: this.getTranslation('zoom_out')
        });
        zoom.addTo(Alpine.raw(this.map));
    }

    /**
     * Configura controle de busca
     */
    setupSearchControl() {
        const provider = this.createGeoSearchProvider();

        if (!provider) {
            return;
        }

        const search = new GeoSearchControl({
            provider: provider,
            notFoundMessage: this.getTranslation('address_not_found'),
            searchLabel: this.getTranslation('enter_address'),

            marker: {
                icon: this.createIcon(),
                draggable: false,
            },
        });

        search.addTo(Alpine.raw(this.map));
    }

    /**
     * Cria o provedor de geosearch baseado na configuração
     */
    createGeoSearchProvider() {
        const config = this.config.geoSearchConfig;
        const provider = config?.provider || 'nominatim';
        const apiKey = config?.apiKey;

        switch (provider) {
            case 'nominatim':
                return new OpenStreetMapProvider();

            case 'google':
                if (!apiKey) {
                    console.warn('Google Maps API Key is required but not provided');
                    return new EsriProvider();
                }
                return new GoogleProvider({
                    params: {
                        key: apiKey,
                    }
                });

            case 'mapbox':
                if (!apiKey) {
                    console.warn('Mapbox API Key is required but not provided');
                    return new EsriProvider();
                }
                return new MapBoxProvider({
                    params: {
                        access_token: apiKey,
                    }
                });

            case 'bing':
                if (!apiKey) {
                    console.warn('Bing Maps API Key is required but not provided');
                    return new EsriProvider();
                }
                return new BingProvider({
                    params: {
                        key: apiKey,
                    }
                });

            default:
                console.warn(`Unknown geosearch provider: ${provider}, using Esri`);
                return new EsriProvider();
        }
    }

    /**
     * Configura controle de tela cheia
     */
    setupFullscreenControl() {
        const fullscreen = new FullScreen({
            title: this.getTranslation('full_screen'),
            titleCancel: this.getTranslation('exit_full_screen'),
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
                icon: this.createIcon()
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
     * Atualiza marcadores já existentes (mesmo id) quando coords, popup ou ícone mudam
     * (ex.: Livewire / wire:poll). Preserva add/remove por id em updateMapData.
     */
    syncExistingMarkers(prevLayers, nextLayers) {
        nextLayers.forEach((layerData) => {
            if (layerData.type !== 'marker') {
                return;
            }

            const oldLayerData = prevLayers.find((oldLayer) => oldLayer.id == layerData.id);
            if (!oldLayerData || oldLayerData.type !== 'marker') {
                return;
            }

            const entry = this.layers.get(layerData.id);
            if (!entry?.layer) {
                return;
            }

            const marker = Alpine.raw(entry.layer);

            const newCoords = layerData.coords;
            if (Array.isArray(newCoords) && newCoords.length >= 2) {
                const cur = marker.getLatLng();
                const eps = 1e-7;
                if (
                    Math.abs(cur.lat - newCoords[0]) > eps ||
                    Math.abs(cur.lng - newCoords[1]) > eps
                ) {
                    marker.setLatLng(newCoords);
                }
            }

            const oldPopup = oldLayerData.popup;
            const newPopup = layerData.popup;

            if (newPopup) {
                const titleChanged = (oldPopup?.title ?? '') !== (newPopup.title ?? '');
                const contentChanged = (oldPopup?.content ?? '') !== (newPopup.content ?? '');

                if (!oldPopup || titleChanged || contentChanged) {
                    if (marker.getPopup()) {
                        marker.unbindPopup();
                    }
                    this.bindPopup(marker, newPopup);
                }
            } else if (oldPopup && marker.getPopup()) {
                marker.unbindPopup();
            }

            const oldIcon = oldLayerData.icon;
            const newIcon = layerData.icon;

            if (newIcon) {
                const urlChanged = (oldIcon?.url ?? '') !== (newIcon.url ?? '');
                const sizeChanged =
                    JSON.stringify(oldIcon?.size ?? []) !== JSON.stringify(newIcon.size ?? []);
                const colorChanged = (oldIcon?.color ?? '') !== (newIcon.color ?? '');
                const heroChanged = (oldIcon?.heroicon ?? '') !== (newIcon.heroicon ?? '');

                if (!oldIcon || urlChanged || sizeChanged || colorChanged || heroChanged) {
                    marker.setIcon(this.createIcon(newIcon));
                }
            }

            this.layers.set(layerData.id, {
                layer: entry.layer,
                data: layerData,
            });
        });

        this.layerGroups.forEach(({ layer }) => {
            const rawLayer = Alpine.raw(layer);
            if (rawLayer && typeof rawLayer.refreshClusters === 'function') {
                rawLayer.refreshClusters();
            }
        });
    }

    /**
     * Atualiza os dados do mapa
     */
    updateMapData(newConfig) {
        const oldConfig = this.config;
        const prevLayers = [...(oldConfig.layersData ?? [])];

        this.config = newConfig;

        const nextLayers = [...(this.config.layersData ?? [])];

        let layersToAdd = [];
        let layersToRemove = [];

        prevLayers.forEach((layerData) => {
            if (!nextLayers.find((newLayer) => newLayer.id == layerData.id)) {
                layersToRemove.push(layerData);
            }
        });

        nextLayers.forEach((layerData) => {
            if (!prevLayers.find((oldLayer) => oldLayer.id == layerData.id)) {
                layersToAdd.push(layerData);
            }
        });

        layersToRemove.forEach((layerData) => this.removeLayer(layerData));
        layersToAdd.forEach((layerData) => this.addLayer(layerData));

        this.syncExistingMarkers(prevLayers, nextLayers);

        if (Object.keys(this.config.geoJsonData)?.length) {
            this.setupInfoControl();
            this.loadGeoJson();
        }

        if (this.config.fitBounds) {
            this.applyFitBounds();
        }
    }

    /**
     * Limpa todas as camadas do mapa
     */
    clearLayers() {
        Object.values(this.layers).forEach(({ layer }) => {
            if (layer.options.group) {
                Alpine.raw(this.layerGroups[layer.options.group].layer).removeLayer(Alpine.raw(layer));
            } else {
                Alpine.raw(this.map).removeLayer(Alpine.raw(layer));
            }
        });

        this.layers.clear();

        Object.values(this.layerGroups).forEach(({ layer }) => {
            Alpine.raw(this.map).removeLayer(Alpine.raw(layer));
        });

        this.layerGroups.clear();

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