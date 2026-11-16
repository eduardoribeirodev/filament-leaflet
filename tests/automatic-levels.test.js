import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { selectAutoBoundaryLevel, selectFocusCountry, resolveBoundaryData, GeoJsonBoundaryController, BoundarySourceCache } from '../resources/js/geojson-boundary.js';

const rectangle = ([west, south, east, north]) => ({ type: 'Polygon', coordinates: [[[west, south], [east, south], [east, north], [west, north], [west, south]]] });
const state = (code, bounds, cityCount, citySpan) => ({ code, bounds, geometry: rectangle(bounds), cityCount, citySpan });
const metadata = () => ({
    levels: { 0: {}, 1: {}, 2: {} },
    auto: { countryBounds: [-70, -35, -30, 10], states: [
        state('MT', [-60, -20, -50, -10], 141, 0.0024),
        state('SP', [-49, -20, -44, -15], 635, 0.00062),
    ] },
});
const view = (center, bounds) => ({ center, bounds, size: [1000, 600] });

test('only the focused country expands, keeping the previous focus at shared borders', () => {
    const layers = ['BR', 'NEIGHBOR'].map((countryCode, index) => ({ administrative: {
        levels: { 0: {}, 1: {}, 2: {} },
        auto: { contextual: true, countryCode, countryBounds: [-60, -20, -40, -10],
            states: [state(countryCode, [-60 + index * 10, -20, -50 + index * 10, -10], 100, 0.002)] },
    } }));
    assert.equal(selectFocusCountry(layers, [-55, -15]), 'BR');
    assert.equal(selectFocusCountry(layers, [-50, -15], 'NEIGHBOR'), 'NEIGHBOR');
    assert.equal(selectFocusCountry(layers, [0, 0], 'BR'), null);
    const focused = { ...view([-50, -15], [-55, -18, -45, -12]), focusCountry: 'NEIGHBOR' };
    assert.equal(selectAutoBoundaryLevel(layers[0].administrative, focused, { level: 2, focusState: 'BR' }).level, 0);
    assert.equal(selectAutoBoundaryLevel(layers[1].administrative, focused).level, 2);
});

test('click focus survives fitting narrow regions even when their area coverage is low', () => {
    const focused = view([0.5, 5], [0, 0, 20, 10]);
    const administrative = { levels: { 0: {}, 1: {}, 2: {} }, auto: {
        contextual: true, countryBounds: [0, 0, 1, 10],
        states: [state('NARROW', [0, 0, 1, 10], 1000, 0.00001)],
    } };
    assert.equal(selectAutoBoundaryLevel(administrative, focused).level, 0);
    assert.equal(selectAutoBoundaryLevel(administrative, focused, null, { country: true }).level, 1);
    assert.equal(selectAutoBoundaryLevel(administrative, focused, null, { state: 'NARROW' }).level, 2);
    const distant = view([1, 5], [-100, -60, 100, 60]);
    assert.equal(selectAutoBoundaryLevel(administrative, distant, null, { country: true }).overrideActive, false);
    assert.equal(selectAutoBoundaryLevel(administrative, distant, null, { state: 'NARROW' }).overrideActive, false);
});

const bundledIndex = JSON.parse(readFileSync(new URL('../dist/geojson/locations.json', import.meta.url)));
const bundledDisplays = JSON.parse(readFileSync(new URL('../dist/geojson/geometry.json', import.meta.url)));
const realCountry = (countryCode) => ({ administrative: { levels: { 0: {}, 1: {}, 2: {} }, auto: {
    contextual: true, countryCode, countryBounds: bundledDisplays[`${countryCode}/adm1.geojson`].bounds,
    states: Object.entries(bundledIndex[countryCode]).map(([code, entry]) => ({ code,
        bounds: entry.focusBounds ?? entry.bounds, geometry: entry.focusGeometry, cityCount: entry.cities.length, citySpan: entry.citySpan })),
} } });
const zoomView = (center, zoom) => {
    const scale = 256 * 2 ** zoom;
    const cy = Math.log(Math.tan(Math.PI / 4 + center[1] * Math.PI / 360)) / (2 * Math.PI);
    const latitude = (y) => Math.atan(Math.sinh(y * 2 * Math.PI)) * 180 / Math.PI;
    return view(center, [center[0] - 500 / scale * 360, latitude(cy - 300 / scale), center[0] + 500 / scale * 360, latitude(cy + 300 / scale)]);
};

