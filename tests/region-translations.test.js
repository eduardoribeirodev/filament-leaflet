import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { validateRegionTranslations } from '../scripts/build-location-index.mjs';

test('bundled region translations reference existing IDs in every supported locale', () => {
    assert.match(validateRegionTranslations(), /across 7 locales/);
});

test('translation validation rejects orphan IDs, invalid labels and malformed dictionaries', () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'leaflet-label-validation-'));
    try {
        const languages = path.join(directory, 'lang');
        fs.mkdirSync(path.join(languages, 'en'), { recursive: true });
        fs.writeFileSync(path.join(directory, 'administrative-geometry.json'), JSON.stringify({ 'EX/adm0.geojson': [['country', [], 0, 1]] }));
        const filename = path.join(languages, 'en/regions.php');
        fs.writeFileSync(filename, "<?php return ['country' => 'Example'];");
        assert.match(validateRegionTranslations(directory, languages), /Validated 1 region labels/);
        fs.writeFileSync(filename, "<?php return ['missing' => 'Example'];");
        assert.throws(() => validateRegionTranslations(directory, languages), /Unknown region shapeID missing/);
        fs.writeFileSync(filename, "<?php return ['country' => '   '];");
        assert.throws(() => validateRegionTranslations(directory, languages), /Invalid region label/);
        fs.writeFileSync(filename, "<?php return 'wrong';");
        assert.throws(() => validateRegionTranslations(directory, languages), /must return a translation array/);
    } finally { fs.rmSync(directory, { recursive: true, force: true }); }
});
