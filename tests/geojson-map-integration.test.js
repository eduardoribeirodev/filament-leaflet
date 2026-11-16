import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';
import { BoundarySourceCache, GeoJsonBoundaryController, createChoroplethScale, forEachFrame, getFeatureLabel, getFeatureValue, selectFocusCountry } from '../resources/js/geojson-boundary.js';

class Events {
    constructor() { this.handlers = new Map(); this.options = {}; }
    on(types, handler) {
        if (typeof types === 'object') {
            Object.entries(types).forEach(([type, callback]) => this.on(type, callback));
        } else {
            types.split(' ').forEach((type) => {
                if (!this.handlers.has(type)) this.handlers.set(type, new Set());
                this.handlers.get(type).add(handler);
            });
        }
        return this;
    }
    off(types, handler) { types.split(' ').forEach((type) => this.handlers.get(type)?.delete(handler)); return this; }
    fire(type, detail = {}) { [...(this.handlers.get(type) || [])].forEach((callback) => callback({ target: this, ...detail })); }
    addTo(parent) { parent.addLayer(this); return this; }
    bindPopup(content) { this.popup = content; }
    bindTooltip(content) { this.tooltip = content; }
}

class Bounds {
    constructor() { this.points = []; }
    extend(value) {
        if (value instanceof Bounds) this.points.push(...value.points);
        else this.points.push(Array.isArray(value) ? value : [value.lat, value.lng]);
        return this;
    }
    isValid() { return this.points.length > 0; }
}

class Marker extends Events {
    constructor(coords) { super(); this.coords = coords; }
    getLatLng() { return { lat: this.coords[0], lng: this.coords[1] }; }
}

class Path extends Marker {
    constructor(coordinates) {
        super([coordinates[0][0][1], coordinates[0][0][0]]);
        this.points = coordinates.flat().map(([lng, lat]) => [lat, lng]);
        this.options = { opacity: 1, fillOpacity: 0.2, interactive: true };
    }
    getBounds() { const bounds = new Bounds(); this.points.forEach((point) => bounds.extend(point)); return bounds; }
    setStyle(style) { Object.assign(this.options, style); this.style = { ...this.style, ...style }; return this; }
}

class Group extends Events {
    constructor() { super(); this.children = new Set(); }
    addLayer(layer) { this.children.add(layer); return this; }
    removeLayer(layer) { this.children.delete(layer); return this; }
    clearLayers() { this.children.clear(); return this; }
    getLayers() { return [...this.children]; }
    getBounds() {
        const bounds = new Bounds();
        this.children.forEach((layer) => bounds.extend(layer.getBounds ? layer.getBounds() : layer.getLatLng()));
        return bounds;
    }
}

class MapStub extends Group {
    constructor(zoom) { super(); this.zoom = zoom; this.fits = []; this.fitZoom = null; }
    getZoom() { return this.zoom; }
    fitBounds(bounds) {
        this.fits.push(bounds.points);
        this.fire('movestart');
        if (this.fitZoom !== null) {
            this.fire('zoomstart');
            this.zoom = this.fitZoom;
            this.fire('zoomend');
        }
        this.fire('moveend');
    }
    remove() { this.handlers.clear(); this.children.clear(); }
}

const L = {
    Marker,
    CircleMarker: class extends Marker {},
    Circle: class extends Marker {},
    Path,
    latLngBounds: () => new Bounds(),
    geoJson(data, options = {}) {
        const group = new Group();
        group.options = options;
        for (const feature of data?.features || []) {
            const [lng, lat] = feature.geometry.coordinates;
            const layer = feature.geometry.type === 'Polygon' ? new Path(feature.geometry.coordinates) : new Marker([lat, lng]);
            layer.feature = feature;
            layer.style = options.style?.(feature);
            Object.assign(layer.options, layer.style);
            options.onEachFeature?.(feature, layer);
            group.addLayer(layer);
        }
        return group;
    },
};

