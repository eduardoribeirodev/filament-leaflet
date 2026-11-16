export function getFeatureValue(feature, path) {
    if (!path) return feature.id ?? feature.properties?.id;

    const normalizedPath = path.startsWith('properties.') ? path : `properties.${path}`;
    return normalizedPath.split('.').reduce((value, segment) => value?.[segment], feature);
}

export function getFeatureLabel(feature, path = 'name', regionNames = {}) {
    const original = getFeatureValue(feature, path);
    if (path !== 'shapeName' && path !== 'properties.shapeName') return original;

    const translated = regionNames?.[feature.properties?.shapeID];
    return typeof translated === 'string' && translated.trim() !== '' ? translated : original;
}

export function getChoroplethColor(value, values, colors, missingColor) {
    return createChoroplethScale(values, colors, missingColor)(value);
}

export function createChoroplethScale(values, colors, missingColor, domain = null) {
    let min = Infinity,
        max = -Infinity;
    for (const value of domain || Object.values(values)) {
        if (typeof value !== 'number' || !Number.isFinite(value)) continue;
        min = Math.min(min, value);
        max = Math.max(max, value);
    }
    return (value) => {
        if (!Number.isFinite(value) || !colors.length || min === Infinity) return missingColor;
        const ratio = max === min ? 1 : (value - min) / (max - min);
        const index = Math.min(colors.length - 1, Math.max(0, Math.floor(ratio * colors.length)));
        return colors[index];
    };
}

export function selectBoundaryLevel(levels, zoom) {
    const sorted = [...levels].sort((a, b) => a.minZoom - b.minZoom);
    let selected = sorted[0]?.level;

    for (const entry of sorted) {
        if (entry.minZoom > zoom) break;
        selected = entry.level;
    }

    return selected;
}

function longitudeIntervals(west, east) {
    let width = east - west;
    if (width >= 360) return [[-180, 180]];
    if (width < 0) width = ((width % 360) + 360) % 360;
    const start = ((((west + 180) % 360) + 360) % 360) - 180;
    const end = start + width;
    return end <= 180
        ? [[start, end]]
        : [
            [start, 180],
            [-180, end - 360],
        ];
}

export function intersectsViewport(bounds, viewport) {
    if (!bounds || !viewport) return true;
    if (bounds[3] < viewport[1] || bounds[1] > viewport[3]) return false;
    return longitudeIntervals(bounds[0], bounds[2]).some(([west, east]) =>
        longitudeIntervals(viewport[0], viewport[2]).some(([left, right]) => west <= right && east >= left)
    );
}

const mercatorY = (lat) =>
    Math.log(Math.tan(Math.PI / 4 + (Math.max(-85.0511, Math.min(85.0511, lat)) * Math.PI) / 360)) /
    (2 * Math.PI);
const longitudeWidth = (bounds) =>
    longitudeIntervals(bounds[0], bounds[2]).reduce((sum, [west, east]) => sum + east - west, 0) / 360;

function projectedSize(bounds, view) {
    if (!bounds || !view?.bounds || !view.size?.[0] || !view.size?.[1]) return null;
    const width = longitudeWidth(view.bounds);
    const height = Math.abs(mercatorY(view.bounds[3]) - mercatorY(view.bounds[1]));
    if (!width || !height) return null;
    const xScale = view.size[0] / width,
        yScale = view.size[1] / height;
    return {
        width: longitudeWidth(bounds) * xScale,
        height: Math.abs(mercatorY(bounds[3]) - mercatorY(bounds[1])) * yScale,
        worldScale: Math.sqrt(xScale * yScale),
    };
}

function fillsFocusDimension(size, view) {
    return size && Math.max(size.width / view.size[0], size.height / view.size[1]) >= 0.25;
}

