import assert from 'node:assert/strict';
import test from 'node:test';
import {
    BoundarySourceCache,
    GeoJsonBoundaryController,
    resolveBoundaryData,
    selectBoundaryLevel,
    intersectsViewport,
    visibleFeatures,
    forEachFrame,
} from '../resources/js/geojson-boundary.js';

const deferred = () => {
    let resolve, reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
};

test('render batches yield between frames and abort without running queued work', async () => {
    const frames = new Map(), visited = [], abort = new AbortController();
    let nextId = 0;
    const pending = forEachFrame([1, 2, 3, 4, 5], (item) => visited.push(item), {
        signal: abort.signal, maxItems: 2, now: () => 0,
        schedule: (callback) => { frames.set(++nextId, callback); return nextId; },
        cancel: (id) => frames.delete(id),
    });
    assert.deepEqual(visited, [1, 2]);
    const [id, callback] = [...frames][0]; frames.delete(id); callback();
    assert.deepEqual(visited, [1, 2, 3, 4]);
    abort.abort();
    assert.equal(await pending, false);
    assert.equal(frames.size, 0);
    assert.deepEqual(visited, [1, 2, 3, 4]);
});

test('render batches yield when their time budget expires, even with few features', async () => {
    const frames = [], visited = [];
    let time = 0;
    const pending = forEachFrame([1, 2, 3], (item) => { visited.push(item); time += 7; }, {
        now: () => time, schedule: (callback) => { frames.push(callback); return frames.length; }, cancel: () => {},
    });
    assert.deepEqual(visited, [1]);
    frames.shift()(); assert.deepEqual(visited, [1, 2]);
    frames.shift()();
    assert.equal(await pending, true);
    assert.deepEqual(visited, [1, 2, 3]);
});

const data = () => ({
    id: 'brazil',
    style: { weight: 2 },
    administrative: {
        dataLevel: 2,
        zoomLevels: [{ minZoom: 0, level: 0 }, { minZoom: 6, level: 1 }, { minZoom: 10, level: 2 }],
        levels: {
            0: { source: { type: 'url', value: '/country' }, features: { key: 'shapeGroup' }, visualization: { values: { BRA: 165 } } },
            1: { source: { type: 'url', value: '/states' }, features: { key: 'shapeISO' }, visualization: { values: { 'BR-MT': 165 } } },
            2: { source: { type: 'url', value: '/towns' }, features: { key: 'shapeID' }, visualization: { values: { a: 45, b: 120 } } },
        },
    },
});

test('viewport intersection handles latitude, date line and wrapped world copies', () => {
    assert.equal(intersectsViewport([-60, -18, -50, -7], [-58, -16, -54, -12]), true);
    assert.equal(intersectsViewport([-60, -18, -50, -7], [-49, -16, -40, -12]), false);
    assert.equal(intersectsViewport([-60, -18, -50, -7], [-58, 0, -54, 10]), false);
    assert.equal(intersectsViewport([170, 45, -130, 72], [-178, 50, -160, 60]), true);
    assert.equal(intersectsViewport([170, 45, -130, 72], [-10, 50, 10, 60]), false);
    assert.equal(intersectsViewport([-60, -18, -50, -7], [302, -16, 306, -12]), true);
    assert.equal(intersectsViewport([170, 45, -130, 72], [175, 50, 190, 60]), true);
    assert.equal(intersectsViewport([-60, -18, -50, -7], [-540, -80, 540, 80]), true);
});

const viewportData = () => ({
    id: 'brazil',
    source: { type: 'collection', value: [
        { type: 'url', value: '/MT', bounds: [-60, -18, -50, -7] },
        { type: 'url', value: '/SP', bounds: [-53, -26, -44, -19] },
    ] },
});

