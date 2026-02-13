# Migrating from JSON to PostGIS

This guide walks you through migrating from simple JSON coordinate storage to PostGIS geometry columns for better spatial query support.

## Prerequisites

### 1. PostgreSQL with PostGIS Extension

```sql
-- Check if PostGIS is available
SELECT name, default_version, installed_version 
FROM pg_available_extensions 
WHERE name = 'postgis';

-- Enable PostGIS (requires superuser)
CREATE EXTENSION IF NOT EXISTS postgis;

-- Verify installation
SELECT PostGIS_Version();
```

### 2. Update Your .env

```env
DB_CONNECTION=pgsql
DB_HOST=your-host
DB_PORT=5432
DB_DATABASE=your_database
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

## Migration Steps

### Step 1: Create a Migration to Add Geometry Column

```bash
php artisan make:migration add_geometry_column_to_infrastructures
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add the geometry column
        Schema::table('infrastructures', function (Blueprint $table) {
            // For simple points:
            $table->geometry('coordinates', 'POINT', 4326)->nullable();
            
            // For lines:
            // $table->geometry('path', 'LINESTRING', 4326)->nullable();
            
            // For areas:
            // $table->geometry('boundary', 'POLYGON', 4326)->nullable();
            
            // For any geometry type:
            // $table->geometry('geometry', 'GEOMETRY', 4326)->nullable();
        });

        // Migrate existing JSON data to geometry column
        DB::statement("
            UPDATE infrastructures 
            SET coordinates = ST_SetSRID(
                ST_MakePoint(
                    CAST(location->>'lng' AS FLOAT),
                    CAST(location->>'lat' AS FLOAT)
                ), 
                4326
            )
            WHERE location IS NOT NULL
            AND location->>'lat' IS NOT NULL
            AND location->>'lng' IS NOT NULL
        ");

        // Create spatial index for performance
        DB::statement('CREATE INDEX idx_infrastructures_coordinates 
                       ON infrastructures USING GIST (coordinates)');
    }

    public function down(): void
    {
        Schema::table('infrastructures', function (Blueprint $table) {
            $table->dropColumn('coordinates');
        });
    }
};
```

### Step 2: Update Your Eloquent Model

```php
<?php

namespace App\Models;

use EduardoRibeiroDev\FilamentLeaflet\Casts\GeometryCast;
use EduardoRibeiroDev\FilamentLeaflet\Database\SpatialScopes;
use Illuminate\Database\Eloquent\Model;

class Infrastructure extends Model
{
    use SpatialScopes;

    protected $fillable = [
        'name',
        'description',
        'type',
        'location',      // Keep JSON for backward compatibility
        'coordinates',   // New PostGIS column
        'subdivision_id',
    ];

    protected function casts(): array
    {
        return [
            'location' => 'json',
            'coordinates' => GeometryCast::class,
            // Or with specific geometry type:
            // 'coordinates' => GeometryCast::class . ':4326,Point',
        ];
    }
}
```

### Step 3: Update Filament Resource

```php
<?php

namespace App\Filament\Resources\Infrastructures\Schemas;

use EduardoRibeiroDev\FilamentLeaflet\Fields\MapPicker;
use EduardoRibeiroDev\FilamentLeaflet\Enums\TileLayer;
use Filament\Schemas\Schema;

class InfrastructureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ... other fields ...
                
                MapPicker::make('coordinates')
                    ->spatialColumn()  // Enable PostGIS mode
                    ->srid(4326)       // WGS84 coordinate system
                    ->storeAsJson(false) // Store as geometry, not JSON
                    ->tileLayersUrl([
                        'Street Map' => TileLayer::OpenStreetMap,
                        'Satellite' => TileLayer::GoogleSatellite,
                    ])
                    ->center(-23.5505, -46.6333)
                    ->zoom(10)
                    ->columnSpanFull(),
            ]);
    }
}
```

### Step 4: Use Spatial Queries

```php
use App\Models\Infrastructure;

// Find nearby infrastructure (within 5km)
$nearby = Infrastructure::withinRadius('coordinates', -23.5505, -46.6333, 5)
    ->get();

// Order by distance
$closest = Infrastructure::orderByDistance('coordinates', -23.5505, -46.6333)
    ->take(10)
    ->get();

// Select distance as a column
$withDistance = Infrastructure::query()
    ->select('*')
    ->selectDistance('coordinates', -23.5505, -46.6333, 'distance_km')
    ->orderBy('distance_km')
    ->get();

// Find within bounding box (viewport filtering)
$inViewport = Infrastructure::withinBounds(
    'coordinates',
    minLng: -47.0,
    minLat: -24.0,
    maxLng: -46.0,
    maxLat: -23.0
)->get();

// Spatial relationships
$intersecting = Infrastructure::intersects('coordinates', $polygon)->get();
$contained = Infrastructure::within('coordinates', $polygon)->get();
```

## Using Multi-Geometry Types

### MultiPoint

```php
use EduardoRibeiroDev\FilamentLeaflet\Support\Geometry\MultiPoint;

// Create from array
$multiPoint = MultiPoint::make([
    [-46.6333, -23.5505],
    [-46.6433, -23.5605],
]);