function pointInRing(center, ring) {
    let inside = false;
    const relative = ([lng, lat]) => [((((lng - center[0] + 180) % 360) + 360) % 360) - 180, lat - center[1]];
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const [xi, yi] = relative(ring[i]);
        const [xj, yj] = relative(ring[j]);
        if (
            Math.abs(xi * yj - xj * yi) < 1e-10 &&
            Math.min(xi, xj) <= 0 &&
            Math.max(xi, xj) >= 0 &&
            Math.min(yi, yj) <= 0 &&
            Math.max(yi, yj) >= 0
        ) {
            return true;
        }
        if ((yi > 0) !== (yj > 0) && 0 < ((xj - xi) * -yi) / (yj - yi) + xi) {
            inside = !inside;
        }
    }
    return inside;
}

function containsCenter(state, center) {
    if (!state.bounds && !state.geometry) return false;
    if (!intersectsViewport(state.bounds, [center[0], center[1], center[0], center[1]])) return false;
    if (!state.geometry) return true;
    const polygons =
        state.geometry.type === 'Polygon'
            ? [state.geometry.coordinates]
            : state.geometry.type === 'MultiPolygon'
                ? state.geometry.coordinates
                : [];
    return polygons.some(
        (rings) =>
            rings.length &&
            pointInRing(center, rings[0]) &&
            !rings.slice(1).some((ring) => pointInRing(center, ring))
    );
}

const countryRegionsCache = new WeakMap();
function countryRegions(metadata) {
    if (countryRegionsCache.has(metadata)) return countryRegionsCache.get(metadata);
    const ringArea = (ring) => {
        let area = 0;
        for (let i = 1; i < ring.length; i++) {
            const [a, b] = [ring[i - 1], ring[i]];
            const dx = (((b[0] - a[0] + 540) % 360) - 180) / 360;
            area += (dx * (mercatorY(a[1]) + mercatorY(b[1]))) / 2;
        }
        return Math.abs(area);
    };
    let regions = [];
    for (const state of metadata.states || []) {
        if (!state.bounds) continue;
        let area = 0;
        const geometry = state.geometry;
        const polygons =
            geometry?.type === 'Polygon'
                ? [geometry.coordinates]
                : geometry?.type === 'MultiPolygon'
                    ? geometry.coordinates
                    : [];
        for (const rings of polygons) {
            if (rings.length) {
                area += Math.max(
                    0,
                    ringArea(rings[0]) - rings.slice(1).reduce((sum, ring) => sum + ringArea(ring), 0)
                );
            }
        }
        if (!area) continue;
        const connected = regions.filter((region) =>
            region.states.some((other) => intersectsViewport(other.bounds, state.bounds))
        );
        const region = {
            states: [state, ...connected.flatMap((entry) => entry.states)],
            area: area + connected.reduce((sum, entry) => sum + entry.area, 0),
        };
        regions = [...regions.filter((entry) => !connected.includes(entry)), region];
    }
    countryRegionsCache.set(metadata, regions);
    return regions;
}

function countryArea(metadata, view, pinnedState) {
    const regions = countryRegions(metadata);
    const pinned =
        pinnedState && regions.find((region) => region.states.some((state) => state.code === pinnedState));
    if (pinned) return pinned.area;
    const ranked = regions
        .map((region) => ({ region, score: countryFocusScore(region, view) }))
        .sort((a, b) => b.score - a.score || b.region.area - a.region.area);
    return ranked[0]?.region.area ?? 0;
}

function countryFocusScore(metadata, view) {
    const states = metadata.states || [];
    if (states.some((state) => containsCenter(state, view.center))) return 100;
    if (!view.bounds) return 0;
    const [west, south, east, north] = view.bounds;
    const width = east >= west ? east - west : east + 360 - west;
    const bottom = mercatorY(south);
    const top = mercatorY(north);
    let score = 0;

    for (const x of [0.2, 0.35, 0.5, 0.65, 0.8]) {
        for (const y of [0.2, 0.35, 0.5, 0.65, 0.8]) {
            const point = [
                west + width * x,
                (Math.atan(Math.sinh((bottom + (top - bottom) * y) * 2 * Math.PI)) * 180) / Math.PI,
            ];
            if (states.some((state) => containsCenter(state, point))) {
                score += 1 / (1 + 4 * Math.hypot(x - 0.5, y - 0.5));
            }
        }
    }
    return score;
}

