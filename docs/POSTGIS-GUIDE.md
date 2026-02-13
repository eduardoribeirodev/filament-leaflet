# PostGIS Integration Guide

This guide explains how to use the PostGIS spatial features in your Laravel application.

## Prerequisites

1. PostgreSQL with PostGIS extension
2. PHP 8.1+
3. Laravel 12+

## Installation

### 1. Enable PostGIS Extension

```sql
CREATE EXTENSION IF NOT EXISTS postgis;
```

Or via Laravel migration:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
    }
};
```

### 2. Configure Database

Ensure your `.env` is configured for PostgreSQL:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

## Migration Examples

### Creating Spatial Columns

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            
            // Option 1: JSON column (works with any database)
            $table->json('location')->nullable();
            
            // Option 2: Native PostGIS geometry (PostgreSQL only)
            // Use raw SQL for PostGIS types
            $table->timestamps();
        });

        // Add PostGIS geometry column
        DB::statement("ALTER TABLE locations ADD COLUMN geom GEOMETRY(Point, 4326)");
        
        // Create spatial index
        DB::statement("CREATE INDEX locations_geom_idx ON locations USING GIST (geom)");
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
```

### Various Geometry Types

```php
// Point
DB::statement("ALTER TABLE {$table} ADD COLUMN point GEOMETRY(Point, 4326)");

// LineString
DB::statement("ALTER TABLE {$table} ADD COLUMN line GEOMETRY(LineString, 4326)");

// Polygon
DB::statement("ALTER TABLE {$table} ADD COLUMN polygon GEOMETRY(Polygon, 4326)");

// MultiPoint
DB::statement("ALTER TABLE {$table} ADD COLUMN multi_point GEOMETRY(MultiPoint, 4326)");

// MultiPolygon
DB::statement("ALTER TABLE {$table} ADD COLUMN multi_polygon GEOMETRY(MultiPolygon, 4326)");

// GeometryCollection
DB::statement("ALTER TABLE {$table} ADD COLUMN geom_collection GEOMETRY(GeometryCollection, 4326)");

// Geography (for more accurate distance calculations on large areas)
DB::statement("ALTER TABLE {$table} ADD COLUMN geog GEOGRAPHY(Point, 4326)");
```

## Model Configuration

### Using the HasSpatialColumns Trait

```php
<?php

namespace App\Models;

use App\Support\Spatial\HasSpatialColumns;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasSpatialColumns;

    protected $fillable = ['name', 'location', 'geom'];

    /**
     * Define spatial columns for automatic conversion.
     */
    protected array $spatialColumns = ['geom'];

    /**
     * Cast JSON location column.
     */
    protected function casts(): array
    {
        return [
            'location' => 'array',
        ];
    }
}
```

### Using PostGIS Geometry Cast

```php
<?php

namespace App\Models;

use App\Support\Spatial\Casts\PostGISGeometry;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected function casts(): array
    {
        return [
            'geom' => PostGISGeometry::class,
            'location' => 'array',
        ];
    }
}
```

## Querying Spatial Data

### Basic Queries

```php
use App\Models\Location;
use Illuminate\Support\Facades\DB;

// Find within radius (native PostGIS)
$nearby = Location::whereRaw(
    "ST_DWithin(geom::geography, ST_MakePoint(?, ?)::geography, ?)",
    [$lng, $lat, $radiusInMeters]
)->get();

// Find within bounding box
$inBounds = Location::whereRaw(
    "ST_Within(geom, ST_MakeEnvelope(?, ?, ?, ?, 4326))",
    [$minLng, $minLat, $maxLng, $maxLat]
)->get();

// Order by distance
$ordered = Location::select('*')
    ->selectRaw("ST_Distance(geom::geography, ST_MakePoint(?, ?)::geography) as distance", [$lng, $lat])
    ->orderBy('distance')
    ->get();

// Find intersecting polygons
$intersecting = Location::whereRaw(
    "ST_Intersects(polygon, ST_GeomFromGeoJSON(?))",
    [json_encode($geoJson)]
)->get();
```

### Using Query Builder Macros

