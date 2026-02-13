# Filament Leaflet PostGIS

PostGIS and advanced spatial support for [Filament Leaflet](https://github.com/eduardoribeirodev/filament-leaflet).

This package extends the Filament Leaflet package with:

- 🗺️ **PostGIS Support** - Native geometry/geography column handling
- 🔍 **Spatial Queries** - Distance, intersects, contains, within, and more
- 📐 **Multi-Geometries** - MultiPoint, MultiLineString, MultiPolygon, GeometryCollection
- 🔄 **WKT/GeoJSON Conversion** - Seamless format conversion
- ⚡ **Performance** - Spatial indexing, caching, and viewport filtering

## Requirements

- PHP 8.1+
- Laravel 10+
- Filament 4.0 or 5.0
- PostgreSQL with PostGIS extension (recommended)
- OR MySQL 8.0+ with spatial extensions

## Installation

```bash
composer require eduardoribeirodev/filament-leaflet-postgis
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=filament-leaflet-postgis-config
```

## Quick Start

### 1. Enable PostGIS

```sql
CREATE EXTENSION IF NOT EXISTS postgis;
```

### 2. Create a Migration with Geometry Column

```php
Schema::create('locations', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->geometry('coordinates', 'POINT', 4326)->nullable();
    $table->timestamps();
});

// Add spatial index
DB::statement('CREATE INDEX idx_locations_coordinates ON locations USING GIST (coordinates)');
```

### 3. Configure Your Model

```php
use EduardoRibeiroDev\FilamentLeaflet\Casts\GeometryCast;
use EduardoRibeiroDev\FilamentLeaflet\Database\SpatialScopes;

class Location extends Model
{
    use SpatialScopes;

    protected function casts(): array
    {
        return [
            'coordinates' => GeometryCast::class,
        ];
    }
}
```

### 4. Use in Filament Forms

```php
use EduardoRibeiroDev\FilamentLeaflet\Fields\MapPicker;

MapPicker::make('coordinates')
    ->spatialColumn()
    ->srid(4326)
    ->center(-23.5505, -46.6333)
    ->zoom(12)
```

### 5. Query Spatially

```php
// Find within radius (km)
Location::withinRadius('coordinates', $lat, $lng, 5)->get();

// Order by distance
Location::orderByDistance('coordinates', $lat, $lng)->get();

// Select with distance
Location::select('*')
    ->selectDistance('coordinates', $lat, $lng, 'distance_km')
    ->get();

// Spatial relationships
Location::intersects('coordinates', $polygon)->get();
Location::within('coordinates', $polygon)->get();
Location::contains('coordinates', $point)->get();
```

## Multi-Geometry Support

### MultiPoint

```php
use EduardoRibeiroDev\FilamentLeaflet\Support\Geometry\MultiPoint;

$multiPoint = MultiPoint::make([
    [-46.6333, -23.5505],
    [-46.6433, -23.5605],
]);

$geoJson = $multiPoint->toGeoJson();
$wkt = $multiPoint->toWKT();
```

### MultiLineString

```php
use EduardoRibeiroDev\FilamentLeaflet\Support\Geometry\MultiLineString;

$routes = MultiLineString::make([
    [[-46.6333, -23.5505], [-46.6433, -23.5605]],
    [[-46.7333, -23.6505], [-46.7433, -23.6605]],
]);

$lengthKm = $routes->calculateLength();
```

### MultiPolygon

```php
use EduardoRibeiroDev\FilamentLeaflet\Support\Geometry\MultiPolygon;

$boundaries = MultiPolygon::make([...]);
$areaKm2 = $boundaries->calculateArea();
```

### GeometryCollection

```php
use EduardoRibeiroDev\FilamentLeaflet\Support\Geometry\GeometryCollection;

$collection = GeometryCollection::make()
    ->addPoint(-46.6333, -23.5505)
    ->addLineString([...])
    ->addPolygon([...]);
```

## Spatial Query Methods

| Method | Description |
|--------|-------------|
| `withinRadius($column, $lat, $lng, $km)` | Find records within radius |
| `orderByDistance($column, $lat, $lng)` | Order by distance from point |
| `selectDistance($column, $lat, $lng, $alias)` | Select distance as column |
| `intersects($column, $geometry)` | Find intersecting geometries |
| `within($column, $geometry)` | Find geometries within another |
| `contains($column, $geometry)` | Find geometries that contain another |
| `withinBounds($column, $minLng, $minLat, $maxLng, $maxLat)` | Find within bounding box |
| `buffer($column, $meters, $alias)` | Create buffer around geometry |
| `selectCentroid($column, $alias)` | Get centroid of geometry |
| `selectArea($column, $alias)` | Calculate area (sq meters) |
| `selectLength($column, $alias)` | Calculate length (meters) |
| `simplify($column, $tolerance, $alias)` | Simplify geometry |
| `selectAsGeoJson($column, $alias)` | Convert to GeoJSON |

## Configuration

```php
// config/filament-leaflet-postgis.php

return [
    'default_srid' => 4326,
    'auto_detect_spatial' => true,
    
    'cache' => [
        'enabled' => false,
        'ttl' => 3600,
    ],
    
    'performance' => [
        'max_markers' => 5000,
        'viewport_loading' => true,
        'simplify_tolerance' => 0.0001,
    ],
];
```

## Migration Guide

See [Migrating to PostGIS](docs/migrating-to-postgis.md) for a complete guide on migrating from JSON coordinate storage.

## Database Support

| Database | JSON | PostGIS | Notes |
|----------|------|---------|-------|
| PostgreSQL + PostGIS | ✅ | ✅ | Full support |
| PostgreSQL (no PostGIS) | ✅ | ❌ | JSON only |
| MySQL 8.0+ | ✅ | ⚠️ | Limited spatial functions |
| SQLite | ✅ | ❌ | JSON only |

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

MIT License. See [LICENSE](LICENSE.md) for details.

