# Filament Leaflet

A powerful and elegant Leaflet integration for Filament PHP that makes creating interactive maps a breeze. Build beautiful, feature-rich maps with markers, clusters, shapes, and more using a fluent, expressive API.

## Features

- 🗺️ **Interactive Maps** - Full Leaflet integration with customizable tile layers
- 📍 **Markers & Clusters** - Beautiful markers with popup/tooltip support and intelligent clustering
- 🎨 **Shapes** - Circles, polygons, polylines, rectangles, and circle markers
- 🎯 **Click Events** - Handle clicks on markers, shapes, and the map itself
- 📊 **GeoJSON Support** - Display density maps with custom color schemes
- 🔄 **Model Binding** - Automatically create markers from Eloquent models
- 🎨 **Multiple Tile Layers** - Switch between OpenStreetMap, Satellite, and custom layers
- 💾 **CRUD Operations** - Create markers directly from map clicks
- 🎭 **Customizable** - Extensive configuration options for every element

## Installation

```bash
composer require eduardoribeirodev/filament-leaflet
```

Publish the assets:

```bash
php artisan filament:assets
```

## Table of Contents

- [Quick Start](#quick-start)
- [Map Widget Configuration](#map-widget-configuration)
- [Working with Markers](#working-with-markers)
- [Marker Clusters](#marker-clusters)
- [Shapes](#shapes)
- [Popups and Tooltips](#popups-and-tooltips)
- [Click Actions](#click-actions)
- [Model Integration](#model-integration)
- [GeoJSON Density Maps](#geojson-density-maps)
- [Advanced Configuration](#advanced-configuration)

## Quick Start

Create your first map widget:

```php
namespace App\Filament\Widgets;

use EduardoRibeiroDev\FilamentLeaflet\Widgets\MapWidget;
use EduardoRibeiroDev\FilamentLeaflet\Support\Markers\Marker;

class MyMapWidget extends MapWidget
{
    protected static ?string $heading = 'My Locations';
    
    protected static array $mapCenter = [-23.5505, -46.6333]; // São Paulo
    protected static int $defaultZoom = 12;
    
    protected function getMarkers(): array
    {
        return [
            Marker::make(-23.5505, -46.6333)
                ->title('São Paulo')
                ->popupContent('The largest city in Brazil'),
        ];
    }
}
```

## Map Widget Configuration

### Basic Settings

Configure your map's initial state and behavior:

```php
class MyMapWidget extends MapWidget
{
    // Map heading
    protected static ?string $heading = 'Store Locations';
    
    // Center coordinates [latitude, longitude]
    protected static array $mapCenter = [-14.235, -51.9253];
    
    // Initial zoom level (1-18)
    protected static int $defaultZoom = 4;
    
    // Map height in pixels
    protected static int $mapHeight = 600;
    
    // Zoom configuration
    protected static int $maxZoom = 18;
    protected static int $minZoom = 2;
    
    // Show attribution control
    protected static bool $hasAttributionControl = true;
}
```

### Tile Layers

Choose from multiple tile layer providers or add your own:

```php
use EduardoRibeiroDev\FilamentLeaflet\Enums\TileLayer;

class MyMapWidget extends MapWidget
{
    // Single layer
    protected static array $tileLayersUrl = [TileLayer::OpenStreetMap];
    
    // Multiple layers
    protected static array $tileLayersUrl = [
        TileLayer::OpenStreetMap,
        TileLayer::GoogleSatellite,
        TileLayer::EsriNatGeo,
    ];
    
    // Multiple layers with custom names
    protected static array $tileLayersUrl = [
        'Street Map' => TileLayer::OpenStreetMap,
        'Satellite' => TileLayer::EsriWorldStreetMap,
        'Terrain' => TileLayer::GoogleTerrain,
    ];
    
    // Custom tile server
    protected static array $tileLayersUrl = [
        'Custom' => 'https://{s}.tile.custom.com/{z}/{x}/{y}.png',
    ];
}
```

Available tile layers:
- `TileLayer::OpenStreetMap`
- `TileLayer::GoogleStreets`
- `TileLayer::GoogleSatellite`
- `TileLayer::GoogleHybrid`
- `TileLayer::GoogleTerrain`
- `TileLayer::EsriWorldImagery`
- `TileLayer::EsriWorldStreetMap`
- `TileLayer::EsriNatGeo`
- `TileLayer::CartoPositron`
- `TileLayer::CartoDarkMatter`

## Working with Markers

### Basic Markers

Create markers with various configurations:

```php
use EduardoRibeiroDev\FilamentLeaflet\Support\Markers\Marker;
use EduardoRibeiroDev\FilamentLeaflet\Enums\Color;

protected function getMarkers(): array
{
    return [
        // Simple marker
        Marker::make(-23.5505, -46.6333),
        
        // Marker with title (shows as tooltip and popup title)
        Marker::make(-23.5505, -46.6333)
            ->title('São Paulo'),
        
        // Colored marker
        Marker::make(-23.5505, -46.6333)
            ->blue(), // or ->color(Color::Blue)
        
        // Custom icon
        Marker::make(-23.5505, -46.6333)
            ->icon('https://example.com/icon.png', [32, 32]),
        
        // Draggable marker
        Marker::make(-23.5505, -46.6333)
            ->draggable(),
        
        // Complete marker
        Marker::make(-23.5505, -46.6333)
            ->id('sao-paulo')
            ->title('São Paulo')
            ->red()
            ->popupContent('The largest city in Brazil')
            ->group('cities'),
    ];
}
```

### Marker Colors

Use the built-in color system:

```php
Marker::make($lat, $lng)
    ->blue()      // Blue marker
    ->red()       // Red marker
    ->green()     // Green marker
    ->orange()    // Orange marker
    ->yellow()    // Yellow marker
    ->violet()    // Violet marker
    ->grey()      // Grey marker
    ->black()     // Black marker
    ->gold()      // Gold marker
    ->randomColor(); // Random color

// Or use the Color enum
Marker::make($lat, $lng)
    ->color(Color::Blue);
```

### Markers from Eloquent Models

Automatically create markers from your database records:

```php
use App\Models\Store;

protected function getMarkers(): array
{
    return Store::all()->map(function ($store) {
        return Marker::fromRecord(
            record: $store,
            latColumn: 'latitude',
            lngColumn: 'longitude',
            titleColumn: 'name',
            descriptionColumn: 'description',
            popupFieldsColumns: ['address', 'phone', 'email'],
            color: Color::Blue,
        );
    })->toArray();
}
```

#### JSON Coordinate Storage

If your coordinates are stored as JSON:

```php
// Database structure: coordinates => {"latitude": -23.5505, "longitude": -46.6333}

Marker::fromRecord(
    record: $store,
    jsonColumn: 'coordinates',      // Column containing JSON
    latColumn: 'latitude',           // Key in JSON object
    lngColumn: 'longitude',          // Key in JSON object
    titleColumn: 'name',
);
```

#### Customizing Markers from Records

Use the `mapRecordCallback` to customize each marker:

```php
Marker::fromRecord(
    record: $store,
    latColumn: 'latitude',
    lngColumn: 'longitude',
    mapRecordCallback: function (Marker $marker, Model $record) {
        // Customize based on record data
        if ($record->is_featured) {
            $marker->gold();
        }
        
        if ($record->status === 'closed') {
            $marker->grey();
        }
        
        // Add custom popup fields
        $marker->popupFields([
            'opening_hours' => $record->hours,
            'rating' => $record->rating . ' ⭐',
        ]);
    }
);
```

## Marker Clusters

Group nearby markers into clusters for better performance and cleaner maps:

```php
use EduardoRibeiroDev\FilamentLeaflet\Support\Markers\MarkerCluster;

protected function getMarkers(): array
{
    return [
        MarkerCluster::make([
            Marker::make(-23.5505, -46.6333)->title('Location 1'),
            Marker::make(-23.5515, -46.6343)->title('Location 2'),
            Marker::make(-23.5525, -46.6353)->title('Location 3'),
        ])
        ->blue()
        ->maxClusterRadius(80)
        ->showCoverageOnHover()
        ->spiderfyOnMaxZoom(),
    ];
}
```

### Cluster from Model

Create clusters directly from Eloquent models:

```php
use App\Models\Store;

protected function getMarkers(): array
{
    return [
        MarkerCluster::fromModel(
            model: Store::class,
            latColumn: 'latitude',
            lngColumn: 'longitude',
            titleColumn: 'name',
            descriptionColumn: 'description',
            popupFieldsColumns: ['address', 'phone'],
            color: Color::Green,
        )
        ->maxClusterRadius(60)
        ->disableClusteringAtZoom(15),
    ];
}
```

### Cluster with Query Modification

Filter and customize the query used to load markers:

```php
MarkerCluster::fromModel(
    model: Store::class,
    latColumn: 'latitude',
    lngColumn: 'longitude',
    modifyQueryCallback: function ($query) {
        return $query
            ->where('status', 'active')
            ->where('city', 'São Paulo')
            ->orderBy('name');
    },
    mapRecordCallback: function (Marker $marker, Model $record) {
        // Customize each marker
        if ($record->isPremium()) {
            $marker->gold()->icon('/images/premium-icon.png');
        }
    }
);
```

### Cluster Configuration

Fine-tune cluster behavior:

```php
MarkerCluster::make($markers)
    ->maxClusterRadius(80)              // Cluster radius in pixels
    ->showCoverageOnHover(true)         // Show cluster coverage on hover
    ->zoomToBoundsOnClick(true)         // Zoom to cluster bounds on click
    ->spiderfyOnMaxZoom(true)           // Spread markers at max zoom
    ->removeOutsideVisibleBounds(true)  // Remove markers outside viewport
    ->disableClusteringAtZoom(15)       // Disable clustering at zoom level
    ->options([                          // Additional Leaflet options
        'animate' => true,
        'animateAddingMarkers' => true,
    ]);
```

## Shapes

Draw various geometric shapes on your map:

### Circles

Circles with radius in various units:

```php
use EduardoRibeiroDev\FilamentLeaflet\Support\Shapes\Circle;

protected function getShapes(): array
{
    return [
        // Radius in meters (default)
        Circle::make(-23.5505, -46.6333)
            ->radius(5000)
            ->blue()
            ->fillBlue()
            ->title('Coverage Area'),
        
        // Radius in kilometers
        Circle::make(-23.5505, -46.6333)
            ->radiusInKilometers(5)
            ->red()
            ->fillOpacity(0.3),
        
        // Radius in miles
        Circle::make(-23.5505, -46.6333)
            ->radiusInMiles(3)
            ->green(),
        
        // Radius in feet
        Circle::make(-23.5505, -46.6333)
            ->radiusInFeet(10000)
            ->orange(),
        
        // Styled circle
        Circle::make(-23.5505, -46.6333)
            ->radiusInKilometers(10)
            ->color(Color::Blue)      // Border color
            ->fillColor(Color::Blue)  // Fill color
            ->weight(3)               // Border width
            ->opacity(0.8)            // Border opacity
            ->fillOpacity(0.2)        // Fill opacity
            ->dashArray('5, 10')      // Dashed border
            ->popupContent('10km radius coverage'),
    ];
}
```

### Circle Markers

Small circles with pixel-based radius (like markers but circular):

```php
use EduardoRibeiroDev\FilamentLeaflet\Support\Shapes\CircleMarker;

CircleMarker::make(-23.5505, -46.6333)
    ->radius(15)           // Radius in pixels
    ->red()
    ->fillRed()
    ->weight(2)
    ->title('Point of Interest');
```

### Polygons

Draw custom polygons:

```php
use EduardoRibeiroDev\FilamentLeaflet\Support\Shapes\Polygon;

// Define a polygon area
Polygon::make([
    [-23.5505, -46.6333],
    [-23.5515, -46.6343],
    [-23.5525, -46.6323],
    [-23.5505, -46.6333], // Close the polygon
])
->green()
->fillGreen()
->fillOpacity(0.3)
->title('Delivery Zone')
->popupContent('We deliver to this area');

// Or build point by point
Polygon::make()
    ->addPoint(-23.5505, -46.6333)
    ->addPoint(-23.5515, -46.6343)
    ->addPoint(-23.5525, -46.6323)
    ->addPoint(-23.5505, -46.6333)
    ->blue();
```

### Polylines

Draw lines connecting multiple points:

```php
use EduardoRibeiroDev\FilamentLeaflet\Support\Shapes\Polyline;

// Route or path
Polyline::make([
    [-23.5505, -46.6333],
    [-23.5515, -46.6343],
    [-23.5525, -46.6353],
    [-23.5535, -46.6363],
])
->blue()
->weight(4)
->opacity(0.7)
->dashArray('10, 5')      // Dashed line
->smoothFactor(1.5)       // Smooth curves
->title('Delivery Route');

// Or build incrementally
Polyline::make()
    ->addPoint(-23.5505, -46.6333)
    ->addPoint(-23.5515, -46.6343)
    ->addPoint(-23.5525, -46.6353)
    ->red()
    ->weight(3);
```

### Rectangles

Draw rectangular bounds:

```php
use EduardoRibeiroDev\FilamentLeaflet\Support\Shapes\Rectangle;

// Using corner coordinates
Rectangle::make(
    [-23.5505, -46.6333],  // Southwest corner
    [-23.5525, -46.6353]   // Northeast corner
)
->orange()
->fillOrange()
->fillOpacity(0.2)
->title('Restricted Area');

// Alternative syntax
Rectangle::makeFromCoordinates(
    -23.5505, -46.6333,    // Southwest lat, lng
    -23.5525, -46.6353     // Northeast lat, lng
)
->red();
```

### Shape Styling

All shapes support comprehensive styling options:

```php
Circle::make(-23.5505, -46.6333)
    ->radius(5000)
    
    // Border styling
    ->color(Color::Blue)        // Border color
    ->weight(3)                 // Border width in pixels
    ->opacity(0.8)              // Border opacity (0-1)
    ->dashArray('5, 10')        // Dashed border pattern
    
    // Fill styling
    ->fillColor(Color::Green)   // Fill color
    ->fillOpacity(0.3)          // Fill opacity (0-1)
    
    // Custom options
    ->options([
        'className' => 'custom-shape',
        'interactive' => true,
    ]);
```

## Popups and Tooltips

### Tooltips

Tooltips appear on hover:

```php
Marker::make(-23.5505, -46.6333)
    ->tooltip(
        content: 'São Paulo City',
        permanent: false,           // Always visible
        direction: 'top',           // 'top', 'bottom', 'left', 'right', 'auto'
        options: [
            'offset' => [0, -20],
            'className' => 'custom-tooltip',
        ]
    );

// Or use individual methods
Marker::make(-23.5505, -46.6333)
    ->tooltipContent('São Paulo')
    ->tooltipPermanent(true)
    ->tooltipDirection('top')
    ->tooltipOptions(['opacity' => 0.9]);
```

### Popups

Popups appear on click and support rich content:

```php
Marker::make(-23.5505, -46.6333)
    ->popupTitle('Store Location')
    ->popupContent('Visit our main store in downtown São Paulo')
    ->popupFields([
        'address' => '123 Main Street',
        'phone' => '+55 11 1234-5678',
        'email' => 'contact@store.com',
        'opening_hours' => 'Mon-Fri: 9AM-6PM',
    ])
    ->popupOptions([
        'maxWidth' => 300,
        'className' => 'custom-popup',
    ]);

// Or use the shorthand
Marker::make(-23.5505, -46.6333)
    ->popup(
        content: 'Store description',
        fields: [
            'address' => '123 Main Street',
            'phone' => '+55 11 1234-5678',
        ],
        options: ['maxWidth' => 300]
    );
```

### How Popup Fields Work

The `popupFields()` method automatically formats your data into a clean, structured display:

```php
Marker::make(-23.5505, -46.6333)
    ->popupFields([
        'store' => 'Pizza Palace',
        'phone_number' => '+55 11 1234-5678',
        'opening_hours' => '10AM - 10PM',
    ]);
```

This generates HTML like:
```html
<p><span class="field-label">Store:</span> Pizza Palace</p>
<p><span class="field-label">Phone Number:</span> +55 11 1234-5678</p>
<p><span class="field-label">Opening Hours:</span> 10AM - 10PM</p>
```

The keys are automatically:
- Converted to title case
- Underscores replaced with spaces
- Translated using Laravel's `__()` helper

Both keys and values are translated, so you can use translation keys:

```php
->popupFields([
    'store.name' => $store->name,
    'store.contact' => $store->phone,
])
```

### Combining Title, Tooltip, and Popup

```php
Marker::make(-23.5505, -46.6333)
    ->title('Pizza Palace')              // Sets both tooltip and popup title
    ->popupContent('Best pizza in town')
    ->popupFields([
        'address' => '123 Main St',
        'phone' => '+55 11 1234-5678',
        'rating' => '4.5 ⭐',
    ]);
```

## Click Actions

Handle user interactions with layers:

### Marker Click Actions

```php
use Filament\Notifications\Notification;

Marker::make(-23.5505, -46.6333)
    ->title('Interactive Marker')
    ->onClick(function (Marker $marker) {
        Notification::make()
            ->title('Marker Clicked!')
            ->body('You clicked on: ' . $marker->getId())
            ->success()
            ->send();
    });

// Or use the action() method
Marker::make(-23.5505, -46.6333)
    ->action(function (Marker $marker) {
        // Handle click
    });
```

### Shape Click Actions

```php
Circle::make(-23.5505, -46.6333)
    ->radius(5000)
    ->action(function (Circle $circle) {
        Notification::make()
            ->title('Circle clicked')
            ->send();
    });

Polygon::make($coordinates)
    ->action(function (Polygon $polygon) {
        // Handle polygon click
    });
```

### Access Record in Click Actions

When using markers from models, access the record in click actions:

```php
protected function getMarkers(): array
{
    return Store::all()->map(function ($store) {
        return Marker::fromRecord(
            record: $store,
            latColumn: 'latitude',
            lngColumn: 'longitude',
        )->action(function (Marker $marker, Store $record) {
            Notification::make()
                ->title("You clicked: {$record->name}")
                ->body("Address: {$record->address}")
                ->send();
            
            // You can also redirect
            return redirect()->route('stores.show', $record);
        });
    })->toArray();
}
```

### Map Click Handler

Handle clicks on the map itself:

```php
public function onMapClick(float $latitude, float $longitude): void
{
    Notification::make()
        ->title('Map clicked')
        ->body("Coordinates: {$latitude}, {$longitude}")
        ->send();
    
    // Or create a new marker dynamically
    // This will trigger the create modal if $markerModel is set
    parent::onMapClick($latitude, $longitude);
}
```

## Model Integration

### CRUD Operations

Enable creating markers directly from map clicks:

```php
use App\Models\Location;

class LocationMapWidget extends MapWidget
{
    protected static ?string $markerModel = Location::class;
    
    // Column names in your database
    protected static string $latitudeColumnName = 'latitude';
    protected static string $longitudeColumnName = 'longitude';
    
    // For JSON storage
    protected static ?string $jsonCoordinatesColumnName = 'coordinates';
    
    // Form configuration
    protected static int $formColumns = 2;
    
    protected static function getFormComponents(): array
    {
        return [
            TextInput::make('name')
                ->required(),
            
            Select::make('color')
                ->options(Color::class),
            
            Textarea::make('description')
                ->columnSpanFull(),
        ];
    }
}
```

Now when users click the map, a form modal opens to create a new location!

### Using a Resource Form

Integrate with existing Filament resources:

```php
use App\Filament\Resources\Locations\LocationResource;

class LocationMapWidget extends MapWidget
{
    protected static ?string $markerModel = Location::class;
    protected static ?string $markerResource = LocationResource::class;
    
    // The resource's form will be used automatically
}
```

### After Create Hook

```php
protected function afterMarkerCreated(Model $record): void
{
    Notification::make()
        ->title('Location created!')
        ->body("Created: {$record->name}")
        ->success()
        ->send();
    
    // Send email, log activity, etc.
}
```

### Mutate Form Data

Transform data before saving:

```php
protected function mutateFormDataBeforeCreate(array $data): array
{
    $data['user_id'] = auth()->id();
    $data['status'] = 'active';
    
    // Convert coordinates to JSON if needed
    return parent::mutateFormDataBeforeCreate($data);
}
```

### Table Integration

Refresh the map when table actions are performed:

```php
use EduardoRibeiroDev\FilamentLeaflet\Traits\InteractsWithMap;

class ManageLocations extends ManageRecords
{
    use InteractsWithMap;
    
    // Your resource code...
}
```

This automatically:
- Refreshes the map after create/edit/delete actions
- Keeps the map in sync with your table

## GeoJSON Density Maps

Display choropleth maps with custom density data:

```php
class BrazilDensityWidget extends MapWidget
{
    protected static ?string $geoJsonUrl = 'https://example.com/brazil-states.json';
    
    protected static array $geoJsonColors = [
        '#FED976',  // Lightest
        '#FEB24C',
        '#FD8D3C',
        '#FC4E2A',
        '#E31A1C',
        '#BD0026',
        '#800026',  // Darkest
    ];
    
    public function getGeoJsonData(): array
    {
        // Return density data for each region
        return [
            'SP' => 166.23,  // São Paulo
            'RJ' => 365.23,  // Rio de Janeiro
            'MG' => 33.41,   // Minas Gerais
            // ... more states
        ];
    }
    
    public static function getGeoJsonTooltip(): string
    {
        return <<<HTML
            <h4>{state}</h4>
            <b>Population Density: {density} per km²</b>
        HTML;
    }
}
```

The colors are automatically applied based on data distribution, creating a beautiful density visualization.

## Advanced Configuration

### Custom Styles

Add custom CSS to your map:

```php
public function getCustomStyles(): string
{
    return <<<CSS
        .custom-marker {
            filter: hue-rotate(45deg);
        }
        
        .leaflet-popup-content {
            font-family: 'Inter', sans-serif;
        }
    CSS;
}
```

### Custom Scripts

Execute JavaScript after map initialization:

```php
public function afterMapInit(): string
{
    return <<<JS
        console.log('Map initialized!');
        
        // Add custom controls
        L.control.scale().addTo(map);
    JS;
}

public function getAdditionalScripts(): string
{
    return <<<JS
        // Additional JavaScript code
        function customFunction() {
            // Your code
        }
    JS;
}
```

### Groups and Layers

Organize markers and shapes into groups:

```php
protected function getMarkers(): array
{
    return [
        Marker::make(-23.5505, -46.6333)
            ->group('stores')
            ->title('Store 1'),
        
        Marker::make(-23.5515, -46.6343)
            ->group('stores')
            ->title('Store 2'),
        
        Marker::make(-23.5525, -46.6353)
            ->group('warehouses')
            ->title('Warehouse 1'),
    ];
}
```

### Distance Calculation

Calculate distances between markers:

```php
$marker1 = Marker::make(-23.5505, -46.6333);
$marker2 = Marker::make(-22.9068, -43.1729);

$distance = $marker1->distanceTo($marker2); // Distance in kilometers
```

### Coordinate Validation

```php
$marker = Marker::make($lat, $lng)
    ->validate(); // Throws exception if coordinates are invalid
```

### Map Options

Fine-tune Leaflet behavior:

```php
public static function getMapOptions(): array
{
    return [
        'scrollWheelZoom' => true,
        'doubleClickZoom' => true,
        'dragging' => true,
        'zoomControl' => true,
        'attributionControl' => false,
        'touchZoom' => true,
        'boxZoom' => true,
        'keyboard' => true,
    ];
}
```

### Complete Example

Here's a comprehensive example combining multiple features:

```php
namespace App\Filament\Widgets;

use App\Models\Store;
use EduardoRibeiroDev\FilamentLeaflet\Widgets\MapWidget;
use EduardoRibeiroDev\FilamentLeaflet\Support\Markers\Marker;
use EduardoRibeiroDev\FilamentLeaflet\Support\Markers\MarkerCluster;
use EduardoRibeiroDev\FilamentLeaflet\Support\Shapes\Circle;
use EduardoRibeiroDev\FilamentLeaflet\Support\Shapes\Polygon;
use EduardoRibeiroDev\FilamentLeaflet\Enums\Color;
use EduardoRibeiroDev\FilamentLeaflet\Enums\TileLayer;
use Filament\Notifications\Notification;

class StoreMapWidget extends MapWidget
{
    protected static ?string $heading = 'Store Network';
    protected static array $mapCenter = [-23.5505, -46.6333];
    protected static int $defaultZoom = 11;
    protected static int $mapHeight = 700;
    
    protected static array $tileLayersUrl = [
        'Street' => TileLayer::OpenStreetMap,
        'Satellite' => TileLayer::GoogleSatellite,
    ];
    
    // Enable marker creation
    protected static ?string $markerModel = Store::class;
    protected static string $latitudeColumnName = 'latitude';
    protected static string $longitudeColumnName = 'longitude';
    
    protected function getMarkers(): array
    {
        return [
            // Clustered stores
            MarkerCluster::fromModel(
                model: Store::class,
                latColumn: 'latitude',
                lngColumn: 'longitude',
                titleColumn: 'name',
                descriptionColumn: 'description',
                popupFieldsColumns: ['address', 'phone', 'manager'],
                color: Color::Blue,
                modifyQueryCallback: fn($q) => $q->where('status', 'active'),
                mapRecordCallback: function (Marker $marker, $record) {
                    if ($record->is_flagship) {
                        $marker->gold()->icon('/images/flagship-icon.png');
                    }
                    
                    $marker->action(function (Marker $m, $r) {
                        Notification::make()
                            ->title("Store: {$r->name}")
                            ->success()
                            ->send();
                    });
                }
            )
            ->maxClusterRadius(60)
            ->spiderfyOnMaxZoom(),
            
            // Featured location
            Marker::make(-23.5505, -46.6333)
                ->title('Headquarters')
                ->red()
                ->icon('/images/hq-icon.png', [40, 40])
                ->popupContent('Our main office')
                ->popupFields([
                    'address' => 'Av. Paulista, 1000',
                    'phone' => '+55 11 1234-5678',
                    'opening_hours' => 'Mon-Fri: 9AM-6PM',
                ]),
        ];
    }
    
    protected function getShapes(): array
    {
        return [
            // Delivery radius
            Circle::make(-23.5505, -46.6333)
                ->radiusInKilometers(5)
                ->blue()
                ->fillBlue()
                ->fillOpacity(0.1)
                ->weight(2)
                ->dashArray('5, 5')
                ->popupContent('5km delivery radius'),
            
            // Exclusive zone
            Polygon::make([
                [-23.5505, -46.6333],
                [-23.5605, -46.6433],
                [-23.5705, -46.6333],
                [-23.5505, -46.6333],
            ])
            ->green()
            ->fillGreen()
            ->fillOpacity(0.2)
            ->popupContent('VIP delivery zone')
            ->action(function () {
                Notification::make()
                    ->title('VIP Zone')
                    ->body('Exclusive delivery area')
                    ->send();
            }),
        ];
    }
    
    protected static function getFormComponents(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            
            Select::make('type')
                ->options([
                    'retail' => 'Retail Store',
                    'warehouse' => 'Warehouse',
                    'office' => 'Office',
                ])
                ->required(),
            
            Select::make('color')
                ->options(Color::class),
            
            TextInput::make('phone')
                ->tel(),
            
            Textarea::make('description')
                ->columnSpanFull()
                ->maxLength(500),
        ];
    }
    
    protected function afterMarkerCreated(Model $record): void
    {
        Notification::make()
            ->title('Store Created!')
            ->body("New store '{$record->name}' added to the map")
            ->success()
            ->duration(5000)
            ->send();
    }
    
    public function onMapClick(float $latitude, float $longitude): void
    {
        // Custom logic before opening create form
        logger("Map clicked at: {$latitude}, {$longitude}");
        
        parent::onMapClick($latitude, $longitude);
    }
}
```

## API Reference

### MapWidget Methods

| Method | Description |
|--------|-------------|
| `getHeading()` | Returns the widget heading |
| `getMarkers()` | Returns array of markers to display |
| `getShapes()` | Returns array of shapes to display |
| `getLayers()` | Returns combined markers and shapes |
| `onMapClick($lat, $lng)` | Handles map click events |
| `onLayerClick($layerId)` | Handles layer click events |
| `refreshMap()` | Manually refresh the map |
| `afterMarkerCreated($record)` | Hook after marker creation |
| `mutateFormDataBeforeCreate($data)` | Transform form data before save |

### Marker Methods

| Method | Description |
|--------|-------------|
| `make($lat, $lng)` | Create a new marker |
| `fromRecord()` | Create marker from Eloquent model |
| `id($id)` | Set marker ID |
| `title($title)` | Set title (tooltip & popup) |
| `color($color)` | Set marker color |
| `icon($url, $size)` | Set custom icon |
| `draggable($bool)` | Make marker draggable |
| `group($group)` | Assign to group |
| `popup($content, $fields, $options)` | Configure popup |
| `tooltip($content, $permanent, $direction, $options)` | Configure tooltip |
| `action($callback)` | Set click handler |
| `distanceTo($marker)` | Calculate distance to another marker |
| `validate()` | Validate coordinates |

### MarkerCluster Methods

| Method | Description |
|--------|-------------|
| `make($markers)` | Create cluster with markers |
| `fromModel()` | Create cluster from Eloquent model |
| `marker($marker)` | Add single marker |
| `markers($array)` | Add multiple markers |
| `maxClusterRadius($pixels)` | Set cluster radius |
| `showCoverageOnHover($bool)` | Show coverage on hover |
| `zoomToBoundsOnClick($bool)` | Zoom to bounds on click |
| `spiderfyOnMaxZoom($bool)` | Spread markers at max zoom |
| `disableClusteringAtZoom($level)` | Disable clustering at zoom level |
| `modifyQueryUsing($callback)` | Modify database query |
| `mapRecordUsing($callback)` | Customize marker creation |

### Shape Methods (All Shapes)

| Method | Description |
|--------|-------------|
| `color($color)` | Set border color |
| `fillColor($color)` | Set fill color |
| `weight($pixels)` | Set border width |
| `opacity($value)` | Set border opacity (0-1) |
| `fillOpacity($value)` | Set fill opacity (0-1) |
| `dashArray($pattern)` | Set dash pattern |
| `options($array)` | Set custom options |
| `popup($content, $fields, $options)` | Configure popup |
| `tooltip($content, $permanent, $direction, $options)` | Configure tooltip |
| `action($callback)` | Set click handler |
| `group($group)` | Assign to group |

### Circle Specific Methods

| Method | Description |
|--------|-------------|
| `make($lat, $lng)` | Create circle |
| `radius($meters)` | Set radius in meters |
| `radiusInMeters($meters)` | Set radius in meters |
| `radiusInKilometers($km)` | Set radius in kilometers |
| `radiusInMiles($miles)` | Set radius in miles |
| `radiusInFeet($feet)` | Set radius in feet |

### CircleMarker Specific Methods

| Method | Description |
|--------|-------------|
| `make($lat, $lng)` | Create circle marker |
| `radius($pixels)` | Set radius in pixels |

### Polygon/Polyline Specific Methods

| Method | Description |
|--------|-------------|
| `make($coordinates)` | Create with coordinates |
| `addPoint($lat, $lng)` | Add vertex/point |

### Polyline Specific Methods

| Method | Description |
|--------|-------------|
| `smoothFactor($factor)` | Set line smoothing |

### Rectangle Specific Methods

| Method | Description |
|--------|-------------|
| `make($corner1, $corner2)` | Create with corners |
| `makeFromCoordinates($lat1, $lng1, $lat2, $lng2)` | Create with coordinates |

## Color Reference

Available colors for markers and shapes:

- `Color::Blue` / `->blue()` - #3388ff
- `Color::Red` / `->red()` - #f03
- `Color::Green` / `->green()` - #3c3
- `Color::Orange` / `->orange()` - #f80
- `Color::Yellow` / `->yellow()` - #fd0
- `Color::Violet` / `->violet()` - #a0f
- `Color::Grey` / `->grey()` - #666
- `Color::Black` / `->black()` - #000
- `Color::Gold` / `->gold()` - #ffd700

## Tips & Best Practices

### Performance Optimization

1. **Use Marker Clusters** for large datasets:
```php
// Bad: 1000 individual markers
protected function getMarkers(): array
{
    return Store::all()->map(fn($s) => Marker::fromRecord($s))->toArray();
}

// Good: Clustered markers
protected function getMarkers(): array
{
    return [
        MarkerCluster::fromModel(Store::class)
            ->maxClusterRadius(80)
    ];
}
```

2. **Limit data** with query modifications:
```php
MarkerCluster::fromModel(
    model: Store::class,
    modifyQueryCallback: fn($q) => $q->limit(100)->latest()
)
```

3. **Use appropriate zoom levels**:
```php
protected static int $defaultZoom = 12; // City level
protected static int $maxZoom = 18;     // Street level
protected static int $minZoom = 3;      // Country level
```

### User Experience

1. **Provide context** with popups:
```php
Marker::make($lat, $lng)
    ->title('Store Name')
    ->popupContent('Visit our location')
    ->popupFields([
        'address' => '123 Main St',
        'hours' => '9AM-6PM',
        'phone' => '+55 11 1234-5678',
    ]);
```

2. **Use appropriate colors**:
```php
// Status-based coloring
$marker->color(match($store->status) {
    'open' => Color::Green,
    'busy' => Color::Orange,
    'closed' => Color::Red,
    default => Color::Grey,
});
```

3. **Add visual feedback**:
```php
Circle::make($lat, $lng)
    ->radiusInKilometers(5)
    ->blue()
    ->fillBlue()
    ->onMouseOver("this.setStyle({fillOpacity: 0.6})")
    ->onMouseOut("this.setStyle({fillOpacity: 0.3})")
    ->fillOpacity(0.1)  // Subtle fill
    ->dashArray('5, 5'); // Dashed border
```

### Code Organization

1. **Extract complex logic**:
```php
protected function getMarkers(): array
{
    return [
        $this->getStoreMarkers(),
        $this->getWarehouseMarkers(),
    ];
}

private function getStoreMarkers(): MarkerCluster
{
    return MarkerCluster::fromModel(Store::class)
        ->blue()
        ->mapRecordUsing($this->configureStoreMarker(...));
}

private function configureStoreMarker(Marker $marker, Model $store): void
{
    if ($store->is_flagship) {
        $marker->gold()->icon('/images/flagship.png');
    }
    
    $marker->popupFields($store->only(['address', 'phone']));
}
```

2. **Use dedicated widget classes**:
```php
// Good structure
app/Filament/Widgets/
├── Maps/
│   ├── StoreMapWidget.php
│   ├── DeliveryMapWidget.php
│   └── AnalyticsMapWidget.php
```

### Debugging

Enable logging for map interactions:

```php
public function onLayerClick(string $layerId): void
{
    logger("Layer clicked: {$layerId}");
    parent::onLayerClick($layerId);
}

public function onMapClick(float $latitude, float $longitude): void
{
    logger("Map clicked", compact('latitude', 'longitude'));
    parent::onMapClick($latitude, $longitude);
}
```

## Troubleshooting

### Markers not appearing

1. Check coordinate validity:
```php
Marker::make($lat, $lng)->validate(); // Throws exception if invalid
```

2. Verify zoom level:
```php
protected static int $defaultZoom = 12; // Try different values
```

3. Check marker is in bounds:
```php
// Ensure coordinates are visible in your map center/zoom
protected static array $mapCenter = [$your_marker_lat, $your_marker_lng];
```

### Popups not showing

1. Ensure content is set:
```php
$marker->popupContent('Some content'); // Required
```

2. Check for JavaScript errors in browser console

### Cluster not grouping

1. Increase cluster radius:
```php
MarkerCluster::make($markers)
    ->maxClusterRadius(100) // Increase this value
```

2. Check zoom level:
```php
->disableClusteringAtZoom(15) // Clusters won't show at/above this zoom
```

### Form not opening on map click

1. Verify model is set:
```php
protected static ?string $markerModel = YourModel::class;
```

2. Check column names match:
```php
protected static string $latitudeColumnName = 'latitude'; // Must match DB
protected static string $longitudeColumnName = 'longitude';
```

## License

This package is open-sourced software licensed under the MIT license.

## Credits

- Built for [Filament](https://filamentphp.com)
- Uses [Leaflet](https://leafletjs.com) for mapping
- Created by [Eduardo Ribeiro](https://github.com/eduardoribeirodev)

## Support

For issues, questions, or contributions, please visit the [GitHub repository](https://github.com/eduardoribeirodev/filament-leaflet). Don't forget, Jesus loves you ❤️.