export function selectFocusCountry(layers, center, previous = null, view = { center }) {
    const countries = layers.flatMap((layer) => layer.administrative?.countries || [layer]);
    const candidates = countries
        .filter((layer) => {
            const metadata = layer.administrative?.auto;
            return metadata?.contextual && metadata.countryCode;
        })
        .map((layer) => ({
            code: layer.administrative.auto.countryCode,
            score: countryFocusScore(layer.administrative.auto, view),
        }))
        .filter((entry) => entry.score > 0)
        .sort((a, b) => b.score - a.score);
    const best = candidates[0];
    return (
        candidates.find((entry) => entry.code === previous && entry.score >= best.score * 0.9)?.code ??
        best?.code ??
        null
    );
}

export function selectAutoBoundaryLevel(administrative, view, previous = null, override = null) {
    const minimum = administrative.minLevel ?? 0;
    const decide = (level, focusState, overrideActive = false) => ({
        level: Math.max(minimum, level),
        focusState,
        ...(override ? { overrideActive } : {}),
    });
    if (minimum === 2) return decide(2, null);
    if (!view?.bounds || !view.center || !view.size) return decide(0, null);
    const metadata = administrative.auto;
    if (
        metadata.contextual &&
        metadata.countryCode &&
        view.focusCountry &&
        view.focusCountry !== metadata.countryCode
    ) {
        return decide(0, null);
    }
    const country = projectedSize(metadata.countryBounds, view);
    const candidates = (metadata.states || []).filter((state) => containsCenter(state, view.center));
    const pinned = (metadata.states || []).find((state) => state.code === override?.state);
    const pinnedSize = pinned ? projectedSize(pinned.bounds, view) : null;
    const pinnedActive =
        pinned &&
        pinned.available !== false &&
        pinned.cityCount > 0 &&
        administrative.levels[2] &&
        intersectsViewport(pinned.bounds, [...view.center, ...view.center]) &&
        fillsFocusDimension(pinnedSize, view);
    const area = metadata.contextual ? countryArea(metadata, view, pinnedActive ? pinned.code : null) : 0;
    const coverage =
        area && country
            ? (area * country.worldScale ** 2) / (view.size[0] * view.size[1])
            : country
                ? (country.width * country.height) / (view.size[0] * view.size[1])
                : 0;
    const countryPinned =
        override?.country &&
        (area ? coverage >= 0.035 : fillsFocusDimension(country, view)) &&
        intersectsViewport(metadata.countryBounds, [...view.center, ...view.center]);
    const countryFocused =
        !metadata.contextual ||
        candidates.length > 0 ||
        pinnedActive ||
        countryPinned ||
        (metadata.countryCode && view.focusCountry === metadata.countryCode);
    const stateThreshold = area ? 0.06 : 0.18;
    if (
        !administrative.levels[1] ||
        !countryFocused ||
        !intersectsViewport(metadata.countryBounds, view.bounds) ||
        (coverage < stateThreshold && !pinnedActive && !countryPinned)
    ) {
        return decide(0, null);
    }

    const focus = pinnedActive
        ? pinned
        : candidates.find((state) => state.code === previous?.focusState) || candidates[0];
    if (!focus || !administrative.levels[2] || focus.available === false || !focus.cityCount)
        return decide(1, focus?.code ?? null, !!countryPinned);
    if (pinnedActive) return decide(2, focus.code, true);
    const size = projectedSize(focus.bounds, view);
    if (!size) return decide(1, focus.code, !!countryPinned);
    const stateCoverage = (size.width * size.height) / (view.size[0] * view.size[1]);
    const cityPixels =
        focus.citySpan > 0
            ? focus.citySpan * size.worldScale
            : Math.sqrt((size.width * size.height) / focus.cityCount);
    const municipal = stateCoverage >= 0.15 && cityPixels >= 14;
    return decide(municipal ? 2 : 1, focus.code, !!countryPinned);
}