test('canonical geometry is used at every zoom while preserving source filters and bounds', () => {
    const data = { source: { type: 'url', value: '/detail', simplified: '/display', detailZoom: 12,
        bounds: [-60, -18, -50, -7], featureIds: ['a'], cullFeatures: true } };
    const low = resolveBoundaryData(data, 7, [-58, -16, -54, -12]);
    const high = resolveBoundaryData(data, 12, [-58, -16, -54, -12]);
    assert.equal(low.data.source.value, '/detail');
    assert.equal(high.data.source.value, '/detail');
    assert.equal(low.key, high.key);
    assert.deepEqual(low.data.source.featureIds, ['a']);
    assert.deepEqual(low.data.source.bounds, data.source.bounds);
});

test('canonical geometry is used even when legacy display options are present', () => {
    const data = { source: { type: 'url', value: '/detail', simplified: '/display', detailZoom: 12 } };
    assert.equal(resolveBoundaryData(data, 7).data.source.value, '/detail');
    assert.equal(resolveBoundaryData(data, 7, [-58, -16, -54, -12]).data.source.value, '/detail');
    assert.equal(resolveBoundaryData(data, 19).data.source.value, '/detail');
    assert.equal(resolveBoundaryData({ ...data, isEditable: true }, 19).data.source.value, '/detail');
});

test('features within a loaded state are culled by viewport and source geometry is not mutated', () => {
    const point = (id, coordinates) => ({ id, type: 'Feature', geometry: { type: 'Point', coordinates } });
    const source = { type: 'FeatureCollection', features: [point('a', [-56, -15]), point('b', [-52, -9])] };
    assert.deepEqual(visibleFeatures(source, [-57, -16, -55, -14]).features.map((f) => f.id), ['a']);
    assert.deepEqual(visibleFeatures(source, [-53, -10, -51, -8]).features.map((f) => f.id), ['b']);
    assert.equal(source.features.length, 2);
});

test('precomputed feature bounds avoid traversing coordinates, including the date line', () => {
    const features = [{ type: 'Feature', bbox: [175, 50, -160, 60],
        geometry: { get coordinates() { throw new Error('Coordinates must not be visited for viewport filtering.'); } } }];
    assert.equal(visibleFeatures({ type: 'FeatureCollection', features }, [-178, 51, -170, 59]).features.length, 1);
    assert.equal(visibleFeatures({ type: 'FeatureCollection', features }, [-10, 51, 10, 59]).features.length, 0);
});

test('movement aborts an asynchronous render and its completion cannot overwrite the active level', async () => {
    let zoom = 3, render;
    const loaded = [], preparation = deferred();
    const controller = new GeoJsonBoundaryController({ data: data(),
        cache: new BoundarySourceCache(() => ({ ok: true, json: () => ({ type: 'FeatureCollection', features: [] }) })),
        getZoom: () => zoom,
        commit: (_data, boundary, options) => {
            if (boundary.displayLevel === 1) { render = options; return preparation.promise; }
        },
        onLoaded: (event) => loaded.push(event),
    });
    await controller.request();
    zoom = 6;
    const pending = controller.request('zoom');
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(render.signal.aborted, false);
    assert.equal(controller.active.key, 0);
    controller.suspend();
    assert.equal(render.signal.aborted, true);
    zoom = 3; await controller.resume('zoom');
    preparation.resolve(true);
    assert.equal(await pending, false);
    assert.equal(controller.active.key, 0);
    assert.equal(loaded.length, 1);
    controller.destroy();
});

test('panning inside a state redraws only when the visible municipality set changes', async () => {
    const commits = [], requests = [];
    let viewport = [-57, -16, -55, -14];
    const cache = new BoundarySourceCache((url) => {
        requests.push(url);
        return { ok: true, json: () => ({ type: 'FeatureCollection', features: [
            { id: 'a', bbox: [-56.5, -15.5, -55.5, -14.5] },
            { id: 'b', bbox: [-52.5, -9.5, -51.5, -8.5] },
        ] }) };
    });
    const controller = new GeoJsonBoundaryController({
        data: { source: { type: 'url', value: '/MT', bounds: [-60, -18, -50, -7], cullFeatures: true } },
        cache, getZoom: () => 8, getBounds: () => viewport,
        commit: (data) => commits.push(data.features.map((feature) => feature.id)),
    });
    await controller.request();
    viewport = [-57.1, -16.1, -55.1, -14.1];
    await controller.request('move');
    assert.equal(commits.length, 1);
    viewport = [-53, -10, -51, -8];
    await controller.request('move');
    viewport = [-57, -16, -55, -14];
    await controller.request('move');
    assert.deepEqual(commits, [['a'], ['b'], ['a']]);
    assert.deepEqual(requests, ['/MT']);
});