// Convert to GeoJSON
$geoJson = $multiPoint->toGeoJson();

// Convert to WKT
$wkt = $multiPoint->toWKT();
```

### MultiLineString

```php
use EduardoRibeiroDev\FilamentLeaflet\Support\Geometry\MultiLineString;

$routes = MultiLineString::make([
    [[-46.6333, -23.5505], [-46.6433, -23.5605]],  // Route 1
    [[-46.7333, -23.6505], [-46.7433, -23.6605]],  // Route 2
]);

// Calculate total length
$totalLengthKm = $routes->calculateLength();
```

### MultiPolygon

```php
use EduardoRibeiroDev\FilamentLeaflet\Support\Geometry\MultiPolygon;

$boundaries = MultiPolygon::make([
    [[
        [-46.6, -23.5], [-46.7, -23.5], 
        [-46.7, -23.6], [-46.6, -23.6],
        [-46.6, -23.5]  // Close the ring
    ]],
]);

// Calculate total area
$areaKm2 = $boundaries->calculateArea();
```

### GeometryCollection

```php
use EduardoRibeiroDev\FilamentLeaflet\Support\Geometry\GeometryCollection;

$collection = GeometryCollection::make()
    ->addPoint(-46.6333, -23.5505)
    ->addLineString([[-46.6333, -23.5505], [-46.6433, -23.5605]])
    ->addPolygon([[
        [-46.6, -23.5], [-46.7, -23.5],
        [-46.7, -23.6], [-46.6, -23.5]
    ]]);

$geoJson = $collection->toGeoJson();
```

## Performance Optimization

### Spatial Indexes

```sql
-- Create GIST index (recommended for most cases)
CREATE INDEX idx_infrastructures_coordinates 
ON infrastructures USING GIST (coordinates);

-- Create BRIN index (for time-series data with spatial clustering)
CREATE INDEX idx_infrastructures_coordinates_brin 
ON infrastructures USING BRIN (coordinates) 
WITH (pages_per_range = 128);

-- Analyze table after bulk operations
ANALYZE infrastructures;
```

### Query Optimization Tips

1. **Use `ST_DWithin` instead of `ST_Distance < X`**
   - `ST_DWithin` can use spatial indexes
   - `ST_Distance` requires calculating distance for every row

2. **Filter by bounding box first**
   - Use `&&` operator for index-based bounding box queries
   - Then apply precise spatial predicates

3. **Simplify complex geometries**
   ```php
   ->simplify('boundary', 0.001, 'simplified_boundary')
   ```

4. **Use viewport-based loading**
   ```php
   // Only load markers visible on map
   Infrastructure::withinBounds('coordinates', $bounds['west'], $bounds['south'], 
                                               $bounds['east'], $bounds['north'])
       ->limit(1000)
       ->get();
   ```

### Caching Spatial Queries

```php
// Enable in config/filament-leaflet-postgis.php
'cache' => [
    'enabled' => true,
    'ttl' => 3600, // 1 hour
],

// Or manually cache expensive queries
$nearby = Cache::remember(
    "nearby-{$lat}-{$lng}-{$radius}",
    3600,
    fn () => Infrastructure::withinRadius('coordinates', $lat, $lng, $radius)->get()
);
```

## Backward Compatibility

If you need to maintain both JSON and PostGIS columns during migration:

```php
class Infrastructure extends Model
{
    protected static function booted()
    {
        // Sync coordinates when location changes
        static::saving(function ($model) {
            if ($model->isDirty('location') && $model->location) {
                $model->coordinates = [
                    'type' => 'Point',
                    'coordinates' => [
                        $model->location['lng'],
                        $model->location['lat'],
                    ],
                ];
            }
        });
    }
}
```

## Dropping the JSON Column

After verifying the migration is successful:

```php
<?php

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infrastructures', function (Blueprint $table) {
            $table->dropColumn('location');
        });
        
        // Rename coordinates to location if desired
        Schema::table('infrastructures', function (Blueprint $table) {
            $table->renameColumn('coordinates', 'location');
        });
    }
};
```

## Troubleshooting

### "function st_geomfromgeojson does not exist"

PostGIS extension is not installed:
```sql
CREATE EXTENSION IF NOT EXISTS postgis;
```

### "geometry SRID (0) does not match column SRID (4326)"

Always specify SRID when creating geometries:
```sql
ST_SetSRID(ST_MakePoint(lng, lat), 4326)
```

### Poor query performance

1. Check for spatial index:
   ```sql
   SELECT * FROM pg_indexes WHERE tablename = 'infrastructures';
   ```

2. Run ANALYZE:
   ```sql
   ANALYZE infrastructures;
   ```

3. Check query plan:
   ```sql
   EXPLAIN ANALYZE SELECT * FROM infrastructures 
   WHERE ST_DWithin(coordinates, ST_SetSRID(ST_MakePoint(-46.6333, -23.5505), 4326), 5000);
   ```

### Memory issues with large datasets

Use pagination and viewport filtering:
```php
Infrastructure::withinBounds('coordinates', ...$bounds)
    ->simplify('coordinates', 0.001)
    ->paginate(100);
```

