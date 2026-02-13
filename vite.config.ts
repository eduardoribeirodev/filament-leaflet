import { defineConfig } from 'vite';
import path from 'path';
import { viteStaticCopy } from 'vite-plugin-static-copy';

export default defineConfig({
    build: {
        outDir: 'resources/dist',
        lib: {
            entry: path.resolve(__dirname, 'resources/js/index.js'),
            name: 'LeafletMap',
            fileName: 'leaflet-map',
            formats: ['umd'],
        },
        rollupOptions: {
            output: {
                inlineDynamicImports: true,
            },
        },
    },
    plugins: [
        viteStaticCopy({
            targets: [
                {
                    src: 'node_modules/leaflet/dist/images/*.png',
                    dest: 'images'
                },
                {
                    src: 'resources/images/*.png',
                    dest: 'images'
                },
            ]
        })
    ]
});