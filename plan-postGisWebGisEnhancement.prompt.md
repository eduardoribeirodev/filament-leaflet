# Plan: PostGIS and WebGIS Enhancement for Filament Leaflet

## Overview

This plan outlines the improvements needed for the `eduardoribeirodev/filament-leaflet` package to support PostGIS spatial data and add advanced WebGIS features. The contribution will be submitted as a Pull Request to the original repository.

## Documentation

Comprehensive documentation is available in the `docs/` directory:

- **[SPATIAL-API.md](docs/SPATIAL-API.md)** - REST API endpoints documentation
- **[SPATIAL-CLASSES.md](docs/SPATIAL-CLASSES.md)** - PHP support classes reference
- **[POSTGIS-GUIDE.md](docs/POSTGIS-GUIDE.md)** - PostGIS integration guide
- **[FILAMENT-INTEGRATION.md](docs/FILAMENT-INTEGRATION.md)** - FilamentPHP integration examples

---

## Phase 1: Core PostGIS Support ✅ COMPLETED

### Bug Fixes (Critical)

#### 1. MapPicker Null State Handling
**Problem:** `MapPicker` crashes when state is null or when saving polygon/circle data.
- **File:** `vendor/eduardoribeirodev/filament-leaflet/src/Fields/MapPicker.php`
- **Line 25:** `$state[$this->latitudeFieldName]` fails when state is null or has different structure
- **Solution Created:** `App\Filament\Forms\Components\MapPicker` - Extended component that properly handles null states and polygon data

#### 2. getMapCenter() State Format Handling  
**Problem:** `HasMapState::getMapCenter()` assumes state always has `latitude`/`longitude` keys
- **File:** `vendor/eduardoribeirodev/filament-leaflet/src/Concerns/HasMapState.php`
- **Line 360:** Crashes when state has `lat`/`lng` or polygon data
- **Solution Created:** Override in extended MapPicker with support for:
  - Standard `latitude`/`longitude` keys
  - Short `lat`/`lng` keys  
  - Polygon data with `points` array
  - GeoJSON format with `type` and `coordinates`

### PostGIS Components Created

| Component | Location | Description |
|-----------|----------|-------------|
| `HasSpatialColumn` | `packages/filament-leaflet-postgis/src/Concerns/` | Trait for PostGIS column detection and WKT/GeoJSON conversion |
| `SpatialScopes` | `packages/filament-leaflet-postgis/src/Database/` | Eloquent query macros for spatial operations |
| `GeometryCast` | `packages/filament-leaflet-postgis/src/Casts/` | Eloquent cast for geometry columns |
| `SpatialColumnType` | `packages/filament-leaflet-postgis/src/Enums/` | Enum for JSON/PostGIS/MySQL/SpatiaLite types |
| `MultiPoint` | `packages/filament-leaflet-postgis/src/Support/Geometry/` | MultiPoint geometry class |
| `MultiLineString` | `packages/filament-leaflet-postgis/src/Support/Geometry/` | MultiLineString geometry class |
| `MultiPolygon` | `packages/filament-leaflet-postgis/src/Support/Geometry/` | MultiPolygon geometry class |
| `GeometryCollection` | `packages/filament-leaflet-postgis/src/Support/Geometry/` | GeometryCollection class |
| `PostGISServiceProvider` | `packages/filament-leaflet-postgis/src/` | Laravel service provider |

---

## Phase 2: Spatial Query Builder ✅ COMPLETED

### Eloquent Query Macros Implemented

```php
// Find within radius (km)
Model::withinRadius('column', $lat, $lng, $radiusKm)->get();

// Order by distance  
Model::orderByDistance('column', $lat, $lng)->get();

// Select distance as column
Model::selectDistance('column', $lat, $lng, 'distance_km')->get();

// Spatial relationships
Model::intersects('column', $geometry)->get();
Model::within('column', $geometry)->get();
Model::contains('column', $geometry)->get();
Model::withinBounds('column', $minLng, $minLat, $maxLng, $maxLat)->get();

// Geometry operations
Model::buffer('column', $meters, 'buffered')->get();
Model::selectCentroid('column', 'centroid')->get();
Model::selectArea('column', 'area')->get();
Model::selectLength('column', 'length')->get();
Model::simplify('column', $tolerance, 'simplified')->get();
Model::selectAsGeoJson('column', 'geojson')->get();
```