test('US mainland, Alaska and island separation cannot keep ADM1 at world scale', () => {
    const country = realCountry('USA');
    const detailed = zoomView([-98, 38], 4);
    assert.ok(selectAutoBoundaryLevel(country.administrative, detailed).level >= 1);
    const distant = zoomView([-98, 38], 2);
    for (const previous of [null, { level: 1 }, { level: 2, focusState: 'KS' }]) {
        assert.equal(selectAutoBoundaryLevel(country.administrative, distant, previous).level, 0);
        const clicked = selectAutoBoundaryLevel(country.administrative, distant, previous, { country: true });
        assert.equal(clicked.level, 0);
        assert.equal(clicked.overrideActive, false);
    }
});

test('distant US territories do not advance the continental ADM1 transition', () => {
    const complete = realCountry('USA').administrative;
    const mainland = { ...complete, auto: { ...complete.auto,
        states: complete.auto.states.filter((state) => !['AK', 'HI', 'AS', 'GU', 'MP', 'PR', 'VI'].includes(state.code)) } };
    assert.equal(selectAutoBoundaryLevel(complete, zoomView([-98, 38], 3)).level, 0);
    for (const zoom of [2, 3, 4, 5]) for (const previous of [null, { level: 1 }]) {
        const current = zoomView([-98, 38], zoom);
        assert.equal(selectAutoBoundaryLevel(complete, current, previous).level,
            selectAutoBoundaryLevel(mainland, current, previous).level,
            `Alaska and islands must not change continental detail at zoom ${zoom}`);
    }
    const alaska = { ...complete, auto: { ...complete.auto,
        states: complete.auto.states.filter((state) => state.code === 'AK') } };
    for (const zoom of [2, 3, 4]) {
        const current = zoomView([-150, 64], zoom);
        assert.equal(selectAutoBoundaryLevel(complete, current).level, selectAutoBoundaryLevel(alaska, current).level,
            'Alaska must use its own region when it is in focus');
    }
    const hawaii = zoomView([-157.85, 21.3], 5);
    assert.equal(selectAutoBoundaryLevel(complete, hawaii, null, { state: 'AK' }).level,
        selectAutoBoundaryLevel(complete, hawaii).level,
        'A released Alaska click must not influence the new region');
});

test('incomplete state metadata cannot claim every map location for its country', () => {
    const invalid = { administrative: { auto: { contextual: true, countryCode: 'USA',
        states: [{ code: 'UNKNOWN', bounds: null, geometry: null }] } } };
    assert.equal(selectFocusCountry([invalid], [7, 55.5], 'USA', zoomView([7, 55.5], 6)), null);
});

test('each bundled country returns to ADM0 when undoing the zoom that opened ADM1', () => {
    for (const [code, center] of [['USA', [-98, 38]], ['BRA', [-55, -15]], ['DEU', [10, 51]],
        ['FRA', [2, 47]], ['ESP', [-4, 40]], ['ITA', [12, 43]], ['PRT', [-8, 39]]]) {
        const administrative = realCountry(code).administrative;
        delete administrative.levels[2];
        let previous = { level: 0 };
        for (let zoom = 1; zoom <= 10; zoom++) {
            const decision = selectAutoBoundaryLevel(administrative, zoomView(center, zoom), previous);
            if (decision.level === 1) {
                assert.equal(selectAutoBoundaryLevel(administrative, zoomView(center, zoom - 1), decision).level, 0, code);
                break;
            }
            previous = decision;
            assert.ok(zoom < 10, `${code} must reach ADM1`);
        }
    }
});