After registering macros via `SpatialQueryBuilder::registerMacros()`:

```php
// Within radius (km)
$nearby = Location::query()
    ->withinRadius('location', $lat, $lng, 10)
    ->get();

// Within bounds
$inBounds = Location::query()
    ->withinBounds('location', $minLat, $maxLat, $minLng, $maxLng)
    ->get();

// Order by distance
$ordered = Location::query()
    ->orderByDistance('location', $lat, $lng)
    ->get();

// With distance column
$withDistance = Location::query()
    ->withDistance('location', $lat, $lng, 'distance_km')
    ->get();
```

## Raw PostGIS Functions

### Geometry Creation

```php
// Create point from coordinates
DB::raw("ST_SetSRID(ST_MakePoint($lng, $lat), 4326)")

// Create point from GeoJSON
DB::raw("ST_GeomFromGeoJSON('$geoJson')")

// Create point from WKT
DB::raw("ST_GeomFromText('POINT($lng $lat)', 4326)")

// Create polygon from coordinates
$coords = "-46.63 -23.55, -46.62 -23.55, -46.62 -23.54, -46.63 -23.54, -46.63 -23.55";
DB::raw("ST_GeomFromText('POLYGON(($coords))', 4326)")
```

### Spatial Relationships

```php
// Contains
DB::raw("ST_Contains(polygon, point)")

// Within
DB::raw("ST_Within(point, polygon)")

// Intersects
DB::raw("ST_Intersects(geom1, geom2)")

// Distance
DB::raw("ST_Distance(geom1::geography, geom2::geography)")

// DWithin (within distance)
DB::raw("ST_DWithin(geom1::geography, geom2::geography, $meters)")
```

### Geometry Operations

```php
// Centroid
DB::raw("ST_Centroid(polygon)")

// Buffer (in meters, using geography)
DB::raw("ST_Buffer(geom::geography, $meters)::geometry")

// Union
DB::raw("ST_Union(geom1, geom2)")

// Intersection
DB::raw("ST_Intersection(geom1, geom2)")

// Simplify
DB::raw("ST_Simplify(geom, $tolerance)")

// Area (in square meters)
DB::raw("ST_Area(polygon::geography)")

// Length (in meters)
DB::raw("ST_Length(line::geography)")
```

### Geometry Conversion

```php
// To GeoJSON
DB::raw("ST_AsGeoJSON(geom)")

// To WKT
DB::raw("ST_AsText(geom)")

// To EWKT (with SRID)
DB::raw("ST_AsEWKT(geom)")

// Get coordinates
DB::raw("ST_X(point)")  // longitude
DB::raw("ST_Y(point)")  // latitude

// Get bounding box
DB::raw("ST_Extent(geom)")
```

## Inserting Spatial Data

### From Coordinates

```php
$location = new Location();
$location->name = 'Test Location';

// Using JSON column
$location->location = ['lat' => -23.55, 'lng' => -46.63];

// Using native geometry
$location->geom = DB::raw("ST_SetSRID(ST_MakePoint(-46.63, -23.55), 4326)");

$location->save();
```

### From GeoJSON

```php
$geoJson = [
    'type' => 'Point',
    'coordinates' => [-46.63, -23.55],
];

$location = Location::create([
    'name' => 'Test',
    'geom' => DB::raw("ST_GeomFromGeoJSON('" . json_encode($geoJson) . "')"),
]);
```

### From WKT

```php
$location = Location::create([
    'name' => 'Test',
    'geom' => DB::raw("ST_GeomFromText('POINT(-46.63 -23.55)', 4326)"),
]);
```

## Performance Optimization

### Spatial Indexes

```php
// GiST index (most common for PostGIS)
DB::statement("CREATE INDEX idx_geom ON locations USING GIST (geom)");

// SP-GiST index (for certain use cases)
DB::statement("CREATE INDEX idx_geom ON locations USING SPGIST (geom)");

// BRIN index (for large, naturally ordered data)
DB::statement("CREATE INDEX idx_geom ON locations USING BRIN (geom)");

// Index on JSON geometry (functional index)
DB::statement("
    CREATE INDEX idx_location_json ON locations USING GIST (
        ST_SetSRID(ST_MakePoint(
            (location->>'lng')::float,
            (location->>'lat')::float
        ), 4326)
    )
");
```

