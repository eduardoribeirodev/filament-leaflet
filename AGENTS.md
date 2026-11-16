# Working on Filament Leaflet

This is a Laravel package that integrates Leaflet with Filament 4 and 5. PHP code is under `src/`, Blade views and translations are under `resources/`, and Leaflet source is under `resources/js/`. Update `README.md` when changing a public API or installation step.

## Checks

- Install local dependencies with `composer install` and `npm ci` as needed.
- Run JavaScript tests with `npm test` (includes validating region translations with PHP).
- Run the standalone PHP checks with `for test in tests/*.php; do php "$test" || exit 1; done`.
- After changing frontend source, run `npm run build` and include the updated `dist/leaflet-map.css` and `dist/leaflet-map.umd.cjs` when they change. This build preserves `dist/geojson/`.
- Run `git diff --check` before preparing a commit.

## Administrative GeoJSON

- The package ships geometry and indexes in `dist/geojson/`. The `filament-leaflet:install-geojson` Artisan command publishes all available levels for each selected country. Use `--all` for unattended publication of every country. In an app that publishes a subset, set the layer scope to the installed countries.
- Country helpers under `src/Countries/` resolve administrative IDs. `AdministrativeBoundary` builds the actual map layer.
- Use `npm run build:locations` to regenerate indexes and country classes from existing geometry, `npm run build:simplify` to simplify bundled geometry and regenerate indexes, and `npm run build:geojson` to download and rebuild the datasets from source. Geometry rebuilds can produce large diffs, so run them when the task calls for a dataset change.