test('state selection changes only when the set intersecting the viewport changes', () => {
    const first = resolveBoundaryData(viewportData(), 11, [-58, -16, -54, -12]);
    const withinState = resolveBoundaryData(viewportData(), 11, [-57, -15, -55, -13]);
    assert.equal(first.key, withinState.key);
    assert.deepEqual(first.data.source.value.map((part) => part.value), ['/MT']);
    const otherState = resolveBoundaryData(viewportData(), 11, [-50, -24, -46, -21]);
    assert.notEqual(first.key, otherState.key);
    assert.deepEqual(otherState.data.source.value.map((part) => part.value), ['/SP']);
    const outside = resolveBoundaryData(viewportData(), 11, [0, 0, 10, 10]);
    assert.equal(outside.data.source.type, 'inline');
    assert.equal(outside.data.source.value.features.length, 0);
    assert.equal(viewportData().source.value.length, 2);
});

test('panning between states fetches only visible files and returning uses cache without fitting', async () => {
    const requests = [], commits = [], loaded = [];
    let bounds = [-58, -16, -54, -12];
    const cache = new BoundarySourceCache((url) => {
        requests.push(url);
        return { ok: true, json: () => ({ type: 'FeatureCollection', features: [{ id: url }] }) };
    });
    const controller = new GeoJsonBoundaryController({ data: viewportData(), cache,
        getZoom: () => 11, getBounds: () => bounds,
        commit: (data) => commits.push(data.features.map((feature) => feature.id)),
        onLoaded: (event) => loaded.push(event),
    });
    await controller.request();
    assert.deepEqual(requests, ['/MT']);
    await controller.request('move');
    assert.equal(commits.length, 1);
    bounds = [-50, -24, -46, -21];
    await controller.request('move');
    assert.deepEqual(requests, ['/MT', '/SP']);
    bounds = [-58, -16, -54, -12];
    await controller.request('move');
    assert.deepEqual(requests, ['/MT', '/SP']);
    bounds = [0, 0, 10, 10];
    await controller.request('move');
    assert.deepEqual(commits, [['/MT'], ['/SP'], ['/MT'], []]);
    assert.deepEqual(loaded, [{ initial: true }, { initial: false }, { initial: false }, { initial: false }]);
});

test('a response for a viewport left behind cannot replace the current state', async () => {
    const requests = [], commits = [];
    let bounds = [-58, -16, -54, -12];
    const cache = new BoundarySourceCache((url) => {
        const request = { url, ...deferred() }; requests.push(request); return request.promise;
    });
    const controller = new GeoJsonBoundaryController({ data: viewportData(), cache,
        getZoom: () => 11, getBounds: () => bounds, commit: (data) => commits.push(data.features),
    });
    const first = controller.request();
    await Promise.resolve();
    bounds = [-50, -24, -46, -21];
    const latest = controller.request('move');
    await Promise.resolve();
    requests[1].resolve({ ok: true, json: () => ({ type: 'FeatureCollection', features: [{ id: 'SP' }] }) });
    await latest;
    requests[0].resolve({ ok: true, json: () => ({ type: 'FeatureCollection', features: [{ id: 'MT' }] }) });
    await first;
    assert.deepEqual(commits, [[{ id: 'SP' }]]);
});