// Run the real map integration without importing browser-only Leaflet CSS/plugins.
const source = await readFile(new URL('../resources/js/leaflet-map-core.js', import.meta.url), 'utf8');
let frameDriver;
const context = vm.createContext({ L, Alpine: { raw: (value) => value }, BoundarySourceCache, GeoJsonBoundaryController, createChoroplethScale,
    forEachFrame: (items, visit, options) => forEachFrame(items, visit, { ...options, ...frameDriver }),
    getFeatureLabel, getFeatureValue, selectFocusCountry, console, setTimeout, clearTimeout });
vm.runInContext(source.slice(source.indexOf('export class LeafletMapCore')).replace('export class ', 'globalThis.LeafletMapCore = class '), context);
const { LeafletMapCore } = context;

const tick = () => new Promise((resolve) => setImmediate(resolve));
const point = (lat, lng) => ({ type: 'FeatureCollection', features: [{ type: 'Feature', properties: {}, geometry: { type: 'Point', coordinates: [lng, lat] } }] });
const boundary = (id, url) => ({ id, type: 'geojson', source: { type: 'url', value: url }, style: {} });

function setup(layers, { fitBounds = true, zoom = 3, fitZoom = null } = {}) {
    const core = new LeafletMapCore({ layersData: layers, fitBounds, zoomConfig: { max: 18 } });
    const map = new MapStub(zoom);
    map.fitZoom = fitZoom;
    core.createMap = () => { core.map = map; };
    core.addTileLayers = core.addLayerGroups = core.setupMapControls = () => {};
    core.createMarker = (data) => new Marker(data.coords);
    return { core, map };
}

test('initial framing waits for markers and every asynchronous boundary', async () => {
    const { core, map } = setup([
        { id: 'marker', type: 'marker', coords: [0, 0] },
        boundary('near', '/near'),
        boundary('far', '/far'),
    ]);
    const requests = new Map();
    core.boundarySourceCache = new BoundarySourceCache((url) => new Promise((resolve) => requests.set(url, resolve)));
    core.init();
    await tick();
    assert.equal(map.fits.length, 0);
    requests.get('/near')({ ok: true, json: () => point(5, 5) });
    await tick();
    assert.equal(map.fits.length, 0);
    requests.get('/far')({ ok: true, json: () => point(60, 120) });
    await tick();
    assert.equal(map.fits.length, 1);
    assert.deepEqual(map.fits[0], [[0, 0], [5, 5], [60, 120]]);
    assert.equal(map.handlers.get('movestart').size, 2, 'Only the two boundary lifecycle handlers should remain.');
    core.destroy();
});

test('real map integration debounces pans, keeps readonly Canvas geometry and cleans move listeners', async () => {
    const data = { id: 'cities', type: 'geojson', style: {}, source: { type: 'collection', value: [
        { type: 'url', value: '/MT', bounds: [-60, -18, -50, -7] },
        { type: 'url', value: '/SP', bounds: [-53, -26, -44, -19] },
    ] } };
    const { core, map } = setup([data], { fitBounds: false, zoom: 11 });
    let viewport = [-58, -16, -54, -12];
    map.getBounds = () => ({ getWest: () => viewport[0], getSouth: () => viewport[1], getEast: () => viewport[2], getNorth: () => viewport[3] });
    const requests = [];
    const canvas = {};
    L.canvas = () => canvas;
    core.boundarySourceCache = new BoundarySourceCache((url) => {
        requests.push(url);
        return { ok: true, json: () => point(-15, -56) };
    });
    core.init();
    await tick();
    assert.deepEqual(requests, ['/MT']);
    const stable = core.layers.get('cities').layer;
    const replacement = [...stable.children][0];
    assert.equal(replacement.options.renderer, canvas);
    assert.equal(replacement.options.pmIgnore, true);
    map.fire('moveend');
    await new Promise((resolve) => setTimeout(resolve, 120));
    assert.equal([...stable.children][0], replacement, 'Panning within the same state must not reconstruct geometry.');
    viewport = [-50, -24, -46, -21];
    map.fire('moveend'); map.fire('moveend');
    await new Promise((resolve) => setTimeout(resolve, 120));
    assert.deepEqual(requests, ['/MT', '/SP']);
    assert.equal(core.layers.get('cities').layer, stable);
    assert.equal(map.fits.length, 0);
    map.fire('moveend');
    core.destroy();
    assert.equal(map.handlers.get('moveend')?.size || 0, 0);
    delete L.canvas;
});