### Application-Level Additions

Added to `Infrastructure` model:
- `distanceFrom($lat, $lng)` - Calculate distance in km (PHP-based)
- `scopeNearby($lat, $lng, $radius)` - Find within radius (PostgreSQL required)
- `scopeOrderByDistanceFrom($lat, $lng)` - Order by distance (PostgreSQL required)

---

## Phase 3: Advanced Map Layers ✅ COMPLETED

### 3.1 Heatmap Layer Support ✅
- [x] Created `HeatmapLayer` component (`app/Support/Layers/HeatmapLayer.php`)
- [x] Support for Leaflet.heat plugin configuration
- [x] Dynamic intensity configuration via `maxIntensity()`, `radius()`, `blur()`
- [x] Gradient customization with `gradient()` and `usePresetGradient()`
- [x] Preset gradients: default, hot, cool, viridis, plasma
- [x] Load from Eloquent models or Collections

### 3.2 Enhanced Marker Clustering ✅
- [x] Created `ClusterLayer` component (`app/Support/Layers/ClusterLayer.php`)
- [x] Configured for large datasets with `maxClusterRadius()`, `disableClusteringAtZoom()`
- [x] Viewport-based lazy loading support via `enableViewportLoading()`
- [x] Custom cluster rendering via `iconSizeClasses()`
- [x] Spiderfying configuration via `spiderfyOnMaxZoom()`, `spiderfyDistanceMultiplier()`
- [x] Calculate bounds for auto-fit

### 3.3 WMS/WMTS Integration ✅
- [x] Created `WMSLayer` component (`app/Support/Layers/WMSLayer.php`)
- [x] GetCapabilities URL generation
- [x] GetFeatureInfo popup support via `enableFeatureInfo()`
- [x] Multiple WMS servers support
- [x] GeoServer-specific configuration via `geoServer()`, `cqlFilter()`, `env()`
- [x] TIME and ELEVATION dimension support
- [x] WMS 1.1.1 and 1.3.0 version support

### 3.4 Vector Tile Support ✅
- [x] Created `VectorTileLayer` component (`app/Support/Layers/VectorTileLayer.php`)
- [x] MVT (Mapbox Vector Tiles) support
- [x] Style configuration via `vectorTileLayerStyles()`
- [x] Preset styles for buildings, water, roads, landuse
- [x] Mapbox integration via `mapbox()`
- [x] PostGIS ST_AsMVT endpoint support via `postGIS()`

### 3.5 Layer Manager ✅
- [x] Created `LayerManager` component (`app/Support/Layers/LayerManager.php`)
- [x] Unified interface for managing multiple layer types
- [x] Base layer configuration
- [x] Layer control positioning
- [x] Combined bounds calculation

---

## Phase 4: Spatial Analysis Tools ✅ COMPLETED

