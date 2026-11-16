import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';
import pointOnFeature from '@turf/point-on-feature';
import booleanPointInPolygon from '@turf/boolean-point-in-polygon';
import { simplifyRing } from './simplify-ring.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]/g, '');

export function geometryBounds(features) {
    let west = Infinity, south = Infinity, east = -Infinity, north = -Infinity;
    let shiftedWest = Infinity, shiftedEast = -Infinity;
    const visit = (coordinates) => {
        if (typeof coordinates?.[0] === 'number') {
            const [lng, lat] = coordinates;
            west = Math.min(west, lng); east = Math.max(east, lng);
            south = Math.min(south, lat); north = Math.max(north, lat);
            const shifted = lng < 0 ? lng + 360 : lng;
            shiftedWest = Math.min(shiftedWest, shifted); shiftedEast = Math.max(shiftedEast, shifted);
        } else {
            coordinates?.forEach(visit);
        }
    };
    features.forEach((feature) => visit(feature.geometry?.coordinates));
    if (!Number.isFinite(west)) return null;
    if (shiftedEast - shiftedWest < east - west) {
        west = shiftedWest > 180 ? shiftedWest - 360 : shiftedWest;
        east = shiftedEast > 180 ? shiftedEast - 360 : shiftedEast;
    }
    return [west, south, east, north];
}

const mercator = (lat) => Math.log(Math.tan(Math.PI / 4 + Math.max(-85.0511, Math.min(85.0511, lat)) * Math.PI / 360));
export function focusGeometry(geometry) {
    if (!geometry || !['Polygon', 'MultiPolygon'].includes(geometry.type)) return null;
    const reduce = (ring) => {
        let reduced = ring;
        for (let tolerance = 0.03; reduced === ring && ring.length > 4 && tolerance >= 0.00000003; tolerance /= 10) {
            reduced = simplifyRing(ring, tolerance);
        }
        return reduced.map(([lng, lat]) => [Number(lng.toFixed(6)), Number(lat.toFixed(6))]);
    };
    return { type: geometry.type, coordinates: geometry.type === 'Polygon'
        ? geometry.coordinates.map(reduce) : geometry.coordinates.map((polygon) => polygon.map(reduce)) };
}
export function typicalCitySpan(cities) {
    const spans = cities.map((city) => {
        const bounds = city.bbox || geometryBounds([city]);
        if (!bounds) return 0;
        const [west, south, east, north] = bounds;
        const width = (east < west ? east + 360 - west : east - west) / 360;
        const height = Math.abs(mercator(north) - mercator(south)) / (2 * Math.PI);
        return Math.sqrt(width * height);
    }).filter((span) => Number.isFinite(span) && span > 0).sort((a, b) => a - b);
    return spans.length ? spans[Math.floor(spans.length / 2)] : null;
}

export function countryDefinitions() {
    return JSON.parse(fs.readFileSync(path.join(root, 'scripts/countries.json'), 'utf8'));
}

