import { defineConfig } from 'vite';
import path from 'path';
import { geojsonSplitPlugin } from './vite-plugins/geojson-split';

export default defineConfig({
    build: {
        outDir: 'dist',
        emptyOutDir: false,
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
    plugins: process.env.BUILD_GEOJSON === 'true'
        ? [geojsonSplitPlugin({
            countries: ['DEU', 'USA', 'ESP', 'FRA', 'ITA', 'BRA', 'PRT'],
            outDir: 'dist/geojson'
        })]
        : [],
});
