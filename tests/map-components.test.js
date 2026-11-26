import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const components = {
    'leaflet-map-field.js': 'leafletMapField',
    'leaflet-map-widget.js': 'leafletMapWidget',
    'leaflet-map-entry.js': 'leafletMapEntry',
    'leaflet-map-column.js': 'leafletMapColumn',
};

/**
 * The script is loaded on request, so it can run after Livewire has started (e.g. in a lazy widget):
 * the components must be defined as soon as it runs, not on a `livewire:init` event that already happened.
 */
for (const [file, name] of Object.entries(components)) {
    test(`${name} is defined as soon as its script runs`, async () => {
        const source = await readFile(new URL(`../resources/js/${file}`, import.meta.url), 'utf8');
        const listeners = [];
        const context = vm.createContext({
            window: { addEventListener: () => {} },
            document: { addEventListener: (type) => listeners.push(type) },
            LeafletMapCore: class {},
        });

        vm.runInContext(source.replace(/^import .*$/gm, ''), context);

        assert.equal(typeof context.window[name], 'function');
        assert.deepEqual(listeners, []);
    });
}

test('the map component loads the script that it needs', async () => {
    const view = await readFile(new URL('../resources/views/components/map.blade.php', import.meta.url), 'utf8');
    const provider = await readFile(new URL('../src/FilamentLeafletServiceProvider.php', import.meta.url), 'utf8');

    assert.match(provider, /Js::make\('leaflet-map', [^)]+\)->loadedOnRequest\(\)/);
    assert.match(view, /@assets\s+<script src="\{\{ FilamentAsset::getScriptSrc\('leaflet-map', 'eduardoribeirodev\/filament-leaflet'\) \}\}"><\/script>\s+@endassets/);
});