function featureRanges(bytes) {
    const text = bytes.toString('utf8');
    const header = /"features"\s*:\s*\[/.exec(text);
    if (!header) throw new Error('Missing GeoJSON features array.');

    const ranges = [];
    let depth = 0, start = 0, quoted = false;
    for (let i = Buffer.byteLength(text.slice(0, header.index + header[0].length)); i < bytes.length; i++) {
        const byte = bytes[i];
        if (quoted) {
            if (byte === 92) i++;
            else if (byte === 34) quoted = false;
        } else if (byte === 34) quoted = true;
        else if (byte === 123) {
            if (depth++ === 0) start = i;
        } else if (byte === 125 && --depth === 0) {
            ranges.push([start, i - start + 1]);
        } else if (byte === 93 && depth === 0) break;
    }
    return ranges;
}

function readWithBounds(filename) {
    const bytes = fs.readFileSync(filename);
    const features = JSON.parse(bytes.toString('utf8')).features;
    const ranges = featureRanges(bytes);
    if (ranges.length !== features.length) throw new Error(`Cannot index feature offsets in ${filename}.`);
    const chunks = [];
    let cursor = 0;
    features.forEach((feature, index) => {
        const bounds = geometryBounds([feature]);
        if (!bounds || JSON.stringify(feature.bbox) === JSON.stringify(bounds)) return;
        const [start, length] = ranges[index];
        if (Object.hasOwn(feature, 'bbox')) {
            feature.bbox = bounds;
            chunks.push(bytes.subarray(cursor, start), Buffer.from(JSON.stringify(feature)));
            cursor = start + length;
        } else {
            feature.bbox = bounds;
            chunks.push(bytes.subarray(cursor, start + 1), Buffer.from(`"bbox":${JSON.stringify(bounds)},`));
            cursor = start + 1;
        }
    });
    if (chunks.length) {
        chunks.push(bytes.subarray(cursor));
        fs.writeFileSync(filename, Buffer.concat(chunks));
    }
    return features;
}

export function buildLocationIndex(directory = path.join(root, 'dist/geojson'), { metadataDirectory = path.join(root, '.geojson.cache') } = {}) {
    const countries = countryDefinitions();
    const catalog = {};
    const index = {};
    const displays = {};
    const administrativeGeometry = {};
    const exists = (filename) => fs.existsSync(filename);
    const read = readWithBounds;
    const describe = (filename, features) => {
        if (displays[filename]) return;
        displays[filename] = { path: filename, bounds: geometryBounds(features) };
        const ranges = featureRanges(fs.readFileSync(path.join(directory, filename)));
        if (ranges.length !== features.length) throw new Error(`Cannot index feature offsets in ${filename}.`);
        administrativeGeometry[filename] = features.flatMap((feature, index) => {
            const id = feature.properties?.shapeID;
            const bounds = feature.bbox;
            return id && bounds ? [[id, bounds, ...ranges[index]]] : [];
        });
    };

    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
        if (!entry.isDirectory() || !/^[A-Z]{3}$/.test(entry.name)) continue;
        const country = entry.name;
        const countryDir = path.join(directory, country);
        const features = exists(path.join(countryDir, 'adm1.geojson')) ? read(path.join(countryDir, 'adm1.geojson')) : [];
        const config = countries[country] ?? definitionFromFeatures(country, features, metadataDirectory);
        if (features.length) describe(`${country}/adm1.geojson`, features);
        const countryFile = `${country}/adm0.geojson`;
        if (exists(path.join(directory, countryFile))) {
            const roots = read(path.join(directory, countryFile));
            describe(countryFile, roots);
            const ids = [...new Set(roots.map((feature) => feature.properties?.shapeID).filter(Boolean))];
            if (ids.length !== 1) throw new Error(`Expected one country shapeID in ${countryFile}.`);
            catalog[country] = { id: ids[0], className: config.className, name: roots[0].properties.shapeName };
        }
        const states = {};
        for (const [code, name] of Object.entries(config.states)) {
            const names = [name, ...Object.entries(config.aliases).filter(([, value]) => value === code).map(([alias]) => alias)];
            const feature = features.find(({ properties: p }) => config.stateIds?.[code] && p.shapeID === config.stateIds[code])
                ?? features.find(({ properties: p }) => config.prefix && (p.shapeISO === `${config.prefix}-${code}` || p.shapeISO === code))
                ?? features.find(({ properties: p }) => names.some((name) => normalize(p.shapeName) === normalize(name)));
            const stateFolder = feature?.properties.shapeISO === code ? code.split('-').at(-1) : code;
            const filename = `${country}/${stateFolder}/adm2.geojson`;
            const available = exists(path.join(directory, filename));
            const cities = available ? read(path.join(directory, filename)) : [];
            if (available) describe(filename, cities);
            states[code] = {
                names: [...new Set([...names, feature?.properties.shapeName].filter(Boolean))],
                path: available ? filename : null,
                cities: cities.map((feature) => [feature.properties.shapeID, feature.properties.shapeName, feature.bbox ?? null]),
                bounds: geometryBounds(cities),
                focusBounds: feature?.bbox ?? null,
                focusGeometry: feature ? focusGeometry(feature.geometry) : null,
                featureId: feature?.properties.shapeID ?? null,
                citySpan: typicalCitySpan(cities),
            };
        }

        const nationalFile = path.join(countryDir, 'adm2.geojson');
        if (Object.values(states).some((state) => !state.path) && exists(nationalFile)) {
            const grouped = {};
            const nationalCities = read(nationalFile);
            describe(`${country}/adm2.geojson`, nationalCities);
            for (const city of nationalCities) {
                if (!city.geometry) continue;
                const point = pointOnFeature(city);
                const parent = features.find((feature) => feature.geometry && booleanPointInPolygon(point, feature));
                if (!parent) continue;
                const code = Object.keys(states).find((key) => states[key].names.some((name) => normalize(name) === normalize(parent.properties.shapeName)));
                if (!code || states[code].path?.endsWith('/adm2.geojson') && states[code].path !== `${country}/adm2.geojson`) continue;
                states[code].path = `${country}/adm2.geojson`;
                states[code].cities.push([city.properties.shapeID, city.properties.shapeName, city.bbox ?? null]);
                (grouped[code] ??= []).push(city);
            }
            Object.entries(grouped).forEach(([code, cities]) => {
                states[code].bounds = geometryBounds(cities);
                states[code].citySpan = typicalCitySpan(cities);
            });
        }
        index[country] = states;
    }
    const remaining = (folder) => {
        for (const entry of fs.readdirSync(folder, { withFileTypes: true })) {
            const file = path.join(folder, entry.name);
            if (entry.isDirectory()) remaining(file);
            else if (/^adm[012]\.geojson$/.test(entry.name)) {
                const canonical = file;
                const filename = path.relative(directory, canonical);
                if (!displays[filename]) describe(filename, read(canonical));
            }
        }
    };
    remaining(directory);
    fs.writeFileSync(path.join(directory, 'locations.json'), JSON.stringify(index));
    fs.writeFileSync(path.join(directory, 'catalog.json'), JSON.stringify(catalog));
    fs.writeFileSync(path.join(directory, 'geometry.json'), JSON.stringify(displays));
    fs.writeFileSync(path.join(directory, 'administrative-geometry.json'), JSON.stringify(administrativeGeometry));
    fs.rmSync(path.join(directory, 'municipal-geometry.json'), { force: true });
    fs.rmSync(path.join(directory, 'display.json'), { force: true });
    return index;
}