test('zooming out releases country click focus before resolving the automatic level', async () => {
    const data = realCountry('USA');
    delete data.administrative.levels[2];
    for (const level of [0, 1]) data.administrative.levels[level].source = { type: 'inline', value: { type: 'FeatureCollection', features: [] } };
    let zoom = 4;
    const controller = new GeoJsonBoundaryController({ data, getZoom: () => zoom,
        getBounds: () => zoomView([-98, 38], zoom).bounds, getView: () => zoomView([-98, 38], zoom),
        cache: { load: async () => ({ type: 'FeatureCollection', features: [] }) }, commit: () => {},
    });
    controller.focusCountry();
    await controller.request('focus');
    assert.equal(controller.active.autoDecision.level, 1);
    zoom = 3;
    await controller.request('zoom');
    assert.equal(controller.focusOverride, null);
    assert.equal(controller.active.autoDecision.level, 0);
});

test('undoing the zoom that opened municipalities returns directly to ADM1', () => {
    for (const [code, center] of [['BRA', [-46.63, -23.55]], ['BRA', [-56.1, -15.6]],
        ['USA', [-98, 38]], ['DEU', [13.4, 52.5]], ['FRA', [2.35, 48.85]],
        ['ESP', [-3.7, 40.42]], ['ITA', [12.5, 41.9]], ['PRT', [-9.14, 38.72]]]) {
        const administrative = realCountry(code).administrative;
        let previous = { level: 0 };
        for (let zoom = 2; zoom <= 16; zoom++) {
            const decision = selectAutoBoundaryLevel(administrative, zoomView(center, zoom), previous);
            if (decision.level === 2) {
                assert.equal(selectAutoBoundaryLevel(administrative, zoomView(center, zoom - 1), decision).level, 1, `${code} ${center}`);
                break;
            }
            previous = decision;
            assert.ok(zoom < 16, `${code} must reach ADM2`);
        }
    }
});

test('state click focus is released on the first zoom reduction', async () => {
    const data = contextualFixture();
    data.administrative.auto.states[1].citySpan = 0.000001;
    let zoom = 6;
    const current = view([-46.5, -17.5], [-52.5, -21.5, -40.5, -13.5]);
    const controller = new GeoJsonBoundaryController({ data, getZoom: () => zoom,
        getBounds: () => current.bounds, getView: () => current,
        cache: { load: async () => ({ type: 'FeatureCollection', features: [] }) }, commit: () => {},
    });
    controller.focusState('SP');
    await controller.request('focus');
    assert.equal(controller.active.autoDecision.level, 2);
    zoom = 5;
    await controller.request('zoom');
    assert.equal(controller.focusOverride, null);
    assert.equal(controller.active.autoDecision.level, 1);
});

test('coastal country views expand without requiring land at the exact center', () => {
    const layers = ['USA', 'FRA', 'DEU', 'PRT'].map(realCountry);
    for (const [countryCode, center, zoom] of [['DEU', [7, 55.5], 6], ['PRT', [-10, 39], 7], ['FRA', [5, 42], 6]]) {
        const current = zoomView(center, zoom);
        current.focusCountry = selectFocusCountry(layers, center, 'USA', current);
        assert.equal(current.focusCountry, countryCode);
        const country = layers.find((entry) => entry.administrative.auto.countryCode === countryCode);
        assert.ok(selectAutoBoundaryLevel(country.administrative, current).level >= 1, countryCode);
        assert.equal(selectAutoBoundaryLevel(layers[0].administrative, current, { level: 1 }).level, 0);
    }
});

test('automatic levels depend on screen coverage and density, not one global zoom threshold', () => {
    const data = metadata();
    assert.equal(selectAutoBoundaryLevel(data, view([-55, -15], [-180, -70, 180, 70])).level, 0);
    assert.equal(selectAutoBoundaryLevel(data, view([-55, -15], [-65, -21, -45, -9])).level, 2, 'Municipal detail now enters a zoom step earlier.');
    assert.equal(selectAutoBoundaryLevel(data, view([-55, -15], [-61, -19, -49, -11])).level, 2);
    assert.equal(selectAutoBoundaryLevel(data, view([-46.5, -17.5], [-52.5, -21.5, -40.5, -13.5])).level, 2);
    assert.equal(selectAutoBoundaryLevel(data, view([-46.5, -17.5], [-48.25, -18.7, -44.75, -16.3])).level, 2);
    assert.equal(selectAutoBoundaryLevel(data, view([0, 0], [-5, -5, 5, 5])).level, 0);
});