test('initial fit uses metadata to find offscreen states without downloading every file', async () => {
    const data = { ...boundary('cities', '/MT'), source: { type: 'url', value: '/MT', bounds: [-60, -18, -50, -7] } };
    const { core, map } = setup([data], { fitBounds: true, zoom: 11 });
    let viewport = [0, 0, 5, 5];
    map.getBounds = () => ({ getWest: () => viewport[0], getSouth: () => viewport[1], getEast: () => viewport[2], getNorth: () => viewport[3] });
    const requests = [];
    core.boundarySourceCache = new BoundarySourceCache((url) => {
        requests.push(url);
        return { ok: true, json: () => point(-15, -56) };
    });
    map.fitBounds = (bounds) => {
        assert.equal(requests.length, 0, 'Fit must happen from metadata before requesting the state.');
        map.fits.push(bounds.points);
        viewport = [-60, -18, -50, -7];
        map.fire('zoomend');
    };
    core.init();
    await tick(); await tick();
    assert.deepEqual(requests, ['/MT']);
    assert.deepEqual(JSON.parse(JSON.stringify(map.fits[0])), [[-18, -60], [-7, -50]]);
    assert.equal(map.fits.length, 1);
    core.destroy();
});

test('user panning or zooming during initial loading cancels delayed framing', async () => {
    for (const event of ['movestart', 'zoomstart']) {
        const { core, map } = setup([boundary('far', '/far')]);
        let respond;
        core.boundarySourceCache = new BoundarySourceCache(() => new Promise((resolve) => { respond = resolve; }));
        core.init();
        await tick();
        map.fire(event);
        respond({ ok: true, json: () => point(60, 120) });
        await tick();
        assert.equal(map.fits.length, 0);
        assert.equal(core.layers.get('far').layer.getBounds().isValid(), false, 'Geometry must not commit during a movement.');
        map.fire('moveend');
        await new Promise((resolve) => setTimeout(resolve, 120));
        assert.equal(core.layers.get('far').layer.getBounds().isValid(), true);
        core.destroy();
    }
});

test('initial fit may select another level without repeated fitting; same-id updates preserve the layer', async () => {
    const data = {
        id: 'brazil', type: 'geojson', style: { weight: 2 }, popup: { title: 'Original' }, tooltip: { content: 'Area' },
        administrative: {
            dataLevel: 1,
            zoomLevels: [{ minZoom: 0, level: 0 }, { minZoom: 6, level: 1 }],
            levels: {
                0: { source: { type: 'inline', value: point(-14, -50) }, features: {}, visualization: null },
                1: { source: { type: 'inline', value: point(-15, -56) }, features: {}, visualization: null },
            },
        },
    };
    const { core, map } = setup([data], { fitZoom: 6 });
    core.init();
    await tick();
    const stableLayer = core.layers.get('brazil').layer;
    assert.equal(core.boundaryControllers.get('brazil').controller.active.key, 1);
    assert.equal(map.fits.length, 1);
    const updated = { ...data, style: { weight: 9 }, popup: { title: 'Updated' }, tooltip: { content: 'New area' } };
    core.updateMapData({ ...core.config, layersData: [updated] });
    await tick();
    assert.equal(core.layers.get('brazil').layer, stableLayer);
    assert.equal(map.fits.length, 1);
    const replacement = [...stableLayer.children][0];
    assert.match(replacement.popup, /Updated/);
    assert.equal(replacement.tooltip, 'New area');
    assert.equal([...replacement.children][0].style.weight, 9);
    core.removeLayer({ id: 'brazil' });
    assert.equal(core.layers.size, 0);
    assert.equal(core.boundaryControllers.size, 0);
    assert.equal(map.handlers.get('zoomend').size, 0);
    core.destroy();
});

