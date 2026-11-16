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
import { BoundarySourceCache, GeoJsonBoundaryController, createChoroplethScale, forEachFrame, getFeatureLabel, getFeatureValue, selectFocusCountry } from './geojson-boundary.js';

export class LeafletMapCore {
    constructor(config) {
        this.map = null;
        this.config = config;
        this.layers = new Map();
        this.layerGroups = new Map();
        this.baseLayers = {};
        this.info = null;
        this.layerControl = null;
        this.isDrawing = false;
        this.callbacks = {};
        this.boundaryControllers = new Map();
        this.boundarySourceCache = new BoundarySourceCache();
        this.autoFocusCountry = null;
        this.boundaryRenderer = null;
        this.initializingLayers = false;
        this.initialBoundaryLoads = [];
        this.initialFitPending = false;
        this.initialFitCancelled = false;
    }

    /**
     * Inicializa o mapa com todas as configurações
     */
    init() {
        this.createMap();
        this.addTileLayers();
        this.addLayerGroups();
        this.setupMapControls();

        this.initialFitPending = Boolean(this.config.fitBounds);
        const onInitialNavigation = () => { this.initialFitCancelled = true; };
        Alpine.raw(this.map).on('movestart zoomstart', onInitialNavigation);
        this.initializingLayers = true;
        this.addLayers();
        this.initializingLayers = false;

        const finishInitialFit = () => {
            if (!this.map) return;
            Alpine.raw(this.map).off('movestart zoomstart', onInitialNavigation);
            this.initialFitPending = false;
            if (this.config.fitBounds && !this.initialFitCancelled) this.applyFitBounds(true);
        };

        if (this.initialBoundaryLoads.length) {
            Promise.all(this.initialBoundaryLoads).then(finishInitialFit);
        } else {
            finishInitialFit();
        }
        this.initialBoundaryLoads = [];
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
        if (this.info) return;

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
                this.info._div.replaceChildren();

                const label = document.createElement('strong');
                label.textContent = props.label ?? '';
                this.info._div.append(label);

                if (props.value !== undefined && props.value !== null) {
                    const value = document.createElement('div');
                    value.textContent = String(props.value);
                    this.info._div.append(value);
                }
            } else {
                this.info._div.style.display = 'none';
            }
        };

        this.info.addTo(Alpine.raw(this.map));
    }

    createGeoJson(boundaryData) {
        const geoJsonLayer = L.geoJson(null);
        const initialLayer = this.initializingLayers;
        let renderedGroup = null;
        let renderedFeatures = new Map();
        let renderedPresentation = null;
        const controller = new GeoJsonBoundaryController({
            data: boundaryData,
            cache: Alpine.raw(this.boundarySourceCache),
            getZoom: () => Alpine.raw(this.map).getZoom(),
            getView: () => {
                const map = Alpine.raw(this.map);
                if (typeof map.getSize !== 'function' || typeof map.getCenter !== 'function') return null;
                const bounds = map.getBounds(), center = map.getCenter(), size = map.getSize();
                const view = {
                    bounds: [bounds.getWest(), bounds.getSouth(), bounds.getEast(), bounds.getNorth()],
                    center: [center.lng, center.lat], size: [size.x, size.y]
                };
                this.autoFocusCountry = selectFocusCountry(this.config.layersData || [], view.center, this.autoFocusCountry, view);
                return { ...view, focusCountry: this.autoFocusCountry };
            },
            getBounds: () => {
                const current = Alpine.raw(this.map).getBounds();
                const bounds = typeof current.pad === 'function' ? current.pad(0.5) : current;
                return typeof bounds.getWest === 'function'
                    ? [bounds.getWest(), bounds.getSouth(), bounds.getEast(), bounds.getNorth()]
                    : null;
            },
            commit: async (data, activeBoundary, { signal }) => {
                const contexts = activeBoundary.displayParts || [activeBoundary];
                const scales = new Map(contexts.map((context) => [context.visualization,
                context.visualization?.type === 'choropleth'
                    ? createChoroplethScale(context.visualization.values || {}, context.visualization.colors || [], context.visualization.missingColor, context.visualization.domain)
                    : null]));
                const contextFor = (feature) => activeBoundary.featureContexts?.get(feature) || activeBoundary;
                if (!activeBoundary.isEditable && !this.boundaryRenderer && L.canvas) {
                    this.boundaryRenderer = L.canvas({ padding: 0.5 });
                }
                const options = {
                    renderer: !activeBoundary.isEditable ? Alpine.raw(this.boundaryRenderer) : undefined,
                    pmIgnore: !activeBoundary.isEditable,
                    style: (feature) => {
                        const context = contextFor(feature);
                        return this.getBoundaryFeatureStyle(feature, context, scales.get(context.visualization));
                    },
                    onEachFeature: (feature, featureLayer) => this.bindBoundaryFeature(feature, featureLayer, contextFor(feature)),
                };
                const equals = (a, b) => a && a.length === b.length && a.every((value, index) => value === b[index]);
                const presentation = [activeBoundary.style, activeBoundary.popup, activeBoundary.tooltip, activeBoundary.isEditable];
                const reuseGroup = equals(renderedPresentation, presentation);
                const target = reuseGroup ? renderedGroup : L.geoJson(null, options);
                const nextFeatures = new Map(), additions = [];
                let attached = reuseGroup, complete = false;
                const attach = () => {
                    if (!attached) Alpine.raw(geoJsonLayer).addLayer(target);
                    attached = true;
                };
                const discard = () => {
                    if (!reuseGroup) Alpine.raw(geoJsonLayer).removeLayer(target);
                    else for (const entry of additions) if (entry.staged) target.removeLayer(entry.layer);
                };
                signal.addEventListener('abort', discard, { once: true });

                try {
                    if (!reuseGroup) {
                        if (activeBoundary.popup) this.bindPopup(target, activeBoundary.popup);
                        if (activeBoundary.tooltip) this.bindTooltip(target, activeBoundary.tooltip);
                    }
                    const prepared = await forEachFrame(data.features || [data], (feature) => {
                        const context = contextFor(feature);
                        const featurePresentation = [context.displayLevel, context.style, context.features,
                        context.visualization, context.administrative, context.popup, context.tooltip, context.isEditable];
                        const previous = reuseGroup && renderedFeatures.get(feature);
                        if (previous && equals(previous.presentation, featurePresentation)) {
                            nextFeatures.set(feature, previous);
                            return;
                        }
                        const part = L.geoJson({ type: 'FeatureCollection', features: [feature] }, options);
                        for (const layer of part.getLayers()) {
                            part.removeLayer(layer);
                            const entry = { layer, presentation: featurePresentation, staged: false, restore: [] };
                            additions.push(entry);
                            nextFeatures.set(feature, entry);

                            // Project Canvas paths in batches too. Keep them invisible and
                            // noninteractive until the whole replacement is ready.
                            const paths = [];
                            const collectPaths = (child) => {
                                if (child instanceof L.Path) { paths.push(child); return true; }
                                return typeof child.getLayers === 'function' && child.getLayers().every(collectPaths);
                            };
                            if (!activeBoundary.isEditable && this.boundaryRenderer && collectPaths(layer)) {
                                for (const path of paths) {
                                    const style = {
                                        opacity: path.options.opacity, fillOpacity: path.options.fillOpacity,
                                        interactive: path.options.interactive
                                    };
                                    path.setStyle({ opacity: 0, fillOpacity: 0, interactive: false });
                                    entry.restore.push(() => path.setStyle(style));
                                }
                                attach();
                                entry.staged = true;
                                target.addLayer(layer);
                            }
                        }
                    }, { signal });
                    if (!prepared || signal.aborted) return false;

                    attach();
                    for (const entry of additions) {
                        if (!entry.staged) target.addLayer(entry.layer);
                        entry.restore.forEach((restore) => restore());
                        if (activeBoundary.isEditable) entry.layer.pm?.enable();
                        else entry.layer.pm?.disable();
                    }
                    if (reuseGroup) {
                        for (const [feature, entry] of renderedFeatures) {
                            if (nextFeatures.get(feature) !== entry) target.removeLayer(entry.layer);
                        }
                    } else if (renderedGroup) Alpine.raw(geoJsonLayer).removeLayer(renderedGroup);

                    renderedGroup = target;
                    renderedFeatures = nextFeatures;
                    renderedPresentation = presentation;
                    complete = true;
                    this.info?.update();
                    return true;
                } finally {
                    signal.removeEventListener('abort', discard);
                    if (!complete) discard();
                }
            },
            onLoaded: ({ initial }) => {
                if (!initialLayer && !this.initialFitPending && initial && this.config.fitBounds && this.map) {
                    this.applyFitBounds();
                }
            },
            onError: (error) => console.error('Erro ao carregar GeoJSON do Boundary:', error),
        });

        let moveTimer;
        const onMoveStart = () => {
            clearTimeout(moveTimer);
            controller.suspend();
        };
        const onZoomEnd = () => {
            clearTimeout(moveTimer);
            controller.resume('zoom');
        };
        const onMoveEnd = () => {
            clearTimeout(moveTimer);
            moveTimer = setTimeout(() => controller.resume('move'), 100);
        };
        const cleanup = () => clearTimeout(moveTimer);
        Alpine.raw(this.map).on('zoomend', onZoomEnd);
        Alpine.raw(this.map).on('moveend', onMoveEnd);
        Alpine.raw(this.map).on('movestart zoomstart', onMoveStart);
        this.boundaryControllers.set(boundaryData.id, { controller, onZoomEnd, onMoveEnd, onMoveStart, cleanup });
        const initialLoad = controller.request();
        if (initialLayer) this.initialBoundaryLoads.push(initialLoad);

        return geoJsonLayer;
    }

    getBoundaryFeatureStyle(feature, boundaryData, colorScale) {
        const visualization = boundaryData.visualization;
        if (visualization?.type !== 'choropleth') return boundaryData.style || {};

        const values = visualization.values || {};
        const key = getFeatureValue(feature, boundaryData.features?.key);
        const value = values[key];
        const fillColor = colorScale(value);

        return {
            ...(boundaryData.style || {}),
            fillColor,
        };
    }

    bindBoundaryFeature(feature, featureLayer, boundaryData) {
        const key = getFeatureValue(feature, boundaryData.features?.key);
        const value = boundaryData.visualization?.values?.[key];

        featureLayer.on({
            mouseover: (e) => {
                const label = getFeatureLabel(feature, boundaryData.features?.label || 'name', this.config.regionNames);
                this.setupInfoControl();
                this.info?.update({
                    name: label,
                    label,
                    density: value,
                    value,
                });
            },
            mouseout: () => this.info?.update(),
            click: (e) => {
                if (typeof e.target.getBounds === 'function') {
                    const controller = Alpine.raw(this.boundaryControllers.get(boundaryData.id)?.controller);
                    const automatic = boundaryData.administrative?.auto;
                    let focused = false;
                    if (automatic && controller) {
                        if (boundaryData.displayLevel === 1) {
                            const state = automatic.states.find((state) =>
                                (state.featureId != null && state.featureId === feature.properties?.shapeID)
                                || (state.key != null && state.key === getFeatureValue(feature, boundaryData.administrative.levels[1].features?.key)));
                            if (state) focused = controller.focusState(state.code, automatic.countryCode);
                        } else if (boundaryData.displayLevel === 0) focused = controller.focusCountry(automatic.countryCode);
                    }
                    Alpine.raw(this.map).fitBounds(e.target.getBounds(), automatic
                        ? { padding: [30, 30], maxZoom: this.config.zoomConfig.max } : undefined);
                    if (focused) {
                        this.autoFocusCountry = automatic.countryCode || null;
                        const applyFocus = () => {
                            if (this.map && !controller.suspended && !controller.destroyed) controller.request('focus');
                        };

                        if (typeof requestAnimationFrame === 'function') requestAnimationFrame(applyFocus);
                        else setTimeout(applyFocus, 0);
                    }
                }
            },
        });
    }

    applyFitBounds(includeBoundarySources = false) {
        const bounds = L.latLngBounds();

        if (includeBoundarySources) {
            const extendSource = (source) => {
                if (source?.bounds) {
                    const [west, south, east, north] = source.bounds;
                    bounds.extend([south, west]);
                    bounds.extend([north, east < west ? east + 360 : east]);
                }
                if (source?.type === 'collection') source.value.forEach(extendSource);
            };
            this.layers.forEach(({ data }) => extendSource(data?.source));
        }

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

            } else if (rawLayer && typeof rawLayer.getBounds === 'function') {
                const layerBounds = rawLayer.getBounds();
                if (layerBounds.isValid()) {
                    bounds.extend(layerBounds);
                }
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

        const boundary = this.boundaryControllers.get(layerData.id);
        if (boundary) {
            Alpine.raw(boundary.controller).destroy();
            Alpine.raw(this.map).off('zoomend', boundary.onZoomEnd);
            Alpine.raw(this.map).off('moveend', boundary.onMoveEnd);
            Alpine.raw(this.map).off('movestart zoomstart', boundary.onMoveStart);
            boundary.cleanup();
            this.boundaryControllers.delete(layerData.id);
            this.info?.update();
        }

        if (layer.layer.options.group) {
            const group = this.layerGroups.get(layer.layer.options.group);
            if (group) Alpine.raw(group.layer).removeLayer(Alpine.raw(layer.layer));
        } else {
            Alpine.raw(this.map).removeLayer(Alpine.raw(layer.layer));
        }
        this.layers.delete(layerData.id);
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
            case 'geojson':
                layer = this.createGeoJson(layerData);
                break;
            default:
                console.warn(`Tipo de layer desconhecido: ${layerData.type}`);
                return;
        }

        if (!layer) return;

        layer.options.group = layerData.group || null;

        if (layerData.popup && layerData.type !== 'geojson') {
            this.bindPopup(layer, layerData.popup);
        }

        if (layerData.tooltip && layerData.type !== 'geojson') {
            this.bindTooltip(layer, layerData.tooltip);
        }

        layer.on('click', () => {
            if (this.callbacks.onLayerClick) {
                this.callbacks.onLayerClick(layerData.id);
            }
        });

        const getLayerData = () => this.layers.get(layerData.id)?.data || layerData;
        layer.on('mouseover', function () {
            const script = getLayerData().onMouseOver;
            if (script) eval(script);
        });
        layer.on('mouseout', function () {
            const script = getLayerData().onMouseOut;
            if (script) eval(script);
        });

        if (layerData.isEditable) layer.pm?.enable();
        else layer.pm?.disable();

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

    syncExistingBoundaries(prevLayers, nextLayers) {
        nextLayers.forEach((data) => {
            if (data.type !== 'geojson') return;
            const previous = prevLayers.find((item) => item.id == data.id && item.type === 'geojson');
            if (!previous || JSON.stringify(previous) === JSON.stringify(data)) return;

            const entry = this.layers.get(data.id);
            const boundary = this.boundaryControllers.get(data.id);
            if (!entry || !boundary) return;

            const layer = Alpine.raw(entry.layer);
            if ((previous.group || null) !== (data.group || null)) {
                const oldParent = previous.group
                    ? this.layerGroups.get(previous.group)?.layer
                    : this.map;
                const newParent = data.group ? this.layerGroups.get(data.group)?.layer : this.map;
                if (oldParent) Alpine.raw(oldParent).removeLayer(layer);
                if (newParent) Alpine.raw(newParent).addLayer(layer);
                layer.options.group = data.group || null;
            }

            this.layers.set(data.id, { layer, data });
            Alpine.raw(boundary.controller).update(data);
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
        this.syncExistingBoundaries(prevLayers, nextLayers);

        const otherLayersChanged = JSON.stringify(prevLayers.filter((layer) => layer.type !== 'geojson'))
            !== JSON.stringify(nextLayers.filter((layer) => layer.type !== 'geojson'));
        if (this.config.fitBounds && (layersToAdd.length || layersToRemove.length || otherLayersChanged)) {
            this.applyFitBounds();
        }
    }

    /**
     * Limpa todas as camadas do mapa
     */
    clearLayers() {
        [...this.layers.keys()].forEach((id) => this.removeLayer({ id }));

        this.layerGroups.forEach(({ layer }) => {
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
            Alpine.raw(this.boundarySourceCache).destroy();
            Alpine.raw(this.map).remove();
            this.map = null;
        }
    }
}
