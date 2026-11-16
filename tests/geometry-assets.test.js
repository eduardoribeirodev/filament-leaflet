import assert from 'node:assert/strict';
import test from 'node:test';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { buildLocationIndex, generateCountryClasses } from '../scripts/build-location-index.mjs';
import { simplifyRing } from '../scripts/simplify-ring.mjs';

test('rebuilding metadata preserves the original geometry without producing variants', () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'leaflet-geometry-'));
    try {
        fs.mkdirSync(path.join(directory, 'BRA/MT'), { recursive: true });
        const feature = { type: 'Feature', properties: { shapeID: 'mt', shapeISO: 'BR-MT', shapeName: 'Mato Grosso', note: 'Cuiabá' },
            geometry: { type: 'Polygon', coordinates: [[[-60, -20], [-55, -19.9999], [-50, -20], [-50, -10], [-60, -10], [-60, -20]]] } };
        const raw = JSON.stringify({ type: 'FeatureCollection', features: [feature] });
        for (const name of ['BRA/adm0.geojson', 'BRA/adm1.geojson', 'BRA/MT/adm2.geojson']) fs.writeFileSync(path.join(directory, name), raw);
        const first = buildLocationIndex(directory);
        const indexed = fs.readFileSync(path.join(directory, 'BRA/MT/adm2.geojson'), 'utf8');
        assert.equal(indexed, raw.replace('"features":[{', '"features":[{"bbox":[-60,-20,-50,-10],'));
        assert.deepEqual(buildLocationIndex(directory), first);
        assert.equal(fs.readFileSync(path.join(directory, 'BRA/MT/adm2.geojson'), 'utf8'), indexed);
        const geometry = JSON.parse(fs.readFileSync(path.join(directory, 'administrative-geometry.json'), 'utf8'));
        for (const [filename, entries] of Object.entries(geometry)) {
            const bytes = fs.readFileSync(path.join(directory, filename));
            for (const [id, bounds, offset, length] of entries) {
                const stored = JSON.parse(bytes.subarray(offset, offset + length).toString('utf8'));
                assert.equal(stored.properties.shapeID, id);
                assert.deepEqual(stored.bbox, bounds);
                assert.deepEqual(stored.geometry, feature.geometry);
            }
        }
        assert.equal(fs.existsSync(path.join(directory, 'BRA/MT/adm2.original.geojson')), false);
        const metadata = JSON.parse(fs.readFileSync(path.join(directory, 'geometry.json'), 'utf8'));
        assert.equal(metadata['BRA/MT/adm2.geojson'].path, 'BRA/MT/adm2.geojson');
        assert.equal(metadata['BRA/MT/adm2.geojson'].original, undefined);
        assert.equal(fs.existsSync(path.join(directory, 'BRA/MT/adm2.display.geojson')), false);
    } finally { fs.rmSync(directory, { recursive: true, force: true }); }
});

test('focus outlines can remove redundant points while keeping closed rings', () => {
    const ring = [[0, 0], [1, 0.0001], [2, 0], [2, 1], [1, 1.0001], [0, 1], [0, 0]];
    const reduced = simplifyRing(ring, 0.01);
    assert.equal(reduced.length, 5);
    assert.deepEqual(reduced[0], reduced.at(-1));
    assert.equal(ring.length, 7);
});

test('small focus islands cannot collapse to invalid rings', () => {
    const ring = [[0, 0], [0.001, 0], [0.001, 0.001], [0, 0.001], [0, 0]];
    assert.deepEqual(simplifyRing(ring, 1), ring);
});


test('new country maps generate their catalog and PHPDoc without an existing PHP country class', () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'leaflet-country-'));
    try {
        const countryDir = path.join(directory, 'ZZZ');
        fs.mkdirSync(path.join(countryDir, 'AA'), { recursive: true });
        const write = (filename, id, name, code) => fs.writeFileSync(path.join(countryDir, filename), JSON.stringify({
            type: 'FeatureCollection', features: [{ type: 'Feature', properties: { shapeID: id, shapeName: name, shapeISO: code },
                geometry: { type: 'Polygon', coordinates: [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]] } }],
        }));
        write('adm0.geojson', 'country', 'Example', 'ZZZ');
        write('adm1.geojson', 'state', 'Example State', 'ZZ-AA');
        write('AA/adm2.geojson', 'city', "D'Example", '');
        const first = buildLocationIndex(directory);
        assert.equal(first.ZZZ.AA.featureId, 'state');
        assert.equal(first.ZZZ.AA.cities[0][0], 'city');
        const output = path.join(directory, 'php');
        generateCountryClasses(directory, output);
        const source = fs.readFileSync(path.join(output, 'ZZZ.php'), 'utf8');
        assert.ok(source.includes('extends Country'));
        assert.ok(source.includes('namespace EduardoRibeiroDev\\FilamentLeaflet\\Countries;'));
        assert.ok(source.includes('function state(string $code): string'));
        assert.ok(source.includes('function cities(string $state, array $values): array'));
        assert.ok(!source.includes(': static'));
        assert.ok(source.includes("@param 'AA' $code"));
        assert.ok(source.includes("D\\'Example"));
        assert.deepEqual(buildLocationIndex(directory), first);
        generateCountryClasses(directory, output);
        assert.equal(fs.readFileSync(path.join(output, 'ZZZ.php'), 'utf8'), source);
    } finally { fs.rmSync(directory, { recursive: true, force: true }); }
});