test('viewport reconciliation retains common Leaflet paths and updates presentation when data changes', async () => {
    const a = { ...point(-15, -56).features[0], bbox: [-57, -16, -55, -14] };
    const b = { ...point(-9, -52).features[0], bbox: [-53, -10, -51, -8] };
    const data = { ...boundary('cities', '/cities'), features: { key: 'id' }, style: { weight: 2 },
        source: { type: 'url', value: '/cities', cullFeatures: true }, popup: { title: 'Visits' } };
    const { core, map } = setup([data], { fitBounds: false, zoom: 8 });
    let viewport = [-58, -17, -54, -13];
    map.getBounds = () => ({ getWest: () => viewport[0], getSouth: () => viewport[1], getEast: () => viewport[2], getNorth: () => viewport[3] });
    core.boundarySourceCache = new BoundarySourceCache(() => ({ ok: true, json: () => ({ type: 'FeatureCollection', features: [a, b] }) }));
    core.init(); await tick();
    const stable = core.layers.get('cities').layer;
    const group = stable.getLayers()[0];
    const retained = group.getLayers()[0];
    viewport = [-60, -18, -50, -7];
    map.fire('movestart'); map.fire('moveend');
    await new Promise((resolve) => setTimeout(resolve, 120));
    assert.equal(stable.getLayers()[0], group);
    assert.equal(group.getLayers().length, 2);
    assert.ok(group.getLayers().includes(retained), 'A feature visible in both views must keep the same path.');
    viewport = [-58, -17, -54, -13];
    map.fire('movestart'); map.fire('moveend');
    await new Promise((resolve) => setTimeout(resolve, 120));
    assert.deepEqual(group.getLayers(), [retained]);
    const updated = { ...data, style: { weight: 9 }, popup: { title: 'Updated' } };
    core.updateMapData({ ...core.config, layersData: [updated] }); await tick();
    const replacement = stable.getLayers()[0];
    assert.notEqual(replacement, group);
    assert.match(replacement.popup, /Updated/);
    assert.equal(replacement.getLayers()[0].style.weight, 9);
    core.destroy();
});

