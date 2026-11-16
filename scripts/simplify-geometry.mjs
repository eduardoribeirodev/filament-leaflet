import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';
import mapshaper from 'mapshaper';

export const SIMPLIFICATION_METERS = 25;
const METHOD = 'douglas-peucker';
const geometryHash = (collection) => createHash('sha256')
    .update(JSON.stringify(collection.features.map((feature) => feature.geometry))).digest('hex');

const polygonRings = (geometry) => geometry.type === 'Polygon'
    ? [geometry.coordinates] : geometry.type === 'MultiPolygon' ? geometry.coordinates : null;

function orientation(ring) {
    let area = 0, x = ring[0][0];
    for (let i = 1; i < ring.length; i++) {
        const delta = ring[i][0] - ring[i - 1][0];
        const next = x + (delta > 180 ? delta - 360 : delta < -180 ? delta + 360 : delta);
        area += x * ring[i][1] - next * ring[i - 1][1];
        x = next;
    }
    return Math.sign(area);
}

export async function simplifyFeatureCollections(collections, interval = SIMPLIFICATION_METERS) {
    if (!Number.isFinite(interval) || interval <= 0) throw new Error('Simplification distance must be positive.');
    const rings = [];
    for (const collection of collections) {
        for (const feature of collection.features) {
            if (!feature.geometry) continue;
            const polygons = polygonRings(feature.geometry);
            if (!polygons) throw new Error(`Unsupported boundary geometry: ${feature.geometry.type}`);
            for (const polygon of polygons) {
                for (let index = 0; index < polygon.length; index++) {
                    const coordinates = polygon[index];
                    rings.push({ polygon, index, coordinates, winding: orientation(coordinates) });
                }
            }
        }
    }
    const beforeVertices = rings.reduce((total, ring) => total + ring.coordinates.length, 0);
    if (collections.every((collection) => collection.simplification?.method === METHOD
        && collection.simplification.intervalMeters === interval && collection.simplification.geometryHash === geometryHash(collection))) {
        return { beforeVertices, afterVertices: beforeVertices, rings: rings.length, skipped: true };
    }
    if (collections.some((collection) => collection.simplification?.method === METHOD)) {
        throw new Error('Use fresh source geometry to change or combine previously simplified files. Run npm run build:geojson.');
    }
    if (!rings.length) return { beforeVertices: 0, afterVertices: 0, rings: 0, skipped: false };

    const input = { type: 'FeatureCollection', features: rings.map(({ coordinates }, id) => ({
        type: 'Feature', properties: { ring: id }, geometry: { type: 'Polygon', coordinates: [coordinates] },
    })) };
    const outputs = await mapshaper.applyCommands(
        `-i rings.geojson -simplify dp interval=${interval}m keep-shapes -o output.geojson format=geojson gj2008`,
        { 'rings.geojson': input },
    );
    const output = JSON.parse(outputs['output.geojson']);
    const replacements = new Map();
    for (const feature of output.features) {
        const id = feature.properties.ring;
        const geometry = feature.geometry;
        if (!Number.isInteger(id) || !rings[id] || replacements.has(id)
            || geometry?.type !== 'Polygon' || geometry.coordinates.length !== 1) {
            throw new Error('Simplification could not preserve every boundary ring.');
        }
        const coordinates = geometry.coordinates[0];
        if (coordinates.length < 4 || coordinates.some((point) => point.some((value) => !Number.isFinite(value)))
            || coordinates[0][0] !== coordinates.at(-1)[0] || coordinates[0][1] !== coordinates.at(-1)[1]) {
            throw new Error('Simplification produced an invalid boundary ring.');
        }
        if (orientation(coordinates) !== rings[id].winding) coordinates.reverse();
        replacements.set(id, coordinates);
    }
    if (replacements.size !== rings.length) throw new Error('Simplification dropped a boundary ring.');

    let afterVertices = 0;
    rings.forEach(({ polygon, index }, id) => {
        const simplified = replacements.get(id);
        afterVertices += simplified.length;
        polygon[index] = simplified;
    });
    for (const collection of collections) {
        delete collection.bbox;
        for (const feature of collection.features) {
            delete feature.bbox;
            if (feature.geometry) delete feature.geometry.bbox;
        }
        collection.simplification = { method: METHOD, intervalMeters: interval, geometryHash: geometryHash(collection) };
    }
    return { beforeVertices, afterVertices, rings: rings.length, skipped: false };
}

export async function simplifyGeometryDirectory(directory, { outputDirectory = directory, onCountry = () => {} } = {}) {
    const reports = [];
    const walk = (folder) => fs.readdirSync(folder, { withFileTypes: true }).flatMap((entry) => {
        const file = path.join(folder, entry.name);
        return entry.isDirectory() ? walk(file) : /^adm[012]\.geojson$/.test(entry.name) ? [file] : [];
    });
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
        if (!entry.isDirectory() || !/^[A-Z]{3}$/.test(entry.name)) continue;
        const files = walk(path.join(directory, entry.name));
        const collections = files.map((file) => JSON.parse(fs.readFileSync(file, 'utf8')));
        const beforeBytes = files.reduce((total, file) => total + fs.statSync(file).size, 0);
        const stats = await simplifyFeatureCollections(collections);
        let afterBytes = 0;
        files.forEach((file, index) => {
            const output = path.join(outputDirectory, path.relative(directory, file));
            const bytes = stats.skipped ? fs.readFileSync(file) : JSON.stringify(collections[index]);
            if (!stats.skipped || path.resolve(output) !== path.resolve(file)) {
                fs.mkdirSync(path.dirname(output), { recursive: true });
                fs.writeFileSync(output, bytes);
            }
            afterBytes += Buffer.byteLength(bytes);
        });
        const report = { country: entry.name, files: files.length, intervalMeters: SIMPLIFICATION_METERS,
            beforeBytes, afterBytes, ...stats };
        reports.push(report);
        onCountry(report);
    }
    return reports;
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    const directory = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../dist/geojson');
    await simplifyGeometryDirectory(directory, { onCountry: (report) => console.log(
        `${report.country}: ${report.beforeVertices.toLocaleString('en-US')} -> ${report.afterVertices.toLocaleString('en-US')} vertices`,
    ) });
    const { buildLocationIndex, generateCountryClasses, validateRegionTranslations } = await import('./build-location-index.mjs');
    buildLocationIndex(directory);
    generateCountryClasses(directory);
    console.log(validateRegionTranslations(directory).trim());
}
