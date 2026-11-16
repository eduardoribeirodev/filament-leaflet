import assert from 'node:assert/strict';
import test from 'node:test';
import { createChoroplethScale, getChoroplethColor, getFeatureLabel, getFeatureValue } from '../resources/js/geojson-boundary.js';

test('reads feature ids and nested properties', () => {
    const feature = { id: 'fallback', properties: { code: 'BR-MT', meta: { label: 'Mato Grosso' } } };

    assert.equal(getFeatureValue(feature), 'fallback');
    assert.equal(getFeatureValue(feature, 'code'), 'BR-MT');
    assert.equal(getFeatureValue(feature, 'properties.meta.label'), 'Mato Grosso');
});

test('administrative labels use shapeID translations with native and custom-label fallbacks', () => {
    const feature = { properties: { shapeID: 'country', shapeName: 'Brazil', customName: 'My territory' } };
    assert.equal(getFeatureLabel(feature, 'shapeName', { country: 'Brasil' }), 'Brasil');
    assert.equal(getFeatureLabel(feature, 'properties.shapeName', { country: 'Brésil' }), 'Brésil');
    assert.equal(getFeatureLabel(feature, 'shapeName', {}), 'Brazil');
    assert.equal(getFeatureLabel(feature, 'shapeName', { country: '' }), 'Brazil');
    assert.equal(getFeatureLabel(feature, 'shapeName', { country: null }), 'Brazil');
    assert.equal(getFeatureLabel(feature, 'customName', { country: 'Brasil' }), 'My territory');
    assert.equal(feature.properties.shapeName, 'Brazil', 'Translation must not mutate source names.');
});

test('maps negative and positive values across the complete color scale', () => {
    const values = { low: -10, middle: 0, high: 10 };
    const colors = ['#light', '#medium', '#dark'];

    assert.equal(getChoroplethColor(-10, values, colors, '#missing'), '#light');
    assert.equal(getChoroplethColor(0, values, colors, '#missing'), '#medium');
    assert.equal(getChoroplethColor(10, values, colors, '#missing'), '#dark');
});

test('uses the final color when all values are equal and the fallback when absent', () => {
    assert.equal(getChoroplethColor(5, { a: 5, b: 5 }, ['#light', '#dark'], '#missing'), '#dark');
    assert.equal(getChoroplethColor(undefined, { a: 5 }, ['#light'], '#missing'), '#missing');
});

test('compiled scales read the domain once, support large datasets and ignore nonfinite values', () => {
    let reads = 0;
    const values = { get low() { reads++; return -10; }, high: 10, invalid: NaN };
    const scale = createChoroplethScale(values, ['low', 'high'], 'missing');
    for (let i = 0; i < 1000; i++) assert.equal(scale(-10), 'low');
    assert.equal(reads, 1);
    assert.equal(scale(Infinity), 'missing');
    assert.equal(scale('10'), 'missing');
    const large = Object.fromEntries(Array.from({ length: 200000 }, (_, i) => [i, i]));
    assert.equal(createChoroplethScale(large, ['low', 'high'], 'missing')(199999), 'high');
});
