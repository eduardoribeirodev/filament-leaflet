# Filament Leaflet - Documentation

Complete documentation for the Filament Leaflet package, a powerful integration of Leaflet maps with FilamentPHP.

---

## Table of Contents

- [Installation](#installation)
- [Getting Started](#getting-started)
- [MapWidget Configuration](#mapwidget-configuration)
- [Working with Markers](#working-with-markers)
- [GeoJSON & Density Maps](#geojson--density-maps)
- [Interactive Features](#interactive-features)
- [Events & Actions](#events--actions)
- [Advanced Usage](#advanced-usage)
- [API Reference](#api-reference)

---

## Installation

Install the package via Composer:

```bash
composer require eduardoribeirodev/filament-leaflet
```

Publish the assets:

```bash
php artisan vendor:publish --tag=filament-leaflet-assets
```

---

## Getting Started

### Creating a Basic Map Widget

Create a new map widget by extending the `MapWidget` class:

```php
<?php

namespace App\Filament\Widgets;

use EduardoRibeiroDev\FilamentLeaflet\Widgets\MapWidget;
use EduardoRibeiroDev\FilamentLeaflet\Support\Marker;

class MyMapWidget extends MapWidget
{
    protected static ?string $heading = 'My Store Locations';
    
    protected static array $mapCenter = [-23.5505, -46.6333]; // São Paulo
    protected static int $defaultZoom = 12;
    protected static int $mapHeight = 600;
    
    public function getMarkers(): array
    {
        return [
            Marker::make(-23.5505, -46.6333)
                ->label('Store 1')
                ->blue(),
        ];
    }
}
```

---

## MapWidget Configuration

### Map Settings

Configure the basic map properties:

```php
class MyMapWidget extends MapWidget
{
    // Map center coordinates [latitude, longitude]
    protected static array $mapCenter = [-14.235, -51.9253];
    
    // Initial zoom level (1-18)
    protected static int $defaultZoom = 4;
    
    // Map height in pixels
    protected static int $mapHeight = 504;
    
    // Show/hide attribution control
    protected static bool $hasAttributionControl = false;
    
    // Tile layer URL (use different map styles)
    protected static string $tileLayerUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    
    // Zoom limits
    protected static int $maxZoom = 18;
    protected static int $minZoom = 2;
}
```

### Alternative Tile Layers

Use different map styles by changing the tile layer URL:

```php
// Dark theme
protected static string $tileLayerUrl = 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png';

// Satellite view
protected static string $tileLayerUrl = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';

// Watercolor style
protected static string $tileLayerUrl = 'https://stamen-tiles-{s}.a.ssl.fastly.net/watercolor/{z}/{x}/{y}.jpg';
```

### Interactive Map Options

Customize map interactions:

```php
public static function getMapOptions(): array
{
    return [
        'scrollWheelZoom' => true,      // Zoom with mouse wheel
        'doubleClickZoom' => true,      // Zoom with double click
        'dragging' => true,              // Allow map dragging
        'zoomControl' => true,           // Show zoom buttons
        'attributionControl' => false,   // Show attribution
    ];
}
```

---

## Working with Markers

### Creating Markers

The `Marker` class provides a fluent interface for creating markers:

```php
use EduardoRibeiroDev\FilamentLeaflet\Support\Marker;

public function getMarkers(): array
{
    return [
        Marker::make(-23.5505, -46.6333)
            ->id('store-1')
            ->label('Main Store')
            ->blue()
            ->draggable(),
    ];
}
```

### Marker Colors

Built-in color methods for quick styling:

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
```

Or use the `color()` method with a string or enum:

```php
use EduardoRibeiroDev\FilamentLeaflet\Enums\Color;

Marker::make($lat, $lng)
    ->color('red')
    ->color(Color::Red);
```

### Custom Icons

Replace the default marker with a custom icon:

```php
Marker::make($lat, $lng)
    ->icon(
        url: '/images/custom-marker.png',
        size: [32, 32]  // [width, height]
    );
```

### Marker Groups

Organize markers into groups for better management:

```php
Marker::make($lat, $lng)
    ->group('restaurants')
    ->label('Pizza Place');

Marker::make($lat, $lng)
    ->group('hotels')
    ->label('Grand Hotel');
```

### Draggable Markers

Enable marker dragging:

```php
Marker::make($lat, $lng)
    ->draggable()
    ->label('Drag me!');
```

---

## Tooltips & Popups

### Tooltips

Display information on hover:

```php
Marker::make($lat, $lng)
    ->tooltipContent('This is a tooltip')
    ->tooltipPermanent(true)  // Always visible
    ->tooltipDirection('top') // 'auto', 'top', 'bottom', 'left', 'right'
    ->tooltipOptions([
        'offset' => [0, -10],
        'className' => 'custom-tooltip'
    ]);
```

### Popups

Display detailed information on click:

```php
Marker::make($lat, $lng)
    ->popupContent('<h3>Store Name</h3><p>123 Main St</p>')
    ->popupOptions([
        'maxWidth' => 300,
        'className' => 'custom-popup'
    ]);
```

### Smart Popup Data

Use `popupData()` to automatically format key-value pairs into HTML:

```php
Marker::make($lat, $lng)
    ->popupData([
        'Store' => 'Pizza Palace',
        'Address' => '123 Main Street',
        'Phone' => '+1 (555) 123-4567',
        'Rating' => '4.8/5.0',
        'Delivery' => 'Available'
    ]);
```

This automatically generates:

```html
<p><span class="field-label">Store:</span> Pizza Palace</p>
<p><span class="field-label">Address:</span> 123 Main Street</p>
<p><span class="field-label">Phone:</span> +1 (555) 123-4567</p>
<p><span class="field-label">Rating:</span> 4.8/5.0</p>
<p><span class="field-label">Delivery:</span> Available</p>
```

### Combined Popup Configuration

Use the `popup()` convenience method:

```php
Marker::make($lat, $lng)
    ->popup(
        content: 'Featured Location',
        data: [
            'type' => 'Restaurant',
            'rating' => '4.5 stars'
        ],
        options: [
            'maxWidth' => 400
        ]
    );
```

---

## GeoJSON & Density Maps

### Displaying GeoJSON Layers

Show geographic boundaries with density coloring:

```php
class BrazilDensityMap extends MapWidget
{
    // URL to GeoJSON file
    protected static ?string $geoJsonUrl = '/maps/brazil-states.json';
    
    // Color gradient for density visualization
    protected static array $geoJsonColors = [
        '#FED976',  // Lowest density
        '#FEB24C',
        '#FD8D3C',
        '#FC4E2A',
        '#E31A1C',
        '#BD0026',
        '#800026'   // Highest density
    ];
    
    public function getGeoJsonData(): array
    {
        return [
            'SP' => 1500,  // São Paulo
            'RJ' => 1200,  // Rio de Janeiro
            'MG' => 800,   // Minas Gerais
            // ... other states
        ];
    }
    
    public static function getGeoJsonTooltip(): string
    {
        return '<h4>{state}</h4><b>Population: {density}</b>';
    }
}
```

### Custom GeoJSON Tooltip

The tooltip template supports placeholders that are replaced with actual data:

```php
public static function getGeoJsonTooltip(): string
{
    return <<<HTML
        <div class="geo-tooltip">
            <h3>{name}</h3>
            <p>Density: {density}</p>
            <p>Code: {code}</p>
        </div>
    HTML;
}
```

---

## Interactive Features

### Creating Markers from Map Clicks

Enable marker creation by clicking on the map:

```php
class InteractiveMap extends MapWidget
{
    // Define the model to store markers
    protected static ?string $markerModel = \App\Models\Location::class;
    
    // Column names in your database
    protected static string $latitudeColumnName = 'latitude';
    protected static string $longitudeColumnName = 'longitude';
    
    // Or use JSON column
    protected static ?string $jsonCoordinatesColumnName = 'coordinates';
    
    // Form configuration
    protected static int $formColumns = 2;
    
    protected static function getFormComponents(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255),
                
            Select::make('color')
                ->options(Color::class)
                ->required(),
                
            Textarea::make('description')
                ->maxLength(1000)
                ->columnSpanFull(),
        ];
    }
}
```

### Coordinate Storage Options

**Option 1: Separate Columns**

```php
protected static string $latitudeColumnName = 'latitude';
protected static string $longitudeColumnName = 'longitude';
```

Database migration:
```php
$table->decimal('latitude', 10, 8);
$table->decimal('longitude', 11, 8);
```

**Option 2: JSON Column**

```php
protected static ?string $jsonCoordinatesColumnName = 'coordinates';
```

Database migration:
```php
$table->json('coordinates');
```

The data will be stored as:
```json
{
    "latitude": -23.5505,
    "longitude": -46.6333
}
```

### After Marker Creation Hook

Execute custom logic after a marker is created:

```php
protected function afterMarkerCreated(Model $record): void
{
    // Send notification
    Notification::make()
        ->success()
        ->title('Location added')
        ->body("New location: {$record->name}")
        ->send();
    
    // Dispatch event
    event(new LocationCreated($record));
    
    // Update related records
    $record->owner->increment('locations_count');
}
```

---

## Events & Actions

### Marker Click Actions

Execute callbacks when markers are clicked:

```php
Marker::make($lat, $lng)
    ->label('Clickable Store')
    ->action(function (Marker $marker) {
        Notification::make()
            ->title('Store Selected')
            ->body('You clicked on: ' . $marker->getId())
            ->send();
    });
```

### Mouse Events

Add custom JavaScript for hover interactions:

```php
Marker::make($lat, $lng)
    ->onMouseOver("console.log('Hover started')")
    ->onMouseOut("console.log('Hover ended')");
```

### Map Update Event

Manually refresh the map:

```php
public function someAction()
{
    // Update your data
    $this->loadNewMarkers();
    
    // Refresh the map
    $this->refreshMap();
}
```

### Livewire Integration with Tables

Use the `InteractsWithMap` trait to sync table actions with the map:

```php
use EduardoRibeiroDev\FilamentLeaflet\Traits\InteractsWithMap;

class ManageLocation extends ManageRecords
{
    use InteractsWithMap;
    
    // Table actions will automatically update the map
}
```

The trait provides:
- Automatic map refresh after table actions
- Reset table after marker creation
- Bidirectional sync between table and map

---

## Advanced Usage

### Loading Markers from Database

```php
public function getMarkers(): array
{
    return \App\Models\Location::all()
        ->map(function ($location) {
            return Marker::make(
                $location->latitude,
                $location->longitude
            )
            ->id($location->id)
            ->label($location->name)
            ->color($location->color)
            ->popup(
                content: "$location->name",
                data: [
                    'address' => $location->address,
                    'phone' => $location->phone,
                    'status' => $location->is_active ? 'Open' : 'Closed'
                ]
            )
            ->action(function () use ($location) {
                $this->redirectRoute('locations.show', $location);
            });
        })
        ->toArray();
}
```

### Marker Distance Calculation

Calculate distance between markers using the Haversine formula:

```php
$marker1 = Marker::make(-23.5505, -46.6333);
$marker2 = Marker::make(-22.9068, -43.1729);

$distanceKm = $marker1->distanceTo($marker2);
// Returns distance in kilometers
```

### Marker Validation

Ensure coordinates are valid:

```php
try {
    Marker::make($lat, $lng)
        ->validate(); // Throws InvalidArgumentException if invalid
} catch (InvalidArgumentException $e) {
    // Handle invalid coordinates
}

// Or check manually
if ($marker->isValid()) {
    // Coordinates are valid (-90 to 90 for lat, -180 to 180 for lng)
}
```

### Custom Scripts & Styles

Add custom JavaScript and CSS:

```php
public function getAdditionalScripts(): string
{
    return <<<JS
        console.log('Map initialized');
        // Custom JavaScript here
    JS;
}

public function afterMapInit(): string
{
    return <<<JS
        // Code to run after map initialization
        map.on('zoomend', function() {
            console.log('Zoom changed to: ' + map.getZoom());
        });
    JS;
}

public function getCustomStyles(): string
{
    return <<<CSS
        .leaflet-container {
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    CSS;
}
```

### Conditional Markers

Use conditional logic with the fluent API:

```php
public function getMarkers(): array
{
    return Location::all()
        ->map(fn($location) => 
            Marker::make($location->lat, $location->lng)
                ->label($location->name)
                ->when(
                    $location->is_featured,
                    fn($marker) => $marker->gold()->icon('/icons/star.png', [40, 40])
                )
                ->when(
                    $location->is_closed,
                    fn($marker) => $marker->grey()
                )
        )
        ->toArray();
}
```

---

## API Reference

### MapWidget Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$heading` | `?string` | `null` | Widget title |
| `$mapCenter` | `array` | `[-14.235, -51.9253]` | Map center coordinates |
| `$defaultZoom` | `int` | `4` | Initial zoom level |
| `$mapHeight` | `int` | `504` | Map height in pixels |
| `$hasAttributionControl` | `bool` | `false` | Show attribution |
| `$tileLayerUrl` | `string` | OpenStreetMap | Tile layer URL |
| `$maxZoom` | `int` | `18` | Maximum zoom |
| `$minZoom` | `int` | `2` | Minimum zoom |
| `$markerModel` | `?string` | `null` | Model for marker creation |
| `$latitudeColumnName` | `string` | `'latitude'` | Latitude column name |
| `$longitudeColumnName` | `string` | `'longitude'` | Longitude column name |
| `$jsonCoordinatesColumnName` | `?string` | `null` | JSON coordinates column |
| `$formColumns` | `int` | `2` | Form columns |
| `$geoJsonUrl` | `?string` | `null` | GeoJSON file URL |
| `$geoJsonColors` | `array` | See config | Density color gradient |

### MapWidget Methods

| Method | Return | Description |
|--------|--------|-------------|
| `getMarkers()` | `array` | Return array of markers |
| `getGeoJsonData()` | `array` | Return density data |
| `getGeoJsonUrl()` | `?string` | Return GeoJSON URL |
| `getGeoJsonColors()` | `array` | Return color palette |
| `getGeoJsonTooltip()` | `string` | Return tooltip template |
| `refreshMap()` | `void` | Update map display |
| `afterMarkerCreated(Model $record)` | `void` | Hook after creation |
| `getAdditionalScripts()` | `string` | Return custom JS |
| `afterMapInit()` | `string` | Return post-init JS |
| `getCustomStyles()` | `string` | Return custom CSS |

### Marker Methods

| Method | Parameters | Return | Description |
|--------|-----------|--------|-------------|
| `make()` | `float $lat, float $lng` | `static` | Create marker |
| `id()` | `string $id` | `static` | Set marker ID |
| `label()` | `string $label` | `static` | Set label text |
| `color()` | `string\|Color $color` | `static` | Set marker color |
| `icon()` | `string $url, array $size` | `static` | Set custom icon |
| `group()` | `string $group` | `static` | Set marker group |
| `draggable()` | `bool $condition` | `static` | Enable dragging |
| `tooltipContent()` | `string $content` | `static` | Set tooltip text |
| `tooltipPermanent()` | `bool $permanent` | `static` | Always show tooltip |
| `tooltipDirection()` | `string $direction` | `static` | Tooltip position |
| `tooltipOptions()` | `array $options` | `static` | Tooltip options |
| `popupContent()` | `string $content` | `static` | Set popup HTML |
| `popupData()` | `array $data` | `static` | Set smart data |
| `popupOptions()` | `array $options` | `static` | Popup options |
| `popup()` | `string $content, array $data, array $options` | `static` | Combined popup setup |
| `action()` | `callable $callback` | `static` | Click handler |
| `onMouseOver()` | `string $script` | `static` | Hover start script |
| `onMouseOut()` | `string $script` | `static` | Hover end script |
| `distanceTo()` | `Marker $target` | `float` | Calculate distance |
| `isValid()` | - | `bool` | Check coordinates |
| `validate()` | - | `static` | Throw if invalid |

### Color Helper Methods

All color methods return `static`:

- `blue()`
- `red()`
- `green()`
- `orange()`
- `yellow()`
- `violet()`
- `grey()`
- `black()`
- `gold()`

---

## Complete Example

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Store;
use EduardoRibeiroDev\FilamentLeaflet\Widgets\MapWidget;
use EduardoRibeiroDev\FilamentLeaflet\Support\Marker;
use EduardoRibeiroDev\FilamentLeaflet\Enums\Color;
use Filament\Notifications\Notification;

class StoreLocationsMap extends MapWidget
{
    protected static ?string $heading = 'Store Locations';
    protected static array $mapCenter = [-23.5505, -46.6333];
    protected static int $defaultZoom = 12;
    protected static int $mapHeight = 600;
    protected static ?string $markerModel = Store::class;
    
    public function getMarkers(): array
    {
        return Store::with('manager')->get()
            ->map(function ($store) {
                return Marker::make($store->latitude, $store->longitude)
                    ->id("store-{$store->id}")
                    ->label($store->name)
                    ->color($store->is_active ? 'green' : 'grey')
                    ->tooltipContent($store->name)
                    ->popup(
                        content: $store->name,
                        data: [
                            'Manager' => $store->manager->name,
                            'Phone' => $store->phone,
                            'Hours' => $store->business_hours,
                            'Status' => $store->is_active ? 'Open' : 'Closed'
                        ]
                    )
                    ->action(function ($marker) use ($store) {
                        Notification::make()
                            ->title("Selected: {$store->name}")
                            ->success()
                            ->send();
                    })
                    ->when(
                        $store->is_flagship,
                        fn($marker) => $marker->icon('/icons/flagship.png', [48, 48])
                    );
            })
            ->toArray();
    }
    
    protected static function getFormComponents(): array
    {
        return [
            TextInput::make('name')->required(),
            Select::make('color')->options(Color::class),
            TextInput::make('phone'),
            Textarea::make('business_hours')->columnSpanFull(),
        ];
    }
    
    protected function afterMarkerCreated(Model $record): void
    {
        Notification::make()
            ->success()
            ->title('Store Added')
            ->body("New store '{$record->name}' has been added to the map")
            ->send();
    }
}
```

---

## Support

For issues, questions, or contributions, please visit:
- GitHub: [eduardoribeirodev/filament-leaflet](https://github.com/eduardoribeirodev/filament-leaflet)

## License

This package is open-sourced software licensed under the MIT license.