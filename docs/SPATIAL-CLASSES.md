# Spatial Support Classes

This document describes the PHP support classes for spatial operations in the application.

## Table of Contents

1. [Geometry Classes](#geometry-classes)
2. [Spatial Query Builder](#spatial-query-builder)
3. [Measurement Tools](#measurement-tools)
4. [Coordinate Converter](#coordinate-converter)
5. [Geocoding](#geocoding)
6. [Import/Export](#importexport)
7. [Performance Optimization](#performance-optimization)

---

## Geometry Classes

Located in `app/Support/Geo/Geometry/`

### Point

```php
use App\Support\Geo\Geometry\Point;

$point = new Point(-23.5505, -46.6333);

// Properties
$point->latitude;   // -23.5505
$point->longitude;  // -46.6333

// Export
$point->toArray();    // ['lat' => -23.5505, 'lng' => -46.6333]
$point->toGeoJson();  // ['type' => 'Point', 'coordinates' => [-46.6333, -23.5505]]
$point->toWkt();      // 'POINT(-46.6333 -23.5505)'

// Parse from various formats
Point::fromArray(['lat' => -23.55, 'lng' => -46.63]);
Point::fromGeoJson(['type' => 'Point', 'coordinates' => [-46.63, -23.55]]);
Point::fromWkt('POINT(-46.63 -23.55)');
```

### Polygon

```php
use App\Support\Geo\Geometry\Polygon;

$polygon = new Polygon([
    new Point(-23.55, -46.63),
    new Point(-23.55, -46.62),
    new Point(-23.54, -46.62),
    new Point(-23.54, -46.63),
]);

// Properties
$polygon->getPoints();
$polygon->getBounds();  // ['minLat' => ..., 'maxLat' => ..., 'minLng' => ..., 'maxLng' => ...]
$polygon->getCentroid(); // Point

// Operations
$polygon->contains($point);  // boolean

// Export
$polygon->toArray();
$polygon->toGeoJson();
$polygon->toWkt();
```

### LineString

```php
use App\Support\Geo\Geometry\LineString;

$line = new LineString([
    new Point(-23.55, -46.63),
    new Point(-23.56, -46.64),
    new Point(-23.57, -46.65),
]);

$line->getLength();  // Length in km
$line->getBounds();
$line->toGeoJson();
```

### GeometryCollection

```php
use App\Support\Geo\Geometry\GeometryCollection;

$collection = new GeometryCollection([
    new Point(-23.55, -46.63),
    new Polygon([...]),
    new LineString([...]),
]);

$collection->toGeoJson();
```

---

## Spatial Query Builder

Located in `app/Support/Spatial/SpatialQueryBuilder.php`

Register macros in a service provider:

```php
use App\Support\Spatial\SpatialQueryBuilder;

// In AppServiceProvider::boot()
SpatialQueryBuilder::registerMacros();
```

### Usage

```php
use App\Models\Infrastructure;

// Find within radius (km)
$nearby = Infrastructure::query()
    ->withinRadius('location', -23.55, -46.63, 10)
    ->get();

// Find within bounding box
$inBounds = Infrastructure::query()
    ->withinBounds('location', -24, -23, -47, -46)
    ->get();

// Order by distance
$ordered = Infrastructure::query()
    ->orderByDistance('location', -23.55, -46.63)
    ->get();

// Add distance as column
$withDistance = Infrastructure::query()
    ->withDistance('location', -23.55, -46.63, 'distance_km')
    ->get();
```

---

## Measurement Tools

Located in `app/Support/Spatial/MeasurementTools.php`

```php
use App\Support\Spatial\MeasurementTools;

$tools = new MeasurementTools();

// Distance calculation (Haversine)
$distance = $tools->distance(
    [-23.55, -46.63],  // [lat, lng]
    [-22.90, -43.17],
    'km'  // km, m, mi, nm
);

// High-precision distance (Vincenty)
$precise = $tools->vincentyDistance([-23.55, -46.63], [-22.90, -43.17]);

// Bearing
$bearing = $tools->bearing([-23.55, -46.63], [-22.90, -43.17]);
$compass = $tools->bearingToCompass($bearing);  // 'NE', 'SSW', etc.

// Polygon area
$area = $tools->polygonArea([
    [-23.55, -46.63],
    [-23.55, -46.62],
    [-23.54, -46.62],
    [-23.54, -46.63],
], 'km2');  // km2, m2, ha, acres

// Midpoint
$mid = $tools->midpoint([-23.55, -46.63], [-22.90, -43.17]);

// Centroid
$center = $tools->centroid($coordinates);

// Destination from bearing
$dest = $tools->destination([-23.55, -46.63], 45.0, 100);  // 100km at 45°

// Point in polygon
$inside = $tools->pointInPolygon([-23.545, -46.625], $polygonCoords);

// Bounding box
$bounds = $tools->boundingBox($coordinates);

// Formatting
$tools->formatDistance(358.5);  // "358.50 km"
$tools->formatArea(1.234);      // "1.23 km²"
```

---

## Coordinate Converter

Located in `app/Support/Spatial/CoordinateConverter.php`

```php
use App\Support\Spatial\CoordinateConverter;

$converter = new CoordinateConverter();

// Decimal Degrees to DMS
$dms = $converter->toDMS(-23.5505, -46.6333);
// "23°33'01.8\"S, 46°37'59.9\"W"

// DMS to Decimal Degrees
[$lat, $lng] = $converter->fromDMS("23°33'01.8\"S", "46°37'59.9\"W");

// Decimal Degrees to DDM (Degrees Decimal Minutes)
$ddm = $converter->toDDM(-23.5505, -46.6333);
// "23°33.030'S, 46°37.998'W"

// To UTM
$utm = $converter->toUTM(-23.5505, -46.6333);
// ['zone' => 23, 'band' => 'K', 'easting' => 333520.5, 'northing' => 7394432.1]

// From UTM
[$lat, $lng] = $converter->fromUTM(23, 'K', 333520.5, 7394432.1);

// To GeoJSON Point
$geojson = $converter->toGeoJsonPoint(-23.5505, -46.6333);

// Validate coordinates
$converter->isValidLatitude(-23.5505);   // true
$converter->isValidLongitude(-46.6333);  // true
$converter->normalizeLongitude(190);     // -170
```

---

## Geocoding

Located in `app/Support/Spatial/Geocoder.php`

```php
use App\Support\Spatial\Geocoder;

$geocoder = new Geocoder();

// Configure provider (default: nominatim)
$geocoder->provider('mapbox', 'your-api-key');

// Forward geocoding
$results = $geocoder->geocode('1600 Pennsylvania Avenue, Washington DC');

foreach ($results as $result) {
    echo $result->getLatitude();
    echo $result->getLongitude();
    echo $result->getFormattedAddress();
    echo $result->getCity();
    echo $result->getCountry();
}

// Reverse geocoding
$result = $geocoder->reverse(-23.5505, -46.6333);
echo $result->getFormattedAddress();

// With options
$results = $geocoder
    ->countryBias('BR')
    ->language('pt')
    ->limit(5)
    ->geocode('Avenida Paulista');

// Batch geocoding
$results = $geocoder->batchGeocode([
    'São Paulo, Brazil',
    'Rio de Janeiro, Brazil',
    'Brasília, Brazil',
]);
```

### Available Providers

| Provider | API Key Required | Rate Limits |
|----------|-----------------|-------------|
| `nominatim` | No | 1 req/sec |
| `mapbox` | Yes | 600 req/min |
| `google` | Yes | Varies |

---

## Import/Export

### GeoJSON Import

```php
use App\Support\Geo\GeoJsonImporter;

$importer = new GeoJsonImporter();

// From file
$features = $importer->fromFile('/path/to/file.geojson');

// From string
$features = $importer->fromString($geoJsonString);

// With property mapping
$importer
    ->mapProperties([
        'name' => 'properties.title',
        'description' => 'properties.desc',
    ])
    ->geometryColumn('location')
    ->storeAsGeoJson(true);

// Import to model
$models = $importer->importToModel(Location::class, $features);

// Validate
$errors = $importer->validate($geoJsonString);
```

### GeoJSON Export

```php
use App\Support\Geo\GeoJsonExporter;

$exporter = new GeoJsonExporter();

// From model
$geoJson = $exporter
    ->fromModel(Infrastructure::class)
    ->geometryColumn('location')
    ->properties(['name', 'description'])
    ->wgs84()
    ->export();

// From collection
$geoJson = $exporter
    ->fromCollection($data)
    ->geometryColumn('location')
    ->allProperties()
    ->except(['password'])
    ->prettyPrint()
    ->export();

// Custom transformations
$exporter->transformGeometry(function ($geometry, $item) {
    return [...];
});

$exporter->transformProperties(function ($properties, $item) {
    $properties['custom'] = $item->computedValue();
    return $properties;
});

// Save to file
$exporter->toFile('/path/to/output.geojson');

// Download response
return $exporter->download('export.geojson');
```

### KML Import/Export

```php
use App\Support\Geo\KmlImporter;
use App\Support\Geo\KmlExporter;

// Import
$importer = new KmlImporter();
$features = $importer->fromFile('/path/to/file.kml');
$features = $importer->fromKmz('/path/to/file.kmz');

// Convert to GeoJSON
$geoJson = $importer->toGeoJson($features);

// Export
$exporter = new KmlExporter();
$kml = $exporter
    ->fromModel(Infrastructure::class)
    ->name('My Export')
    ->geometryColumn('location')
    ->nameColumn('name')
    ->descriptionColumn('description')
    ->addPointStyle('default', 'http://example.com/icon.png', 1.0, 'ff0000ff')
    ->addPolygonStyle('polygon', '7f00ff00', 'ff00ff00')
    ->defaultStyle('default')
    ->extendedData(['category', 'status'])
    ->export();

return $exporter->download('export.kml');
```

### GPX Import

```php
use App\Support\Geo\GpxImporter;

$importer = new GpxImporter();
$data = $importer->fromFile('/path/to/track.gpx');

// Access waypoints
foreach ($data['waypoints'] as $waypoint) {
    echo $waypoint['name'];
    echo $waypoint['latitude'];
    echo $waypoint['longitude'];
    echo $waypoint['elevation'];
}

// Access tracks with statistics
foreach ($data['tracks'] as $track) {
    echo $track['name'];
    echo $track['statistics']['distance'];      // km
    echo $track['statistics']['elevationGain']; // meters
    echo $track['statistics']['duration'];      // seconds
    echo $track['statistics']['avgSpeed'];      // km/h
}

// Convert to GeoJSON
$geoJson = $importer->toGeoJson($data);
```

### CSV Import

```php
use App\Support\Geo\CsvGeoImporter;

$importer = new CsvGeoImporter();

// Basic usage
$features = $importer
    ->latitudeColumn('lat')
    ->longitudeColumn('lng')
    ->fromFile('/path/to/data.csv');

// European format (semicolon delimiter)
$features = $importer
    ->europeanFormat()
    ->latitudeColumn('latitude')
    ->longitudeColumn('longitude')
    ->fromFile('/path/to/data.csv');

// Tab-delimited
$features = $importer->tabDelimited()->fromFile('/path/to/data.tsv');

// Combined coordinate column
$features = $importer
    ->coordinateColumn('coords')
    ->fromFile('/path/to/data.csv');

// DMS coordinates are automatically parsed
// "23°33'01.8\"S, 46°37'59.9\"W" → -23.5505, -46.6333

// Custom column mapping
$importer->mapColumns([
    'title' => 'name',
    'desc' => 'description',
]);

// Validate before import
$errors = $importer->validate($csvContent);

// Convert to GeoJSON
$geoJson = $importer->toGeoJson($features);
```

---

## Performance Optimization

### Spatial Indexing

```php
use App\Support\Spatial\SpatialIndex;

$index = new SpatialIndex();

// Create GiST index (PostGIS)
$index->createGistIndex('infrastructures', 'location');

// Create index on JSON geometry
$index->createJsonGistIndex('infrastructures', 'location');

// Create BRIN index (for large ordered data)
$index->createBrinIndex('infrastructures', 'location');

// Create compound lat/lng index
$index->createLatLngIndex('infrastructures', 'latitude', 'longitude');

// Analyze table
$index->analyze('infrastructures');
$index->vacuumAnalyze('infrastructures');

// Get index information
$indexes = $index->getIndexes('infrastructures');

// Check if index exists
$exists = $index->indexExists('infrastructures', 'location_gist_idx');

// Get table statistics
$stats = $index->getTableStats('infrastructures');
// ['row_count' => 10000, 'total_size' => '5 MB', 'index_size' => '1 MB']

// Explain query performance
$explanation = $index->explainQuery(Infrastructure::query()->withinRadius(...));

// Get index suggestions
$suggestions = $index->suggestIndexes('infrastructures', 'location');
```

### Spatial Caching

```php
use App\Support\Spatial\SpatialCache;

$cache = new SpatialCache();

// Configure
$cache->ttl(3600)->tileSize(0.1)->prefix('geo');

// Cache viewport queries
$data = $cache->viewport('markers', $bounds, function () {
    return Infrastructure::withinBounds(...)->get();
});

// Cache tile queries
$data = $cache->tile('markers', $lat, $lng, $zoom, function () {
    return $this->loadTileData(...);
});

// Cache geometry by ID
$geom = $cache->geometry('infrastructure', $id, function () use ($id) {
    return Infrastructure::find($id)->location;
});

// Invalidate cache
$cache->invalidateViewport('markers', $bounds);
$cache->invalidateRegion('markers', $bounds);
$cache->invalidateGeometry('infrastructure', $id);
$cache->invalidateAll('markers');

// Cache warming
$warmed = $cache->warmCache('markers', $bounds, [8, 10, 12, 14], function ($tileBounds) {
    return Infrastructure::withinBounds(...)->get();
});
```

### Viewport Loading

```php
use App\Support\Spatial\ViewportLoader;

$loader = ViewportLoader::make(Infrastructure::class)
    ->geometryColumn('location')
    ->withinBounds([
        'minLat' => -24, 'maxLat' => -23,
        'minLng' => -47, 'maxLng' => -46,
    ])
    ->zoom(12)
    ->cluster(true, 50)  // Enable clustering with 50px radius
    ->limit(1000)
    ->cache(true, 300)
    ->select(['id', 'name', 'location'])
    ->where(fn ($q) => $q->where('active', true));

// Load data
$data = $loader->load();

// Get count
$count = $loader->count();

// Check if data exists
$exists = $loader->exists();

// Export as GeoJSON
$geoJson = $loader->toGeoJson();

// Return as JSON response
return $loader->toResponse();
```

### Geometry Simplification

```php
use App\Support\Spatial\GeometrySimplifier;

$simplifier = new GeometrySimplifier();

// Douglas-Peucker algorithm
$simplified = $simplifier->douglasPeucker($coordinates, 0.001);

// Visvalingam-Whyatt (area-preserving)
$simplified = $simplifier->visvalingam($coordinates, 10);  // Target 10 points

// Simplify polygon
$simplified = $simplifier->simplifyPolygon($rings, 0.001);

// Simplify GeoJSON geometry
$simplified = $simplifier->simplifyGeoJson($geometry, 0.001);

// Get tolerance for zoom level
$tolerance = $simplifier->toleranceForZoom(12);
$simplified = $simplifier->simplifyGeoJson($geometry, $tolerance);

// Count points
$count = $simplifier->countPoints($geometry);

// Calculate compression ratio
$ratio = $simplifier->compressionRatio($original, $simplified);
// 0.75 = 75% reduction in points
```

---

## Map Layers

Located in `app/Support/Map/`

### Heatmap Layer

```php
use App\Support\Map\HeatmapLayer;

$layer = HeatmapLayer::make('heat')
    ->points($coordinates)  // [[lat, lng, intensity], ...]
    ->radius(25)
    ->blur(15)
    ->maxZoom(17)
    ->gradient([
        0.4 => 'blue',
        0.6 => 'cyan',
        0.7 => 'lime',
        0.8 => 'yellow',
        1.0 => 'red',
    ]);

return $layer->toArray();
```

### Cluster Layer

```php
use App\Support\Map\ClusterLayer;

$layer = ClusterLayer::make('markers')
    ->markers($markers)
    ->showCoverageOnHover()
    ->zoomToBoundsOnClick()
    ->spiderfyOnMaxZoom()
    ->maxClusterRadius(80)
    ->disableClusteringAtZoom(18)
    ->iconCreateFunction('customClusterIcon');

return $layer->toArray();
```

### WMS Layer

```php
use App\Support\Map\WMSLayer;

$layer = WMSLayer::make('imagery')
    ->url('https://example.com/wms')
    ->layers(['satellite', 'roads'])
    ->format('image/png')
    ->transparent()
    ->crs('EPSG:4326')
    ->attribution('© Provider');

return $layer->toArray();
```

### Vector Tile Layer

```php
use App\Support\Map\VectorTileLayer;

$layer = VectorTileLayer::make('buildings')
    ->url('https://tiles.example.com/{z}/{x}/{y}.pbf')
    ->sourceLayer('buildings')
    ->style([
        'fill' => true,
        'fillColor' => '#3388ff',
        'fillOpacity' => 0.5,
    ])
    ->interactive();

return $layer->toArray();
```

### Layer Manager

```php
use App\Support\Map\LayerManager;

$manager = new LayerManager();

$manager
    ->addBaseLayer(TileLayer::make('osm')->url('...'), 'OpenStreetMap')
    ->addBaseLayer(TileLayer::make('satellite')->url('...'), 'Satellite')
    ->addOverlay(HeatmapLayer::make('heat')->points(...), 'Heatmap')
    ->addOverlay(ClusterLayer::make('markers')->markers(...), 'Markers');

// Get configuration for Leaflet
$config = $manager->toLeafletConfig();
```