test('country and municipality transitions use symmetric entering and leaving limits', () => {
    const data = { levels: { 0: {}, 1: {} }, auto: { countryBounds: [0, 0, 10, 10], states: [] } };
    const middle = view([5, 5], [0, 0, 30, 20]);
    assert.equal(selectAutoBoundaryLevel(data, middle).level, 0);
    assert.equal(selectAutoBoundaryLevel(data, middle, { level: 1, focusState: null }).level, 0);
    assert.equal(selectAutoBoundaryLevel(data, view([5, 5], [0, 0, 50, 30]), { level: 1 }).level, 0);

    const cities = metadata(); cities.auto.states[0].citySpan = 0.000175;
    const municipalMiddle = view([-55, -15], [-57, -17, -53, -13]);
    assert.equal(selectAutoBoundaryLevel(cities, municipalMiddle).level, 1);
    assert.equal(selectAutoBoundaryLevel(cities, municipalMiddle, { level: 2, focusState: 'MT' }).level, 1);
    assert.equal(selectAutoBoundaryLevel(cities, municipalMiddle, { level: 2, focusState: 'SP' }).level, 1);
});

test('focus uses state geometry, including holes, border stability and wrapped longitudes', () => {
    const data = metadata();
    const hole = rectangle([-57, -17, -53, -13]).coordinates[0];
    data.auto.states[0].geometry.coordinates.push(hole);
    assert.equal(selectAutoBoundaryLevel(data, view([-55, -15], [-61, -19, -49, -11])).focusState, null);
    data.auto.states[0].geometry.coordinates.pop();
    assert.equal(selectAutoBoundaryLevel(data, view([305, -15], [299, -19, 311, -11])).focusState, 'MT');
    data.auto.states.push(state('NEIGHBOR', [-50, -20, -40, -10], 50, 0.002));
    assert.equal(selectAutoBoundaryLevel(data, view([-50, -15], [-52, -17, -48, -13]), { level: 2, focusState: 'NEIGHBOR' }).focusState, 'NEIGHBOR');
    data.auto.states[0].cityCount = 0;
    assert.equal(selectAutoBoundaryLevel(data, view([-55, -15], [-61, -19, -49, -11])).level, 1);
});

test('Auto is capped by available data levels and resolves before choosing geometry quality', () => {
    const administrative = metadata();
    for (const level of [0, 1, 2]) administrative.levels[level] = { source: { type: 'url', value: `/adm${level}`, simplified: `/adm${level}.display`, detailZoom: 12 } };
    const data = { administrative };
    const focused = view([-55, -15], [-57, -17, -53, -13]);
    const low = resolveBoundaryData(data, 8, focused.bounds, focused);
    const high = resolveBoundaryData(data, 12, focused.bounds, focused);
    assert.equal(low.autoDecision.level, 2);
    assert.equal(low.data.source.value, '/adm2');
    assert.equal(high.autoDecision.level, 2);
    assert.equal(high.data.source.value, '/adm2');
    delete administrative.levels[2];
    assert.equal(resolveBoundaryData(data, 15, focused.bounds, focused).autoDecision.level, 1);
    delete administrative.levels[1];
    assert.equal(resolveBoundaryData(data, 15, focused.bounds, focused).autoDecision.level, 0);
});

test('Auto state is committed only for the latest settled view and cached sources are reused', async () => {
    const administrative = metadata();
    for (const level of [0, 1, 2]) administrative.levels[level] = { source: { type: 'url', value: `/adm${level}` } };
    let current = view([-55, -15], [-180, -70, 180, 70]);
    const requests = [], commits = [];
    const cache = new BoundarySourceCache((url) => {
        requests.push(url);
        return { ok: true, json: () => ({ type: 'FeatureCollection', features: [] }) };
    });
    const controller = new GeoJsonBoundaryController({ data: { administrative }, cache, getZoom: () => 8,
        getBounds: () => current.bounds, getView: () => current,
        commit: () => commits.push(current),
    });
    await controller.request();
    assert.equal(controller.active.autoDecision.level, 0);
    controller.suspend();
    current = view([-55, -15], [-61, -19, -49, -11]);
    await controller.request('zoom');
    assert.equal(commits.length, 1);
    await controller.resume('zoom');
    assert.equal(controller.active.autoDecision.level, 2);
    current = view([-55, -15], [-180, -70, 180, 70]);
    await controller.request('move');
    assert.deepEqual(requests, ['/adm0', '/adm2']);
    assert.equal(controller.active.autoDecision.level, 0);
});