test('a state click in an administrative country collection opens only that state in ADM2', async () => {
    const rectangle = ([west, south, east, north]) => ({ type: 'Polygon', coordinates: [[[west, south], [east, south], [east, north], [west, north], [west, south]]] });
    const meta = [
        { code: 'MT', key: 'BR-MT', featureId: 'parent-MT', bounds: [-60, -20, -50, -10], cityCount: 141, citySpan: 0.0024 },
        { code: 'SP', key: 'BR-SP', featureId: 'parent-SP', bounds: [-49, -20, -44, -15], cityCount: 635, citySpan: 0.000001 },
        { code: 'GO', key: 'BR-GO', featureId: 'parent-GO', bounds: [-52, -19, -48, -13], cityCount: 246, citySpan: 0.000001 },
    ].map((entry) => ({ ...entry, available: true, geometry: rectangle(entry.bounds) }));
    const parent = (code, coords) => ({ ...point(...coords).features[0], properties: { shapeID: `parent-${code}`, shapeISO: `BR-${code}`, shapeName: code } });
    const city = (code, coords) => ({ ...point(...coords).features[0], properties: { shapeID: `city-${code}`, shapeName: `City ${code}` } });
    const parents = [parent('MT', [-15, -55]), parent('SP', [-17, -46]), parent('GO', [-16, -50])];
    const cities = { '/MT': city('MT', [-15, -55]), '/SP': city('SP', [-17, -46]) };
    const data = { id: 'mixed', type: 'geojson', style: {}, administrative: {
        dataLevel: 2, auto: { contextual: true, countryBounds: [-70, -35, -30, 10], states: meta },
        levels: {
            0: { source: { type: 'inline', value: point(-15, -55) }, features: {} },
            1: { source: { type: 'url', value: '/states' }, features: { key: 'shapeISO', label: 'shapeName' }, visualization: { type: 'choropleth', values: { 'BR-MT': 45, 'BR-SP': 120 }, colors: ['state-low', 'state-high'] } },
            2: { source: { type: 'collection', value: meta.map((entry) => ({ type: 'url', value: `/${entry.code}`, state: entry.code })) }, features: { key: 'shapeID', label: 'shapeName' }, visualization: { type: 'choropleth', values: { 'city-MT': 45, 'city-SP': 120 }, colors: ['city-low', 'city-high'] } },
        },
    } };
    data.administrative.auto.countryCode = 'BRA';
    data.administrative = { countries: [{ administrative: data.administrative }] };
    let viewport = [-61, -21, -43, -7], center = [-55, -15], hover;
    const { core, map } = setup([data], { fitBounds: false, zoom: 7 });
    map.getBounds = () => ({ getWest: () => viewport[0], getSouth: () => viewport[1], getEast: () => viewport[2], getNorth: () => viewport[3] });
    map.getSize = () => ({ x: 1000, y: 600 });
    map.getCenter = () => ({ lng: center[0], lat: center[1] });
    core.setupInfoControl = () => { core.info = { update: (value) => { hover = value; } }; };
    const requests = [];
    core.boundarySourceCache = new BoundarySourceCache((url) => { requests.push(url); return { ok: true, json: () => ({ type: 'FeatureCollection', features: url === '/states' ? parents : [cities[url]] }) }; });
    core.init(); await tick();
    const root = core.layers.get('mixed').layer;
    const paths = () => root.getLayers()[0].getLayers();
    const stateSP = paths().find((layer) => layer.feature.properties.shapeID === 'parent-SP');
    const cityMT = paths().find((layer) => layer.feature.properties.shapeID === 'city-MT');
    const stateGO = paths().find((layer) => layer.feature.properties.shapeID === 'parent-GO');
    assert.equal(stateSP.style.fillColor, 'state-high');
    assert.equal(cityMT.style.fillColor, 'city-low');
    stateSP.fire('mouseover'); assert.equal(hover.value, 120);
    cityMT.fire('mouseover'); assert.equal(hover.value, 45);
    assert.deepEqual(requests, ['/states', '/MT']);
    stateSP.getBounds = () => new Bounds().extend([-20, -49]).extend([-15, -44]);
    map.fitBounds = () => {
        map.fire('movestart'); viewport = [-52.5, -21.5, -40.5, -13.5]; center = [-46.5, -17.5]; map.fire('moveend');
    };
    stateSP.fire('click');
    await new Promise((resolve) => setTimeout(resolve, 120));
    assert.equal(core.boundaryControllers.get('mixed').controller.active.autoDecision.countries[0].focusState, 'SP');
    assert.equal(core.boundaryControllers.get('mixed').controller.active.autoDecision.overrideActive, true);
    assert.deepEqual(paths().map((layer) => layer.feature.properties.shapeID).sort(), ['city-SP', 'parent-GO', 'parent-MT']);
    assert.equal(paths().find((layer) => layer.feature.properties.shapeID === 'parent-GO'), stateGO,
        'A neighboring state keeps its Leaflet path when focus changes between states.');
    assert.deepEqual(requests, ['/states', '/MT', '/SP']);
    core.destroy();
});