const emptySource = () => ({ type: 'inline', value: { type: 'FeatureCollection', features: [] } });

const boundsCache = new WeakMap();
const featureIds = new WeakMap();
let nextFeatureId = 0;

function featureBounds(feature) {
    if (feature.bbox?.length === 4) return feature.bbox;
    if (boundsCache.has(feature)) return boundsCache.get(feature);
    let west = Infinity,
        east = -Infinity,
        south = Infinity,
        north = -Infinity;
    let shiftedWest = Infinity,
        shiftedEast = -Infinity;
    const visit = (coordinates) => {
        if (typeof coordinates?.[0] === 'number') {
            const [lng, lat] = coordinates;
            west = Math.min(west, lng);
            east = Math.max(east, lng);
            south = Math.min(south, lat);
            north = Math.max(north, lat);
            const shifted = lng < 0 ? lng + 360 : lng;
            shiftedWest = Math.min(shiftedWest, shifted);
            shiftedEast = Math.max(shiftedEast, shifted);
        } else coordinates?.forEach(visit);
    };
    visit(feature.geometry?.coordinates);
    if (shiftedEast - shiftedWest < east - west) {
        west = shiftedWest > 180 ? shiftedWest - 360 : shiftedWest;
        east = shiftedEast > 180 ? shiftedEast - 360 : shiftedEast;
    }
    const bounds = Number.isFinite(west) ? [west, south, east, north] : null;
    boundsCache.set(feature, bounds);
    return bounds;
}

function hasFeatureCulling(source) {
    return source.cullFeatures || (source.type === 'collection' && source.value.some(hasFeatureCulling));
}

export function visibleFeatures(data, viewport) {
    if (!viewport || data.type !== 'FeatureCollection') return data;
    return {
        ...data,
        features: data.features.filter((feature) => intersectsViewport(featureBounds(feature), viewport)),
    };
}

export function forEachFrame(
    items,
    visit,
    {
        signal,
        budget = 6,
        maxItems = 32,
        now = () => performance.now(),
        schedule = (callback) =>
            typeof requestAnimationFrame === 'function'
                ? requestAnimationFrame(callback)
                : setTimeout(callback, 0),
        cancel = (id) =>
            typeof cancelAnimationFrame === 'function' ? cancelAnimationFrame(id) : clearTimeout(id),
    } = {}
) {
    return new Promise((resolve, reject) => {
        let index = 0,
            frame = null;
        const finish = (value, error = null) => {
            if (frame !== null) cancel(frame);
            signal?.removeEventListener('abort', onAbort);
            if (error) reject(error);
            else resolve(value);
        };
        const onAbort = () => finish(false);
        const run = () => {
            frame = null;
            const start = now();
            let count = 0;
            try {
                while (index < items.length && !signal?.aborted) {
                    visit(items[index++]);
                    if (index < items.length && (++count >= maxItems || now() - start >= budget)) {
                        if (!signal?.aborted) frame = schedule(run);
                        else finish(false);
                        return;
                    }
                }
                finish(!signal?.aborted);
            } catch (error) {
                finish(false, error);
            }
        };
        signal?.addEventListener('abort', onAbort, { once: true });
        run();
    });
}

function geometrySignature(data) {
    return (data.features || [data])
        .map((feature) => {
            if (!featureIds.has(feature)) featureIds.set(feature, ++nextFeatureId);
            return featureIds.get(feature);
        })
        .join(',');
}