function fixture(initialZoom = 3) {
    const requests = [];
    const commits = [];
    const errors = [];
    const loaded = [];
    let zoom = initialZoom;
    const controller = new GeoJsonBoundaryController({
        data: data(),
        cache: {
            load(source) {
                const request = { ...deferred(), source };
                requests.push(request);
                return request.promise;
            },
        },
        getZoom: () => zoom,
        commit: (geoJson, boundary) => commits.push({ geoJson, boundary }),
        onLoaded: (event) => loaded.push(event),
        onError: (error) => errors.push(error),
    });
    return { controller, requests, commits, errors, loaded, zoom: (value) => { zoom = value; } };
}

test('movement start invalidates pending replacements while keeping the active paths', async () => {
    const f = fixture();
    const first = f.controller.request();
    f.requests[0].resolve('country');
    await first;
    f.zoom(10);
    const towns = f.controller.request('zoom');
    f.controller.suspend();
    f.zoom(6);
    f.requests[1].resolve('obsolete towns');
    assert.equal(await towns, false);
    assert.deepEqual(f.commits.map((entry) => entry.geoJson), ['country']);
    assert.equal(f.controller.active.key, 0);
    assert.equal(await f.controller.request('zoom'), false);
    assert.equal(f.requests.length, 2, 'Suspension must not start more loads.');
    const settled = f.controller.resume('zoom');
    assert.equal(f.requests[2].source.value, '/states');
    f.requests[2].resolve('states');
    assert.equal(await settled, true);
    assert.deepEqual(f.commits.map((entry) => entry.geoJson), ['country', 'states']);
});

test('rapid successive movements resume using only the final zoom and data revision', async () => {
    const f = fixture();
    const first = f.controller.request();
    f.requests[0].resolve('country');
    await first;
    f.controller.suspend();
    f.zoom(6);
    const states = f.controller.resume('zoom');
    f.controller.suspend();
    f.zoom(10);
    const updated = data();
    updated.administrative.levels[2].visualization.values.a = 999;
    await f.controller.update(updated);
    assert.equal(f.requests.length, 2, 'An update during movement must wait for the final view.');
    f.requests[1].resolve('obsolete states');
    await states;
    assert.equal(f.commits.length, 1);
    const towns = f.controller.resume('zoom');
    f.requests[2].resolve('towns');
    await towns;
    assert.equal(f.controller.active.key, 2);
    assert.equal(f.commits[1].boundary.visualization.values.a, 999);
    assert.deepEqual(f.loaded, [{ initial: true }, { initial: false }]);
});

test('a suspended in-flight source can finish in cache and is reused on resume', async () => {
    const response = deferred();
    const commits = [];
    let calls = 0;
    const cache = new BoundarySourceCache(() => { calls++; return response.promise; });
    const controller = new GeoJsonBoundaryController({
        data: { source: { type: 'url', value: '/towns' } }, cache,
        getZoom: () => 10, commit: (geoJson) => commits.push(geoJson),
    });
    const pending = controller.request();
    controller.suspend();
    response.resolve({ ok: true, json: () => ({ type: 'FeatureCollection', features: [] }) });
    assert.equal(await pending, false);
    assert.equal(commits.length, 0);
    await controller.resume();
    assert.equal(calls, 1);
    assert.equal(commits.length, 1);
    controller.destroy();
    assert.equal(await controller.resume(), false);
});

test('selects inclusive zoom thresholds, including fractional thresholds and zoom below the first', () => {
    const levels = [{ minZoom: 10, level: 2 }, { minZoom: 2, level: 0 }, { minZoom: 6.5, level: 1 }];
    assert.equal(selectBoundaryLevel(levels, -1), 0);
    assert.equal(selectBoundaryLevel(levels, 6.49), 0);
    assert.equal(selectBoundaryLevel(levels, 6.5), 1);
    assert.equal(selectBoundaryLevel(levels, 10), 2);
    assert.equal(selectBoundaryLevel(levels, 30), 2);
    assert.equal(selectBoundaryLevel(levels, 3), 0);
});