test('one administrative layer renders several countries with a shared scale and keeps its identity on updates', async () => {
    const features = ['BR-MT', 'US-CA'].map((id, index) => ({ ...point(index, index).features[0], properties: { shapeID: id, shapeName: id } }));
    const makeData = (totals) => ({ id: 'visits', type: 'geojson', style: {}, administrative: {
        countries: features.map((feature, index) => ({ administrative: {
            minLevel: 0, dataLevel: 1, zoomLevels: [{ minZoom: 0, level: 1 }], levels: {
                1: { source: { type: 'url', value: `/${index}` }, features: { key: 'shapeID', label: 'shapeName' },
                    visualization: { type: 'choropleth', values: { [feature.properties.shapeID]: totals[index] },
                        domain: [Math.min(...totals), Math.max(...totals)], colors: ['low', 'high'] } },
            },
        } })),
    } });
    const data = makeData([20, 80]);
    const { core } = setup([data], { fitBounds: false });
    const requests = [];
    core.boundarySourceCache = new BoundarySourceCache((url) => {
        requests.push(url);
        return { ok: true, json: () => ({ type: 'FeatureCollection', features: [features[Number(url.slice(1))]] }) };
    });
    core.init(); await tick();
    const root = core.layers.get('visits').layer;
    assert.equal(core.boundaryControllers.size, 1);
    assert.deepEqual(root.getLayers()[0].getLayers().map((path) => path.style.fillColor), ['low', 'high']);
    core.updateMapData({ ...core.config, layersData: [makeData([100, 80])] }); await tick();
    assert.equal(core.layers.get('visits').layer, root);
    assert.deepEqual(root.getLayers()[0].getLayers().map((path) => path.style.fillColor), ['high', 'low']);
    assert.deepEqual(requests, ['/0', '/1']);
    core.destroy();
});

test('changing administrative level retains paths of unchanged countries in both directions', async () => {
    const states = point(-15, -55), cities = point(-15.5, -55.5), neighbor = point(-30, -60);
    const data = { id: 'world', type: 'geojson', style: {}, administrative: { countries: [
        { administrative: { zoomLevels: [{ minZoom: 0, level: 1 }, { minZoom: 8, level: 2 }], levels: {
            1: { source: { type: 'inline', value: states }, features: {} },
            2: { source: { type: 'inline', value: cities }, features: {} },
        } } },
        { administrative: { zoomLevels: [{ minZoom: 0, level: 0 }], levels: {
            0: { source: { type: 'inline', value: neighbor }, features: {} },
        } } },
    ] } };
    const { core, map } = setup([data], { fitBounds: false, zoom: 7 });
    core.init(); await tick();
    const group = core.layers.get('world').layer.getLayers()[0];
    const retained = group.getLayers().find((layer) => layer.feature === neighbor.features[0]);
    for (const zoom of [8, 7, 8]) {
        map.fire('zoomstart'); map.zoom = zoom; map.fire('zoomend'); await tick();
        assert.equal(core.layers.get('world').layer.getLayers()[0], group);
        assert.equal(group.getLayers().find((layer) => layer.feature === neighbor.features[0]), retained);
        assert.equal(group.getLayers().length, 2);
        assert.ok(group.getLayers().some((layer) => layer.feature === (zoom === 8 ? cities : states).features[0]));
    }
    core.destroy();
});

test('hover labels follow the map locale dictionary without changing features or reconstructing paths', async () => {
    const feature = { ...point(-15, -55).features[0], properties: { shapeID: 'country', shapeName: 'Brazil' } };
    const data = { id: 'translated', type: 'geojson', style: {}, features: { key: 'shapeID', label: 'shapeName' },
        source: { type: 'inline', value: { type: 'FeatureCollection', features: [feature] } } };
    const { core } = setup([data], { fitBounds: false });
    let hover;
    core.config.regionNames = { country: 'Brasil' };
    core.setupInfoControl = () => { core.info = { update: (value) => { hover = value; } }; };
    try {
        core.init(); await tick();
        const paths = () => core.layers.get('translated').layer.getLayers()[0].getLayers();
        const retained = paths()[0];
        retained.fire('mouseover'); assert.equal(hover.label, 'Brasil');
        core.updateMapData({ ...core.config, regionNames: { country: 'Brésil' } }); await tick();
        assert.equal(paths()[0], retained);
        retained.fire('mouseover'); assert.equal(hover.label, 'Brésil');
        core.updateMapData({ ...core.config, regionNames: {} }); await tick();
        assert.equal(paths()[0], retained);
        retained.fire('mouseover'); assert.equal(hover.label, 'Brazil');
        assert.equal(feature.properties.shapeName, 'Brazil');
    } finally { core.destroy(); }
});