export function selectVisibleSource(source, viewport) {
    if (!viewport) return { source, signature: null };
    if (source.type === 'collection') {
        const parts = source.value.map((part) => selectVisibleSource(part, viewport));
        if (parts.every((part) => part.signature === null)) return { source, signature: null };
        const visible = parts
            .map((part, index) => ({ ...part, index }))
            .filter((part) => part.signature !== 'out');
        return {
            source: visible.length ? { ...source, value: visible.map((part) => part.source) } : emptySource(),
            signature: visible.map((part) => `${part.index}:${part.signature ?? 'all'}`).join(','),
        };
    }
    if (!source.bounds) return { source, signature: null };
    return intersectsViewport(source.bounds, viewport)
        ? { source, signature: 'in' }
        : { source: emptySource(), signature: 'out' };
}

function descriptorSelection(data, descriptor, level, zoom, viewport) {
    let key = level;
    const active = descriptor ? { ...data, ...descriptor, displayLevel: level } : data;
    const visible = selectVisibleSource(active.source, viewport);
    return visible.signature === null
        ? { key, data: active }
        : { key: `${key}:${visible.signature}`, data: { ...active, source: visible.source } };
}

function stateSource(source, code) {
    if (source.type === 'collection') {
        const selected = source.value.filter((part) => part.state === code);
        return selected.length ? { ...source, value: selected } : emptySource();
    }
    return source.state === code ? source : emptySource();
}

export function resolveBoundaryData(
    data,
    zoom,
    viewport = null,
    view = null,
    previousAuto = null,
    override = null
) {
    if (!data.administrative) return descriptorSelection(data, null, 'fixed', zoom, viewport);
    const administrative = data.administrative;
    if (administrative.countries) {
        const selections = administrative.countries.map((country, index) => {
            const countryCode = country.administrative.auto?.countryCode;
            const focus = override?.countryCode === countryCode ? override : null;
            return resolveBoundaryData(
                { ...data, ...country },
                zoom,
                viewport,
                view,
                previousAuto?.countries?.[index],
                focus
            );
        });
        return {
            key: selections.map((selection, index) => `${index}:${selection.key}`).join('|'),
            data,
            parts: selections.flatMap((selection) => selection.parts || [selection.data]),
            autoDecision: {
                countries: selections.map((selection) => selection.autoDecision),
                overrideActive: selections.some((selection) => selection.autoDecision?.overrideActive),
            },
        };
    }
    const autoDecision = administrative.auto
        ? selectAutoBoundaryLevel(administrative, view, previousAuto, override)
        : null;
    const level = autoDecision?.level ?? selectBoundaryLevel(administrative.zoomLevels, zoom);
    const descriptor = administrative.levels[level];
    if (!descriptor) throw new Error(`Missing administrative boundary level ${level}`);
    let selection;
    if (administrative.auto?.contextual && level === 2 && administrative.levels[1]) {
        const state = administrative.auto.states.find((entry) => entry.code === autoDecision.focusState);
        const parent = administrative.levels[1];
        const exclude =
            state.featureId != null
                ? { key: 'shapeID', exclude: [state.featureId] }
                : { key: parent.features?.key, exclude: [state.key ?? state.code] };
        const context = descriptorSelection(
            data,
            { ...parent, source: { ...parent.source, featureFilter: exclude } },
            1,
            zoom,
            viewport
        );
        const focus = descriptorSelection(
            data,
            { ...descriptor, source: stateSource(descriptor.source, state.code) },
            2,
            zoom,
            viewport
        );
        selection = {
            key: `context:${context.key}|focus:${state.code}:${focus.key}`,
            data: focus.data,
            parts: [context.data, focus.data],
        };
    } else selection = descriptorSelection(data, descriptor, level, zoom, viewport);
    if (autoDecision) selection.autoDecision = autoDecision;
    return selection;
}

export class BoundarySourceCache {
    constructor(fetcher = (...args) => fetch(...args)) {
        this.fetcher = fetcher;
        this.entries = new Map();
    }