### Analyzing Queries

```php
// Check query plan
$plan = DB::select("EXPLAIN ANALYZE " . $query->toSql(), $query->getBindings());

// Check index usage
$stats = DB::select("
    SELECT 
        schemaname, tablename, indexname, 
        idx_scan, idx_tup_read, idx_tup_fetch
    FROM pg_stat_user_indexes
    WHERE tablename = 'locations'
");
```

### Clustering Data

```php
// Cluster table by spatial index for better performance
DB::statement("CLUSTER locations USING locations_geom_idx");
```

## Geography vs Geometry

### Geometry (Planar)
- Uses Cartesian coordinates
- Faster calculations
- Good for small areas
- Distance in coordinate units (degrees)

### Geography (Spherical)
- Uses spherical calculations
- More accurate for large distances
- Slower calculations
- Distance in meters

```php
// Geometry: fast but less accurate for large areas
$distance = DB::raw("ST_Distance(geom1, geom2)");  // Returns degrees

// Geography: slower but accurate globally
$distance = DB::raw("ST_Distance(geom1::geography, geom2::geography)");  // Returns meters

// Tip: Cast to geography for distance calculations
$nearby = Location::whereRaw(
    "ST_DWithin(geom::geography, ST_MakePoint(?, ?)::geography, ?)",
    [$lng, $lat, $radiusMeters]
)->get();
```

## Common Patterns

### Store Both JSON and Native Geometry

For maximum flexibility, store both formats:

```php
class Location extends Model
{
    protected static function booted(): void
    {
        static::saving(function ($model) {
            // Sync JSON to native geometry
            if ($model->isDirty('location') && $model->location) {
                $model->geom = DB::raw(sprintf(
                    "ST_SetSRID(ST_MakePoint(%f, %f), 4326)",
                    $model->location['lng'],
                    $model->location['lat']
                ));
            }
        });
    }
}
```

### Scope for Common Queries

```php
class Location extends Model
{
    public function scopeNearby($query, float $lat, float $lng, float $km)
    {
        $meters = $km * 1000;
        
        return $query->whereRaw(
            "ST_DWithin(geom::geography, ST_MakePoint(?, ?)::geography, ?)",
            [$lng, $lat, $meters]
        );
    }

    public function scopeInBounds($query, array $bounds)
    {
        return $query->whereRaw(
            "ST_Within(geom, ST_MakeEnvelope(?, ?, ?, ?, 4326))",
            [$bounds['minLng'], $bounds['minLat'], $bounds['maxLng'], $bounds['maxLat']]
        );
    }

    public function scopeOrderByDistanceFrom($query, float $lat, float $lng)
    {
        return $query
            ->selectRaw("*, ST_Distance(geom::geography, ST_MakePoint(?, ?)::geography) as distance", [$lng, $lat])
            ->orderBy('distance');
    }
}

// Usage
$nearby = Location::nearby(-23.55, -46.63, 10)->get();
$inBounds = Location::inBounds($bounds)->get();
$ordered = Location::orderByDistanceFrom(-23.55, -46.63)->limit(10)->get();
```

## Troubleshooting

### "function st_xxx does not exist"

PostGIS extension not installed:
```sql
CREATE EXTENSION IF NOT EXISTS postgis;
```

### Slow spatial queries

1. Ensure spatial index exists
2. Use `EXPLAIN ANALYZE` to check query plan
3. Consider using geography for distance queries
4. Use `ST_DWithin` instead of `ST_Distance < x`

### SRID mismatch errors

Ensure all geometries use the same SRID (usually 4326):
```sql
SELECT UpdateGeometrySRID('locations', 'geom', 4326);
```

### Memory issues with large results

Use viewport loading and pagination:
```php
$loader = ViewportLoader::make(Location::class)
    ->withinBounds($bounds)
    ->limit(1000)
    ->cluster(true);
```