test('Canvas replacements are prepared across frames, stay hidden and roll back immediately on navigation', async () => {
    const frames = new Map();
    let frameId = 0;
    frameDriver = { maxItems: 2, now: () => 0,
        schedule: (callback) => { frames.set(++frameId, callback); return frameId; },
        cancel: (id) => frames.delete(id) };
    L.canvas = () => ({});
    const polygon = (id) => ({ type: 'Feature', properties: { id }, geometry: { type: 'Polygon',
        coordinates: [[[0, 0], [1, 0], [1, 1], [0, 0]]] } });
    const initial = { type: 'FeatureCollection', features: [polygon('parent')] };
    const detailed = { type: 'FeatureCollection', features: Array.from({ length: 7 }, (_, i) => polygon(`city-${i}`)) };
    const data = { id: 'batched', type: 'geojson', style: { fillOpacity: 0.7, opacity: 0.8 }, administrative: {
        zoomLevels: [{ minZoom: 0, level: 1 }, { minZoom: 8, level: 2 }], levels: {
            1: { source: { type: 'inline', value: initial }, features: {} },
            2: { source: { type: 'inline', value: detailed }, features: {} },
        },
    } };
    const { core, map } = setup([data], { fitBounds: false, zoom: 7 });
    try {
        core.init(); await tick();
        const root = core.layers.get('batched').layer, group = root.getLayers()[0];
        const parent = group.getLayers()[0];
        assert.equal(parent.options.fillOpacity, 0.7);
        map.fire('zoomstart'); map.zoom = 8; map.fire('zoomend'); await tick();
        assert.equal(frames.size, 1);
        assert.ok(group.getLayers().includes(parent), 'The old drawing stays visible during preparation.');
        assert.equal(group.getLayers().length, 3);
        for (const path of group.getLayers().filter((layer) => layer !== parent)) {
            assert.equal(path.options.opacity, 0); assert.equal(path.options.fillOpacity, 0);
            assert.equal(path.options.interactive, false);
        }
        map.fire('zoomstart');
        assert.equal(frames.size, 0, 'Navigation cancels the next preparation frame.');
        assert.deepEqual(group.getLayers(), [parent], 'Staged paths are removed before Leaflet animates the next zoom.');
        map.zoom = 7; map.fire('zoomend'); await tick();
        assert.equal(core.boundaryControllers.get('batched').controller.active.key, 1);

        map.fire('zoomstart'); map.zoom = 8; map.fire('zoomend'); await tick();
        while (frames.size) {
            const [id, callback] = [...frames][0]; frames.delete(id); callback(); await tick();
        }
        const paths = group.getLayers();
        assert.equal(paths.length, 7);
        assert.ok(!paths.includes(parent));
        for (const path of paths) {
            assert.equal(path.options.opacity, 0.8); assert.equal(path.options.fillOpacity, 0.7);
            assert.equal(path.options.interactive, true);
        }
        assert.equal(core.boundaryControllers.get('batched').controller.active.key, 2);

        const controller = core.boundaryControllers.get('batched').controller;
        const errors = [];
        controller.onError = (error) => errors.push(error);
        const broken = { ...polygon('broken'), geometry: { get coordinates() { throw new Error('Invalid replacement'); } } };
        const invalid = { ...data, administrative: { ...data.administrative, levels: { ...data.administrative.levels,
            2: { ...data.administrative.levels[2], source: { type: 'inline', value: {
                type: 'FeatureCollection', features: [polygon('new-1'), polygon('new-2'), broken],
            } } },
        } } };
        const failed = controller.update(invalid); await tick();
        assert.equal(group.getLayers().length, 9);
        const [id, callback] = [...frames][0]; frames.delete(id); callback();
        assert.equal(await failed, false);
        assert.equal(errors.length, 1);
        assert.deepEqual(group.getLayers(), paths, 'A failed batch preserves every previously visible path.');

        // An interrupted replacement on destruction must not leave frames running.
        core.updateMapData({ ...core.config, layersData: [{ ...data, style: { fillOpacity: 0.9 } }] }); await tick();
        assert.equal(frames.size, 1);
        core.destroy();
        assert.equal(frames.size, 0);
    } finally {
        core.destroy();
        frameDriver = undefined;
        delete L.canvas;
    }
});