    load(source) {
        if (!source?.value) return Promise.reject(new Error('Missing GeoJSON source'));
        if (source.type === 'inline') return Promise.resolve(source.value);
        if (source.type === 'collection') {
            return Promise.all(source.value.map((part) => this.load(part))).then((collections) => ({
                type: 'FeatureCollection',
                features: collections.flatMap((data) =>
                    data.type === 'FeatureCollection' ? data.features : [data]
                ),
            }));
        }
        if (source.featureFilter) {
            const { featureFilter, ...unfiltered } = source;
            const excluded = new Set(featureFilter.exclude || []);
            return this.load(unfiltered).then((data) => ({
                type: 'FeatureCollection',
                features: (data.type === 'FeatureCollection' ? data.features : [data]).filter(
                    (feature) => !excluded.has(getFeatureValue(feature, featureFilter.key))
                ),
            }));
        }
        if (source.featureIds) {
            const ids = new Set(source.featureIds);
            return this.load({ type: source.type, value: source.value }).then((data) => ({
                type: 'FeatureCollection',
                features: (data.type === 'FeatureCollection' ? data.features : [data]).filter((feature) =>
                    ids.has(feature.properties?.shapeID)
                ),
            }));
        }

        const cached = this.entries.get(source.value);
        if (cached) return cached.promise;

        const abort = new AbortController();
        const entry = { abort, promise: null };
        entry.promise = Promise.resolve()
            .then(() => this.fetcher(source.value, { signal: abort.signal }))
            .then((response) => {
                if (!response.ok) throw new Error(`GeoJSON request failed with status ${response.status}`);
                return response.json();
            })
            .catch((error) => {
                if (this.entries.get(source.value) === entry) this.entries.delete(source.value);
                throw error;
            });

        this.entries.set(source.value, entry);
        return entry.promise;
    }

    destroy() {
        this.entries.forEach(({ abort }) => abort.abort());
        this.entries.clear();
    }
}

export class GeoJsonBoundaryController {
    constructor({
        data,
        cache,
        getZoom,
        getBounds = () => null,
        getView = () => null,
        commit,
        onLoaded = () => { },
        onError = () => { },
    }) {
        this.data = data;
        this.cache = cache;
        this.getZoom = getZoom;
        this.getBounds = getBounds;
        this.getView = getView;
        this.commit = commit;
        this.onLoaded = onLoaded;
        this.onError = onError;
        this.revision = 0;
        this.requestId = 0;
        this.active = null;
        this.pending = null;
        this.allowInitialFit = true;
        this.destroyed = false;
        this.suspended = false;
        this.focusOverride = null;
    }

    focusState(code, countryCode = null) {
        const countries = this.data.administrative?.countries || [this.data];
        const country = countries.find(
            (entry) =>
                (!countryCode || entry.administrative?.auto?.countryCode === countryCode) &&
                entry.administrative?.auto?.states?.some((state) => state.code === code)
        );
        const state = country?.administrative.auto.states.find((entry) => entry.code === code);
        if (!state || state.available === false || !state.cityCount || !country.administrative.levels[2])
            return false;
        this.focusOverride = { state: code, countryCode: country.administrative.auto.countryCode ?? null };
        return true;
    }

    focusCountry(countryCode = null) {
        const countries = this.data.administrative?.countries || [this.data];
        const country = countries.find(
            (entry) =>
                entry.administrative?.auto &&
                entry.administrative.levels[1] &&
                (!countryCode || entry.administrative.auto.countryCode === countryCode)
        );
        if (!country) return false;
        this.focusOverride = { country: true, countryCode: country.administrative.auto.countryCode ?? null };
        return true;
    }

    suspend() {
        if (this.destroyed) return;
        this.suspended = true;
        this.allowInitialFit = false;
        this.requestId++;
        this.pending?.abort.abort();
        this.pending = null;
    }

    resume(reason = 'move') {
        this.suspended = false;
        return this.request(reason);
    }

    update(data) {
        this.data = data;
        this.revision++;
        return this.request('update');
    }

