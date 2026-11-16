import assert from 'node:assert/strict';
import test from 'node:test';
import { geometryBounds } from '../scripts/build-location-index.mjs';

test('metadata includes every polygon vertex and uses narrow bounds across the date line', () => {
    const feature = (coordinates) => ({ geometry: { type: 'Polygon', coordinates: [coordinates] } });
    assert.deepEqual(geometryBounds([feature([[-60, -15], [-50, -7], [-55, -18]])]), [-60, -18, -50, -7]);
    assert.deepEqual(geometryBounds([feature([[175, 50], [-160, 60], [179, 55]])]), [175, 50, -160, 60]);
    assert.equal(geometryBounds([]), null);
});