function contextualFixture() {
    const administrative = metadata();
    administrative.auto.contextual = true;
    for (const entry of administrative.auto.states) {
        entry.key = `BR-${entry.code}`;
        entry.featureId = `parent-${entry.code}`;
        entry.available = true;
    }
    administrative.levels = {
        0: { source: { type: 'url', value: '/country' }, features: { key: 'shapeGroup' }, visualization: { values: { BRA: 165 } } },
        1: { source: { type: 'url', value: '/states' }, features: { key: 'shapeISO' }, visualization: { values: { 'BR-MT': 45, 'BR-SP': 120 } } },
        2: { source: { type: 'collection', value: administrative.auto.states.map((entry) => ({ type: 'url', value: `/${entry.code}`, state: entry.code, bounds: entry.bounds })) },
            features: { key: 'shapeID' }, visualization: { values: { 'city-MT': 45, 'city-SP': 120 } } },
    };
    return { id: 'brazil', administrative };
}

test('contextual Auto leaves countries outside focus at ADM0 and preserves manual modes', () => {
    const data = contextualFixture();
    const focused = view([-55, -15], [-61, -19, -49, -11]);
    assert.equal(selectAutoBoundaryLevel(data.administrative, focused).level, 2);
    data.administrative.auto.states = [data.administrative.auto.states[1]];
    assert.equal(selectAutoBoundaryLevel(data.administrative, focused).level, 0, 'A visible bounding box alone must not expand an unfocused country.');
    delete data.administrative.auto;
    data.administrative.zoomLevels = [{ minZoom: 0, level: 2 }];
    assert.equal(resolveBoundaryData(data, 9, focused.bounds, focused).parts, undefined, 'Manual level maps remain homogeneous.');
});

test('only the focus state loads ADM2; neighbor ADM1 values remain distinct without parent overlap', async () => {
    const data = contextualFixture();
    const parents = data.administrative.auto.states.map((entry) => ({ type: 'Feature', properties: { shapeID: entry.featureId, shapeISO: entry.key }, geometry: rectangle(entry.bounds) }));
    const cities = Object.fromEntries(['MT', 'SP'].map((code) => [code, { type: 'Feature', properties: { shapeID: `city-${code}` }, geometry: rectangle(data.administrative.auto.states.find((entry) => entry.code === code).bounds) }]));
    const requests = [], commits = [];
    const cache = new BoundarySourceCache((url) => {
        requests.push(url);
        return { ok: true, json: () => ({ type: 'FeatureCollection', features: url === '/states' ? parents : [cities[url.slice(1)]] }) };
    });
    let current = view([-55, -15], [-61, -21, -43, -7]);
    const controller = new GeoJsonBoundaryController({ data, cache, getZoom: () => 8, getBounds: () => current.bounds, getView: () => current,
        commit: (geometry, boundary) => commits.push({ geometry, boundary }),
    });
    await controller.request();
    assert.deepEqual(requests, ['/states', '/MT']);
    const first = commits[0];
    assert.deepEqual(first.geometry.features.map((f) => f.properties.shapeID), ['parent-SP', 'city-MT']);
    assert.equal(first.boundary.featureContexts.get(parents[1]).displayLevel, 1);
    assert.equal(first.boundary.featureContexts.get(cities.MT).displayLevel, 2);
    assert.equal(first.boundary.featureContexts.get(parents[1]).visualization.values['BR-SP'], 120);
    assert.equal(first.boundary.featureContexts.get(cities.MT).visualization.values['city-MT'], 45);
    assert.equal((await cache.load({ type: 'url', value: '/states' })).features.length, 2, 'Excluding the focus must not mutate cached ADM1 geometry.');
    current = view([-46.5, -17.5], [-48.25, -18.7, -44.75, -16.3]);
    await controller.request('move');
    assert.deepEqual(requests, ['/states', '/MT', '/SP']);
    assert.deepEqual(commits[1].geometry.features.map((f) => f.properties.shapeID), ['parent-MT', 'city-SP']);
    current = view([-55, -15], [-61, -21, -43, -7]);
    await controller.request('move');
    assert.equal(requests.length, 3);
});