### 4.1 Measurement Tools ✅
- [x] Created `MeasurementTools` class (`app/Support/Spatial/MeasurementTools.php`)
- [x] Distance calculation (Haversine formula) in km, m, mi, nm
- [x] High-precision distance (Vincenty's formula)
- [x] Polyline length calculation
- [x] Polygon area calculation (spherical excess formula)
- [x] Initial and final bearing (azimuth) calculation
- [x] Destination point from bearing and distance
- [x] Midpoint and centroid calculation
- [x] Point-in-polygon test (ray casting)
- [x] Bounding box calculation
- [x] Buffer zone generation (circle approximation)
- [x] Distance/area formatting helpers
- [x] Bearing to compass direction conversion

### 4.2 Coordinate System Support ✅
- [x] Created `CoordinateConverter` class (`app/Support/Spatial/CoordinateConverter.php`)
- [x] Decimal Degrees (DD) format support
- [x] Degrees Minutes Seconds (DMS) conversion
- [x] Degrees Decimal Minutes (DDM) conversion
- [x] UTM coordinate conversion (to/from)
- [x] UTM zone and band calculation
- [x] GeoJSON Point conversion (to/from)
- [x] Coordinate validation (lat/lng bounds)
- [x] Longitude normalization (-180 to 180)

### 4.3 Geocoding Integration ✅
- [x] Created `Geocoder` class (`app/Support/Spatial/Geocoder.php`)
- [x] Nominatim (OpenStreetMap) provider - free, no API key
- [x] Mapbox provider support
- [x] Google Maps provider support
- [x] Forward geocoding (address to coordinates)
- [x] Reverse geocoding (coordinates to address)
- [x] Batch geocoding support
- [x] Country bias filtering
- [x] Language configuration
- [x] Confidence scoring
- [x] Created `GeocoderResult` DTO (`app/Support/Spatial/GeocoderResult.php`)

### 4.4 Service Provider ✅
- [x] Created `SpatialServiceProvider` (`app/Support/Spatial/SpatialServiceProvider.php`)
- [x] Singleton registration for all spatial services

---

## Phase 5: Import/Export ✅ COMPLETED

### 5.1 GeoJSON Import/Export ✅
- [x] Created `GeoJsonImporter` class (`app/Support/Geo/GeoJsonImporter.php`)
- [x] Parse FeatureCollection, Feature, and raw geometry
- [x] Property mapping for model import
- [x] Validation of GeoJSON structure
- [x] Created `GeoJsonExporter` class (`app/Support/Geo/GeoJsonExporter.php`)
- [x] Export from Eloquent models or collections
- [x] Property selection and transformation
- [x] CRS support (WGS84)
- [x] Pretty print option
- [x] Download as HTTP response

### 5.2 KML/KMZ Support ✅
- [x] Created `KmlImporter` class (`app/Support/Geo/KmlImporter.php`)
- [x] Parse Placemarks with Point, LineString, Polygon
- [x] KMZ (compressed) file support
- [x] Extended data parsing
- [x] MultiGeometry support
- [x] Created `KmlExporter` class (`app/Support/Geo/KmlExporter.php`)
- [x] Export to KML format
- [x] Style definitions (point, line, polygon)
- [x] Extended data columns
- [x] Dynamic styling via callback

### 5.3 GPX Support ✅
- [x] Created `GpxImporter` class (`app/Support/Geo/GpxImporter.php`)
- [x] Parse waypoints with elevation and time
- [x] Parse tracks with segments
- [x] Parse routes
- [x] Calculate track statistics (distance, elevation gain/loss, duration, avg speed)
- [x] Convert to GeoJSON

### 5.4 CSV Support ✅
- [x] Created `CsvGeoImporter` class (`app/Support/Geo/CsvGeoImporter.php`)
- [x] Separate lat/lng column parsing
- [x] Combined coordinate column parsing
- [x] DMS (Degrees Minutes Seconds) format support
- [x] European CSV format (semicolon delimiter)
- [x] Custom column mapping
- [x] Skip invalid rows option
- [x] Validation of CSV structure
- [x] Convert to GeoJSON

---

## Phase 6: Performance Optimization ✅ COMPLETED

### 6.1 Spatial Indexing ✅
- [x] Created `SpatialIndex` class (`app/Support/Spatial/SpatialIndex.php`)
- [x] GiST index creation for PostGIS columns
- [x] JSON geometry index support
- [x] BRIN index for large ordered datasets
- [x] Compound lat/lng index creation
- [x] Index existence checking
- [x] Table statistics retrieval
- [x] Query explanation (EXPLAIN ANALYZE)
- [x] Index suggestion based on query patterns

### 6.2 Caching Strategies ✅
- [x] Created `SpatialCache` class (`app/Support/Spatial/SpatialCache.php`)
- [x] Viewport-based caching with bounds rounding
- [x] Tile-based caching with XYZ coordinates
- [x] Geometry caching by ID
- [x] Tagged cache support (Redis)
- [x] Region invalidation
- [x] Cache warming for multiple zoom levels
- [x] Configurable TTL and tile size

### 6.3 Viewport Loading ✅
- [x] Created `ViewportLoader` class (`app/Support/Spatial/ViewportLoader.php`)
- [x] Bounds-based query filtering
- [x] Server-side grid clustering
- [x] Progressive loading with limits
- [x] GeoJSON export
- [x] JSON/lat-lng geometry normalization
- [x] Integrated caching support
- [x] Custom query constraints

### 6.4 Geometry Simplification ✅
- [x] Created `GeometrySimplifier` class (`app/Support/Spatial/GeometrySimplifier.php`)
- [x] Douglas-Peucker algorithm for polylines
- [x] Visvalingam-Whyatt for area-preserving simplification
- [x] Polygon simplification with ring closure
- [x] GeoJSON geometry simplification
- [x] Zoom-based tolerance calculation
- [x] Point counting and compression ratio

### 6.5 API Controller ✅
- [x] Created `SpatialDataController` (`app/Http/Controllers/Api/SpatialDataController.php`)
- [x] Features endpoint with clustering
- [x] GeoJSON endpoint
- [x] Feature count endpoint
- [x] Model whitelist for security

---

## Testing Strategy

### Unit Tests Created
- `tests/Feature/SpatialQueryTest.php` - Distance calculations, geometry classes
- `tests/Feature/MapPickerTest.php` - Data handling for locations and polygons

### Additional Tests Needed
- [ ] PostGIS-specific integration tests (require PostgreSQL)
- [ ] WKT parsing edge cases
- [ ] GeoJSON conversion accuracy
- [ ] Multi-geometry operations
- [ ] Spatial query performance benchmarks

---

## Documentation

### Created
- `packages/filament-leaflet-postgis/README.md` - Package overview
- `packages/filament-leaflet-postgis/docs/migrating-to-postgis.md` - Migration guide

### Needed
- [ ] API reference documentation
- [ ] Configuration guide
- [ ] Troubleshooting guide
- [ ] Performance tuning guide

---

## Files Modified in Application

| File | Changes |
|------|---------|
| `app/Filament/Forms/Components/MapPicker.php` | New - Extended MapPicker with bug fixes |
| `app/Filament/Resources/Subdivisions/Schemas/SubdivisionForm.php` | Use new MapPicker |
| `app/Filament/Resources/Infrastructures/Schemas/InfrastructureForm.php` | Use new MapPicker with polygon support |
| `app/Models/Infrastructure.php` | Added `distanceFrom()`, `scopeNearby()`, `scopeOrderByDistanceFrom()` |
| `tests/Pest.php` | Enabled RefreshDatabase trait |
| `tests/Feature/MapPickerTest.php` | New - Tests for data handling |
| `tests/Feature/SpatialQueryTest.php` | New - Tests for spatial queries |

---

## Pull Request Strategy

### PR #1: Bug Fixes (High Priority)
- Fix null state handling in MapPicker
- Fix getMapCenter() for various state formats
- Add test coverage

### PR #2: PostGIS Support
- Add geometry type detection
- Add spatial column trait
- Add Eloquent cast for geometry
- Add migration helpers

### PR #3: Spatial Query Builder
- Add query macros for spatial operations
- Add documentation for PostgreSQL/MySQL differences

### PR #4: Multi-Geometry Support
- Add MultiPoint, MultiLineString, MultiPolygon
- Add GeometryCollection
- Add WKT/GeoJSON conversion utilities

### PR #5: Advanced Features (Future)
- Heatmaps
- WMS integration
- Import/Export

---

## Dependencies

### Required
- PHP 8.1+
- Laravel 10+ / Filament 4+ or 5+
- PostgreSQL with PostGIS (recommended) OR MySQL 8.0+

### Suggested
- `matanyadaev/laravel-eloquent-spatial` - Enhanced Eloquent spatial support
- `spatie/laravel-data` - For typed geometry DTOs

---

## Next Steps

1. **Immediate:** Test the bug fixes in production environment
2. **Short-term:** Fork `eduardoribeirodev/filament-leaflet` and create PR for bug fixes
3. **Medium-term:** Implement Phase 3 (Advanced Map Layers)
4. **Long-term:** Complete Phases 4-6 with community feedback