    request(reason = 'initial') {
        if (this.destroyed || this.suspended) return Promise.resolve(false);
        if (reason !== 'initial') this.allowInitialFit = false;

        let selection;
        let viewport;
        try {
            viewport = this.getBounds();
            if (this.focusOverride?.zoom !== undefined && this.getZoom() < this.focusOverride.zoom)
                this.focusOverride = null;
            selection = resolveBoundaryData(
                this.data,
                this.getZoom(),
                viewport,
                this.getView(),
                this.active?.autoDecision,
                this.focusOverride
            );
            if (this.focusOverride && !selection.autoDecision?.overrideActive) this.focusOverride = null;
            if (this.focusOverride && this.focusOverride.zoom === undefined)
                this.focusOverride.zoom = this.getZoom();
        } catch (error) {
            this.onError(error);
            return Promise.resolve(false);
        }

        const revision = this.revision;
        const parts = selection.parts || [selection.data];
        const cull = parts.some((part) => hasFeatureCulling(part.source));
        const viewKey = cull ? JSON.stringify(viewport) : null;
        if (
            this.active?.key === selection.key &&
            this.active.revision === revision &&
            this.active.viewKey === viewKey
        ) {
            this.requestId++;
            this.pending?.abort.abort();
            this.pending = null;
            this.active.autoDecision = selection.autoDecision;
            return Promise.resolve(false);
        }

        if (
            this.pending?.key === selection.key &&
            this.pending.revision === revision &&
            this.pending.viewKey === viewKey
        ) {
            return this.pending.promise;
        }

        this.pending?.abort.abort();
        const requestId = ++this.requestId;
        const pending = {
            key: selection.key,
            revision,
            viewKey,
            promise: null,
            abort: new AbortController(),
        };
        pending.promise = Promise.all(
            parts.map((part) => this.cache.load(part.source).then((geoJson) => ({ part, geoJson })))
        )
            .then(async (loaded) => {
                if (
                    this.destroyed ||
                    this.suspended ||
                    requestId !== this.requestId ||
                    revision !== this.revision
                ) {
                    return false;
                }

                const contexts = new WeakMap();
                const geometries = loaded.map(({ part, geoJson }) => {
                    const visible = hasFeatureCulling(part.source)
                        ? visibleFeatures(geoJson, viewport)
                        : geoJson;
                    if (selection.parts)
                        for (const feature of visible.features || [visible]) contexts.set(feature, part);
                    return visible;
                });
                const visible = selection.parts
                    ? {
                        type: 'FeatureCollection',
                        features: geometries.flatMap((geometry) => geometry.features || [geometry]),
                    }
                    : geometries[0];
                const signature = cull ? geometrySignature(visible) : null;
                if (
                    cull &&
                    this.active?.key === selection.key &&
                    this.active.revision === revision &&
                    this.active.signature === signature
                ) {
                    this.active.viewKey = viewKey;
                    this.active.autoDecision = selection.autoDecision;
                    this.pending = null;
                    return false;
                }

                const committed = await this.commit(
                    visible,
                    selection.parts
                        ? { ...selection.data, displayParts: selection.parts, featureContexts: contexts }
                        : selection.data,
                    { signal: pending.abort.signal }
                );
                if (
                    committed === false ||
                    this.destroyed ||
                    this.suspended ||
                    requestId !== this.requestId ||
                    revision !== this.revision
                ) {
                    return false;
                }
                const initial = this.active === null && this.allowInitialFit;
                this.active = {
                    key: selection.key,
                    revision,
                    viewKey,
                    signature,
                    autoDecision: selection.autoDecision,
                };
                this.pending = null;
                this.allowInitialFit = false;
                this.onLoaded({ initial });
                return true;
            })
            .catch((error) => {
                if (
                    !this.destroyed &&
                    !this.suspended &&
                    requestId === this.requestId &&
                    revision === this.revision
                ) {
                    this.pending = null;
                    this.onError(error);
                }
                return false;
            });

        this.pending = pending;
        return pending.promise;
    }

    destroy() {
        this.destroyed = true;
        this.requestId++;
        this.pending?.abort.abort();
        this.pending = null;
    }
}