function definitionFromFeatures(country, features, metadataDirectory) {
    const metadataFile = path.join(metadataDirectory, `${country}-ADM0-meta.json`);
    const raw = fs.existsSync(metadataFile) ? JSON.parse(fs.readFileSync(metadataFile, 'utf8')) : {};
    const metadata = Array.isArray(raw) ? raw[0] : raw;
    const name = metadata.boundaryName || country;
    const className = name.normalize('NFD').replace(/[\u0300-\u036f]/g, '').split(/[^a-zA-Z0-9]+/)
        .filter(Boolean).map((part) => part[0].toUpperCase() + part.slice(1)).join('');
    if (!/^[A-Za-z][A-Za-z0-9]*$/.test(className)) throw new Error(`Cannot generate a class name for ${country}.`);
    const states = {};
    const stateIds = {};
    for (const feature of features) {
        const { shapeISO, shapeName } = feature.properties;
        const code = shapeISO && shapeISO !== 'None' && shapeISO !== country
            ? shapeISO.split('-').slice(1).join('-') || shapeISO
            : shapeName.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-zA-Z0-9]+/g, '-').toUpperCase();
        if (states[code]) throw new Error(`Duplicate state code ${country}/${code}.`);
        states[code] = shapeName;
        stateIds[code] = feature.properties.shapeID;
    }
    return { className, prefix: null, states, stateIds, aliases: {} };
}

const phpString = (value) => `'${String(value).replaceAll('\\', '\\\\').replaceAll("'", "\\'")}'`;
const phpUnion = (values) => [...new Set(values)].sort().map((value) => {
    if (/[\r\n]|\*\//.test(value)) throw new Error('A catalog value cannot be safely represented in PHPDoc.');
    return phpString(value);
}).join('|') || 'string';

export function generateCountryClasses(directory, output = path.join(root, 'src/Countries')) {
    const catalog = JSON.parse(fs.readFileSync(path.join(directory, 'catalog.json'), 'utf8'));
    const locations = JSON.parse(fs.readFileSync(path.join(directory, 'locations.json'), 'utf8'));
    const names = new Set();
    fs.mkdirSync(output, { recursive: true });
    for (const [iso, country] of Object.entries(catalog)) {
        const name = country.className;
        if (!/^[A-Za-z][A-Za-z0-9]*$/.test(name) || names.has(name)
            || ['Country', 'Boundary', 'AdministrativeBoundary'].includes(name)) {
            throw new Error(`Country class name collision: ${name}.`);
        }
        names.add(name);
        const states = phpUnion(Object.keys(locations[iso] || {}));
        const cities = phpUnion(Object.values(locations[iso] || {}).flatMap((state) => state.cities.map(([, city]) => city)));
        const methods = [
            [`@param ${states} $code`, 'state(string $code)', 'string', 'parent::state($code)'],
            [`@param ${states} $state\n     * @param ${cities} $name`, 'city(string $state, string $name)', 'string', 'parent::city($state, $name)'],
            [`@param array<${states}, int|float|null> $values\n     * @return array<string, int|float|null>`, 'states(array $values)', 'array', 'parent::states($values)'],
            [`@param ${states} $state\n     * @param array<${cities}, int|float|null> $values\n     * @return array<string, int|float|null>`, 'cities(string $state, array $values)', 'array', 'parent::cities($state, $values)'],
        ].map(([doc, signature, returns, call]) => `    /**\n     * ${doc}\n     */\n    public static function ${signature}: ${returns}\n    {\n        return ${call};\n    }`).join('\n\n');
        const source = `<?php\n\nnamespace EduardoRibeiroDev\\FilamentLeaflet\\Countries;\n\n/** Generated by npm run build:locations. */\nfinal class ${name} extends Country\n{\n    protected const ISO_CODE = ${phpString(iso)};\n\n${methods}\n}\n`;
        const filename = path.join(output, `${name}.php`);
        if (!fs.existsSync(filename) || fs.readFileSync(filename, 'utf8') !== source) fs.writeFileSync(filename, source);
    }
}

export function validateRegionTranslations(directory = path.join(root, 'dist/geojson'), languageDirectory = path.join(root, 'resources/lang')) {
    return execFileSync('php', [path.join(root, 'scripts/validate-region-translations.php'), directory, languageDirectory], {
        encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'],
    });
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    const index = buildLocationIndex();
    generateCountryClasses(path.join(root, 'dist/geojson'));
    console.log(validateRegionTranslations().trim());
    console.log(`Indexed ${Object.keys(index).length} countries for administrative location resolution.`);
}