test('state click overrides density until focus is left or the map is sufficiently zoomed out', async () => {
    const data = contextualFixture();
    data.administrative.auto.states[1].citySpan = 0.000001;
    let current = view([-46.5, -17.5], [-52.5, -21.5, -40.5, -13.5]);
    const controller = new GeoJsonBoundaryController({ data, cache: { load: async () => ({ type: 'FeatureCollection', features: [] }) },
        getZoom: () => 6, getBounds: () => current.bounds, getView: () => current, commit: () => {},
    });
    await controller.request();
    assert.equal(controller.active.autoDecision.level, 1);
    assert.equal(controller.focusState('unknown'), false);
    assert.equal(controller.focusState('SP'), true);
    await controller.request('focus');
    assert.equal(controller.active.autoDecision.level, 2);
    assert.equal(controller.active.autoDecision.overrideActive, true);
    current = view([-55, -15], [-61, -19, -49, -11]);
    await controller.request('move');
    assert.equal(controller.focusOverride, null);
    assert.equal(controller.active.autoDecision.focusState, 'MT');
    controller.focusState('MT');
    current = view([-55, -15], [-180, -70, 180, 70]);
    await controller.request('zoom');
    assert.equal(controller.active.autoDecision.level, 0);
    assert.equal(controller.focusOverride, null);
});

test('explicit scopes keep their minimum level when zooming out', () => {
    const scoped = { ...metadata(), minLevel: 1 };
    delete scoped.levels[0];
    const distant = view([0, 0], [-180, -80, 180, 80]);
    assert.equal(selectAutoBoundaryLevel(scoped, distant).level, 1);
    assert.equal(selectAutoBoundaryLevel(scoped, null).level, 1);
    const city = { levels: { 2: {} }, minLevel: 2, auto: scoped.auto };
    assert.equal(selectAutoBoundaryLevel(city, distant).level, 2);
    const selected = resolveBoundaryData({ administrative: { ...city,
        levels: { 2: { source: { type: 'inline', value: { type: 'FeatureCollection', features: [] } } } } } }, 0, distant.bounds, distant);
    assert.equal(selected.data.displayLevel, 2);
    assert.equal(selected.parts, undefined);
});

test('one administrative layer chooses a focus country and preserves per-country values', () => {
    const countries = ['BRA', 'USA'].map((countryCode, index) => {
        const bounds = index ? [-120, 30, -110, 40] : [-60, -20, -50, -10];
        const levels = {};
        for (const level of [0, 1]) levels[level] = {
            source: { type: 'inline', value: { type: 'FeatureCollection', features: [] } },
            features: { key: 'shapeID' }, visualization: { values: { [countryCode]: 10 + index } },
        };
        return { administrative: { levels, minLevel: 0, auto: { contextual: true, countryCode,
            countryBounds: bounds, states: [state('MT', bounds, 10, 0.002)] } } };
    });
    const data = { id: 'visits', style: { weight: 2 }, administrative: { countries } };
    const current = { ...view([-55, -15], [-61, -21, -49, -9]), focusCountry: 'BRA' };
    assert.equal(selectFocusCountry([data], current.center), 'BRA');
    const resolved = resolveBoundaryData(data, 6, current.bounds, current);
    assert.deepEqual(resolved.parts.map((part) => part.displayLevel), [1, 0]);
    assert.equal(resolved.parts[0].visualization.values.BRA, 10);
    assert.equal(resolved.parts[1].visualization.values.USA, 11);
    assert.ok(resolved.parts.every((part) => part.id === 'visits' && part.style.weight === 2));
    const controller = new GeoJsonBoundaryController({ data, cache: new BoundarySourceCache(),
        getZoom: () => 6, getView: () => current, commit: () => {} });
    assert.equal(controller.focusCountry(), true);
    assert.equal(controller.focusOverride.countryCode, 'BRA');
    assert.equal(controller.focusCountry('USA'), true);
    assert.equal(controller.focusOverride.countryCode, 'USA');
    assert.equal(controller.focusCountry('UNKNOWN'), false);
});