test('resolves sources, feature keys and summed values together, preserving shared style', () => {
    const resolved = resolveBoundaryData(data(), 3);
    assert.equal(resolved.key, 0);
    assert.equal(resolved.data.source.value, '/country');
    assert.equal(resolved.data.features.key, 'shapeGroup');
    assert.deepEqual(resolved.data.visualization.values, { BRA: 165 });
    assert.deepEqual(resolved.data.style, { weight: 2 });
    const fixed = { source: { type: 'inline', value: { type: 'FeatureCollection', features: [] } } };
    assert.equal(resolveBoundaryData(fixed, 12).data, fixed);
});

test('shares in-flight and completed requests and does not fetch inline data', async () => {
    const response = deferred();
    let calls = 0;
    const cache = new BoundarySourceCache(() => { calls++; return response.promise; });
    const source = { type: 'url', value: '/states' };
    const first = cache.load(source);
    const second = cache.load(source);
    assert.equal(first, second);
    response.resolve({ ok: true, json: () => ({ type: 'FeatureCollection', features: [] }) });
    const loaded = await first;
    assert.equal(await cache.load(source), loaded);
    assert.equal(calls, 1);
    assert.deepEqual(await cache.load({ type: 'inline', value: { test: true } }), { test: true });
    assert.equal(calls, 1);
});

test('combines state sources while filtering shared national files and fetching each URL once', async () => {
    const features = ['a', 'b', 'c'].map((id) => ({ type: 'Feature', properties: { shapeID: id }, geometry: null }));
    const requests = [];
    const cache = new BoundarySourceCache((url) => {
        requests.push(url);
        return { ok: true, json: () => ({ type: 'FeatureCollection', features }) };
    });
    const source = {
        type: 'collection', value: [
            { type: 'url', value: '/national', featureIds: ['a'] },
            { type: 'url', value: '/national', featureIds: ['b'] },
            { type: 'inline', value: { type: 'FeatureCollection', features: [] } },
        ],
    };
    const combined = await cache.load(source);
    assert.deepEqual(combined.features.map((feature) => feature.properties.shapeID), ['a', 'b']);
    assert.deepEqual(requests, ['/national']);
    await cache.load(source);
    assert.equal(requests.length, 1);
    const unfiltered = await cache.load({ type: 'url', value: '/national' });
    assert.equal(unfiltered.features.length, 3, 'Filtering must not mutate the cached national geometry.');
});

test('a failed state file rejects the whole replacement and can be retried', async () => {
    let fail = true;
    const cache = new BoundarySourceCache((url) => ({
        ok: !(fail && url === '/missing'), status: 404,
        json: () => ({ type: 'FeatureCollection', features: [] }),
    }));
    const source = { type: 'collection', value: [{ type: 'url', value: '/ok' }, { type: 'url', value: '/missing' }] };
    await assert.rejects(cache.load(source), /404/);
    fail = false;
    assert.equal((await cache.load(source)).features.length, 0);
});

test('failed requests leave the cache and can be retried', async () => {
    let calls = 0;
    const cache = new BoundarySourceCache(() => {
        calls++;
        return { ok: calls > 1, status: 404, json: () => ({ valid: true }) };
    });
    const source = { type: 'url', value: '/states' };
    await assert.rejects(cache.load(source), /status 404/);
    assert.deepEqual(await cache.load(source), { valid: true });
    assert.equal(calls, 2);
});

test('destroying the map aborts cached network requests', async () => {
    let signal;
    const cache = new BoundarySourceCache((url, options) => {
        signal = options.signal;
        return { ok: true, json: () => ({}) };
    });
    await cache.load({ type: 'url', value: '/states' });
    cache.destroy();
    assert.equal(signal.aborted, true);
    assert.equal(cache.entries.size, 0);
});

test('initial fit is allowed once, and automatic switches preserve the view', async () => {
    const f = fixture();
    let pending = f.controller.request();
    f.requests[0].resolve('country');
    await pending;
    assert.deepEqual(f.loaded, [{ initial: true }]);
    f.zoom(6);
    pending = f.controller.request('zoom');
    f.requests[1].resolve('states');
    await pending;
    assert.deepEqual(f.loaded, [{ initial: true }, { initial: false }]);
    assert.deepEqual(f.commits.map((entry) => entry.geoJson), ['country', 'states']);
});

test('zooming inside a pending band shares the request and suppresses late initial fit', async () => {
    const f = fixture(6);
    const first = f.controller.request();
    f.zoom(7);
    const second = f.controller.request('zoom');
    assert.equal(first, second);
    assert.equal(f.requests.length, 1);
    f.requests[0].resolve('states');
    await first;
    assert.deepEqual(f.loaded, [{ initial: false }]);
    f.zoom(8);
    await f.controller.request('zoom');
    assert.equal(f.requests.length, 1);
});

test('returning to the visible level cancels a pending switch without replacing visible geometry', async () => {
    const f = fixture();
    const initial = f.controller.request();
    f.requests[0].resolve('country');
    await initial;
    f.zoom(6);
    const states = f.controller.request('zoom');
    f.zoom(3);
    await f.controller.request('zoom');
    f.requests[1].resolve('states');
    assert.equal(await states, false);
    assert.deepEqual(f.commits.map((entry) => entry.geoJson), ['country']);
});

test('out-of-order responses cannot overwrite the newest selected level', async () => {
    const f = fixture();
    const country = f.controller.request();
    f.zoom(10);
    const towns = f.controller.request('zoom');
    f.requests[1].resolve('towns');
    await towns;
    f.requests[0].resolve('country');
    await country;
    assert.deepEqual(f.commits.map((entry) => entry.geoJson), ['towns']);
});

test('failed switches preserve the visible level and allow a later retry', async () => {
    const f = fixture();
    const initial = f.controller.request();
    f.requests[0].resolve('country');
    await initial;
    f.zoom(6);
    const failed = f.controller.request('zoom');
    f.requests[1].reject(new Error('offline'));
    assert.equal(await failed, false);
    assert.equal(f.controller.active.key, 0);
    assert.equal(f.errors.length, 1);
    const retry = f.controller.request('zoom');
    f.requests[2].resolve('states');
    await retry;
    assert.equal(f.controller.active.key, 1);
});

test('same-id configuration updates invalidate earlier responses and refresh data and style', async () => {
    const f = fixture();
    const oldRequest = f.controller.request();
    const updated = data();
    updated.style = { weight: 5 };
    updated.administrative.levels[0].visualization.values.BRA = 999;
    const latest = f.controller.update(updated);
    f.requests[0].resolve('obsolete');
    await oldRequest;
    assert.equal(f.commits.length, 0);
    f.requests[1].resolve('current');
    await latest;
    assert.equal(f.commits[0].boundary.style.weight, 5);
    assert.equal(f.commits[0].boundary.visualization.values.BRA, 999);
    assert.deepEqual(f.loaded, [{ initial: false }]);
});

test('removal while loading prevents commits and suppresses obsolete errors', async () => {
    const f = fixture();
    const pending = f.controller.request();
    f.controller.destroy();
    f.requests[0].reject(new Error('removed'));
    assert.equal(await pending, false);
    assert.equal(f.commits.length, 0);
    assert.equal(f.errors.length, 0);
    await f.controller.request();
    assert.equal(f.requests.length, 1);
});

test('a rejected replacement keeps the previous active level', async () => {
    const f = fixture();
    const initial = f.controller.request();
    f.requests[0].resolve('country');
    await initial;
    f.controller.commit = () => { throw new Error('Invalid GeoJSON'); };
    f.zoom(6);
    const failed = f.controller.request('zoom');
    f.requests[1].resolve('invalid');
    assert.equal(await failed, false);
    assert.equal(f.controller.active.key, 0);
    assert.equal(f.errors[0].message, 'Invalid GeoJSON');
});
