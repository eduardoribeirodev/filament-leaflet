# Filament Integration Examples

This guide shows how to integrate the spatial support classes with FilamentPHP.

## Table of Contents

1. [Basic MapPicker Setup](#basic-mappicker-setup)
2. [Resource with Spatial Features](#resource-with-spatial-features)
3. [Table with Distance Column](#table-with-distance-column)
4. [Import/Export Actions](#importexport-actions)
5. [Custom Map Widget](#custom-map-widget)
6. [Geocoding in Forms](#geocoding-in-forms)

---

## Basic MapPicker Setup

The custom `MapPicker` component handles null states and polygon data properly:

```php
<?php

namespace App\Filament\Resources\LocationResource\Schemas;

use App\Filament\Forms\Components\MapPicker;
use EduardoRibeiroDev\FilamentLeaflet\Enums\TileLayer;
use Filament\Schemas\Schema;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // Point location picker
            MapPicker::make('location')
                ->label('Location')
                ->storeAsJson()
                ->center(-23.5505, -46.6333)
                ->zoom(15)
                ->drawMarkerControl()
                ->tileLayersUrl([
                    'Street' => TileLayer::OpenStreetMap,
                    'Satellite' => TileLayer::GoogleSatellite,
                ])
                ->height(400),

            // Polygon area picker
            MapPicker::make('boundary')
                ->label('Area Boundary')
                ->storeAsJson()
                ->storeFullGeometry()
                ->center(-23.5505, -46.6333)
                ->zoom(15)
                ->drawPolygonControl()
                ->editLayersControl()
                ->removeLayersControl()
                ->height(400),
        ]);
    }
}
```

---

## Resource with Spatial Features

Complete example of a Filament Resource with spatial queries:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocationResource\Pages;
use App\Models\Location;
use App\Support\Spatial\MeasurementTools;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            
            \App\Filament\Forms\Components\MapPicker::make('location')
                ->storeAsJson()
                ->center(-23.5505, -46.6333)
                ->zoom(15)
                ->drawMarkerControl()
                ->height(400),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                
                TextColumn::make('location')
                    ->label('Coordinates')
                    ->formatStateUsing(fn ($state) => $state 
                        ? sprintf('%.4f, %.4f', $state['lat'] ?? 0, $state['lng'] ?? 0)
                        : '-'
                    ),
                
                // Dynamic distance column (requires reference point in session/config)
                TextColumn::make('distance')
                    ->label('Distance')
                    ->getStateUsing(function ($record) {
                        $refLat = session('reference_lat', -23.5505);
                        $refLng = session('reference_lng', -46.6333);
                        
                        if (!$record->location) return null;
                        
                        $tools = new MeasurementTools();
                        $distance = $tools->distance(
                            [$record->location['lat'], $record->location['lng']],
                            [$refLat, $refLng],
                            'km'
                        );
                        
                        return $tools->formatDistance($distance);
                    }),
            ])
            ->filters([
                // Filter by radius
                Filter::make('nearby')
                    ->form([
                        TextInput::make('radius')
                            ->label('Radius (km)')
                            ->numeric()
                            ->default(10),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!$data['radius']) return $query;
                        
                        $refLat = session('reference_lat', -23.5505);
                        $refLng = session('reference_lng', -46.6333);
                        
                        return $query->withinRadius('location', $refLat, $refLng, $data['radius']);
                    }),
                    
                // Filter by bounding box
                Filter::make('in_bounds')
                    ->form([
                        TextInput::make('min_lat')->numeric(),
                        TextInput::make('max_lat')->numeric(),
                        TextInput::make('min_lng')->numeric(),
                        TextInput::make('max_lng')->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!$data['min_lat']) return $query;
                        
                        return $query->withinBounds(
                            'location',
                            $data['min_lat'],
                            $data['max_lat'],
                            $data['min_lng'],
                            $data['max_lng']
                        );
                    }),
            ])
            ->actions([
                // Calculate distance action
                Action::make('distance')
                    ->label('Distance To')
                    ->icon('heroicon-o-map-pin')
                    ->form([
                        TextInput::make('lat')->label('Latitude')->required()->numeric(),
                        TextInput::make('lng')->label('Longitude')->required()->numeric(),
                    ])
                    ->action(function (Location $record, array $data) {
                        $tools = new MeasurementTools();
                        $distance = $tools->distance(
                            [$record->location['lat'], $record->location['lng']],
                            [$data['lat'], $data['lng']],
                            'km'
                        );
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Distance Calculated')
                            ->body($tools->formatDistance($distance))
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}
```

---

## Table with Distance Column

Add a computed distance column to any table:

```php
<?php

namespace App\Filament\Resources\Infrastructures\Tables;

use App\Support\Spatial\MeasurementTools;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InfrastructuresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'primary' => 'school',
                        'success' => 'hospital',
                        'warning' => 'road',
                        'info' => 'water_system',
                    ]),

                TextColumn::make('location')
                    ->label('Coordinates')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '-';
                        
                        // Handle both point and polygon
                        if (isset($state['lat'])) {
                            return sprintf('%.4f, %.4f', $state['lat'], $state['lng']);
                        }
                        
                        if (isset($state['type']) && $state['type'] === 'Polygon') {
                            return 'Polygon (' . count($state['coordinates'][0] ?? []) . ' points)';
                        }
                        
                        return '-';
                    }),

                // Computed distance from reference point
                TextColumn::make('distance_km')
                    ->label('Distance')
                    ->getStateUsing(function ($record) {
                        // Get reference from session, request, or config
                        $refLat = request()->get('ref_lat', config('spatial.reference.lat', -23.5505));
                        $refLng = request()->get('ref_lng', config('spatial.reference.lng', -46.6333));
                        
                        $location = $record->location;
                        if (!$location || !isset($location['lat'])) {
                            return null;
                        }
                        
                        $tools = new MeasurementTools();
                        return $tools->distance(
                            [$location['lat'], $location['lng']],
                            [$refLat, $refLng],
                            'km'
                        );
                    })
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 2) . ' km' : '-')
                    ->sortable(query: function ($query, $direction) {
                        // For PostgreSQL with PostGIS
                        $refLat = config('spatial.reference.lat', -23.5505);
                        $refLng = config('spatial.reference.lng', -46.6333);
                        
                        return $query->orderByRaw(
                            "ST_Distance(
                                ST_SetSRID(ST_MakePoint((location->>'lng')::float, (location->>'lat')::float), 4326)::geography,
                                ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
                            ) {$direction}",
                            [$refLng, $refLat]
                        );
                    }),

                TextColumn::make('subdivision.name')
                    ->label('Subdivision')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name');
    }
}
```

---

## Import/Export Actions

Add GeoJSON/KML import and export to your resource:

```php
<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Resources\LocationResource;
use App\Models\Location;
use App\Support\Geo\GeoJsonExporter;
use App\Support\Geo\GeoJsonImporter;
use App\Support\Geo\KmlExporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListLocations extends ListRecords
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Import from GeoJSON
            Action::make('import')
                ->label('Import GeoJSON')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('file')
                        ->label('GeoJSON File')
                        ->acceptedFileTypes(['application/json', 'application/geo+json'])
                        ->required(),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['file']);
                    
                    $importer = new GeoJsonImporter();
                    $features = $importer
                        ->mapProperties([
                            'name' => 'properties.name',
                            'description' => 'properties.description',
                        ])
                        ->geometryColumn('location')
                        ->fromFile($path);
                    
                    $count = 0;
                    foreach ($features as $feature) {
                        Location::create([
                            'name' => $feature['properties']['name'] ?? 'Imported Location',
                            'description' => $feature['properties']['description'] ?? null,
                            'location' => [
                                'lat' => $feature['latitude'],
                                'lng' => $feature['longitude'],
                            ],
                        ]);
                        $count++;
                    }
                    
                    // Clean up uploaded file
                    Storage::disk('local')->delete($data['file']);
                    
                    Notification::make()
                        ->title('Import Complete')
                        ->body("Imported {$count} locations.")
                        ->success()
                        ->send();
                }),

            // Export to GeoJSON/KML
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->form([
                    Select::make('format')
                        ->label('Format')
                        ->options([
                            'geojson' => 'GeoJSON',
                            'kml' => 'KML',
                        ])
                        ->required()
                        ->default('geojson'),
                ])
                ->action(function (array $data) {
                    if ($data['format'] === 'geojson') {
                        $exporter = new GeoJsonExporter();
                        return $exporter
                            ->fromModel(Location::class)
                            ->geometryColumn('location')
                            ->properties(['name', 'description'])
                            ->download('locations.geojson');
                    }
                    
                    $exporter = new KmlExporter();
                    return $exporter
                        ->fromModel(Location::class)
                        ->name('Locations Export')
                        ->geometryColumn('location')
                        ->nameColumn('name')
                        ->descriptionColumn('description')
                        ->download('locations.kml');
                }),
                
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
```

---

## Custom Map Widget

Create a dashboard widget showing locations on a map:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Infrastructure;
use App\Support\Spatial\ViewportLoader;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class MapWidget extends Widget
{
    protected static string $view = 'filament.widgets.map-widget';

    protected int | string | array $columnSpan = 'full';

    public array $bounds = [
        'minLat' => -24,
        'maxLat' => -23,
        'minLng' => -47,
        'maxLng' => -46,
    ];

    public int $zoom = 12;

    public function getViewData(): array
    {
        $loader = ViewportLoader::make(Infrastructure::class)
            ->geometryColumn('location')
            ->withinBounds($this->bounds)
            ->zoom($this->zoom)
            ->cluster(true, 50)
            ->limit(500);

        return [
            'geoJson' => $loader->toGeoJson(),
            'center' => [
                ($this->bounds['minLat'] + $this->bounds['maxLat']) / 2,
                ($this->bounds['minLng'] + $this->bounds['maxLng']) / 2,
            ],
            'zoom' => $this->zoom,
        ];
    }

    #[On('map-moved')]
    public function updateBounds(array $bounds, int $zoom): void
    {
        $this->bounds = $bounds;
        $this->zoom = $zoom;
    }
}
```

**Widget Blade Template** (`resources/views/filament/widgets/map-widget.blade.php`):

```blade
<x-filament-widgets::widget>
    <x-filament::section>
        <div
            x-data="{
                map: null,
                geoJsonLayer: null,
                init() {
                    this.map = L.map($refs.map).setView(@json($center), {{ $zoom }});
                    
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors'
                    }).addTo(this.map);
                    
                    this.loadGeoJson(@json($geoJson));
                    
                    this.map.on('moveend', () => {
                        const bounds = this.map.getBounds();
                        $wire.call('updateBounds', {
                            minLat: bounds.getSouth(),
                            maxLat: bounds.getNorth(),
                            minLng: bounds.getWest(),
                            maxLng: bounds.getEast(),
                        }, this.map.getZoom());
                    });
                },
                loadGeoJson(data) {
                    if (this.geoJsonLayer) {
                        this.map.removeLayer(this.geoJsonLayer);
                    }
                    
                    this.geoJsonLayer = L.geoJSON(data, {
                        pointToLayer: (feature, latlng) => {
                            return L.marker(latlng);
                        },
                        onEachFeature: (feature, layer) => {
                            if (feature.properties.name) {
                                layer.bindPopup(`
                                    <strong>${feature.properties.name}</strong>
                                    ${feature.properties.type ? '<br>Type: ' + feature.properties.type : ''}
                                `);
                            }
                        }
                    }).addTo(this.map);
                }
            }"
            x-init="init()"
            wire:ignore
        >
            <div x-ref="map" style="height: 400px; width: 100%;"></div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
```

---

## Geocoding in Forms

Add geocoding (address lookup) to your forms:

```php
<?php

namespace App\Filament\Resources\LocationResource\Schemas;

use App\Filament\Forms\Components\MapPicker;
use App\Support\Spatial\Geocoder;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required(),

            TextInput::make('address')
                ->label('Address')
                ->placeholder('Enter address to geocode')
                ->suffixAction(
                    Action::make('geocode')
                        ->icon('heroicon-o-magnifying-glass')
                        ->action(function (Set $set, $state) {
                            if (empty($state)) {
                                Notification::make()
                                    ->title('Error')
                                    ->body('Please enter an address')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            try {
                                $geocoder = new Geocoder();
                                $results = $geocoder->geocode($state);

                                if ($results->isEmpty()) {
                                    Notification::make()
                                        ->title('Not Found')
                                        ->body('No results found for this address')
                                        ->warning()
                                        ->send();
                                    return;
                                }

                                $first = $results->first();
                                $set('location', [
                                    'lat' => $first->getLatitude(),
                                    'lng' => $first->getLongitude(),
                                ]);

                                Notification::make()
                                    ->title('Location Found')
                                    ->body($first->getFormattedAddress())
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Geocoding Error')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                ),

            MapPicker::make('location')
                ->storeAsJson()
                ->center(-23.5505, -46.6333)
                ->zoom(15)
                ->drawMarkerControl()
                ->height(400)
                ->live()
                ->afterStateUpdated(function (Set $set, $state) {
                    // Reverse geocode when marker is moved
                    if ($state && isset($state['lat'])) {
                        try {
                            $geocoder = new Geocoder();
                            $result = $geocoder->reverse($state['lat'], $state['lng']);
                            
                            if ($result) {
                                $set('address', $result->getFormattedAddress());
                            }
                        } catch (\Exception $e) {
                            // Silently fail reverse geocoding
                        }
                    }
                }),
        ]);
    }
}
```

---

## Complete Working Example

Here's a complete Infrastructure Resource with all spatial features:

```php
<?php

namespace App\Filament\Resources\Infrastructures\Schemas;

use App\Filament\Forms\Components\MapPicker;
use App\Support\Spatial\Geocoder;
use App\Support\Spatial\MeasurementTools;
use EduardoRibeiroDev\FilamentLeaflet\Enums\TileLayer;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InfrastructureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->placeholder('Infrastructure name'),

                        Select::make('type')
                            ->required()
                            ->options([
                                'school' => 'School',
                                'hospital' => 'Hospital',
                                'road' => 'Road',
                                'water_system' => 'Water System',
                                'market' => 'Market',
                            ]),

                        Textarea::make('description')
                            ->columnSpanFull()
                            ->rows(3),

                        Select::make('subdivision_id')
                            ->relationship('subdivision', 'name')
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('Location')
                    ->description('Search for an address or click on the map to set location')
                    ->schema([
                        TextInput::make('address')
                            ->label('Address Search')
                            ->placeholder('Type an address and click search')
                            ->suffixAction(
                                Action::make('geocode')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->tooltip('Search address')
                                    ->action(function (Set $set, $state) {
                                        if (empty($state)) return;

                                        try {
                                            $geocoder = new Geocoder();
                                            $results = $geocoder->geocode($state);

                                            if ($results->isNotEmpty()) {
                                                $first = $results->first();
                                                $set('location', [
                                                    'lat' => $first->getLatitude(),
                                                    'lng' => $first->getLongitude(),
                                                ]);

                                                Notification::make()
                                                    ->title('Location Found')
                                                    ->body($first->getFormattedAddress())
                                                    ->success()
                                                    ->send();
                                            }
                                        } catch (\Exception $e) {
                                            Notification::make()
                                                ->title('Search Failed')
                                                ->body($e->getMessage())
                                                ->danger()
                                                ->send();
                                        }
                                    })
                            ),

                        MapPicker::make('location')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->storeAsJson()
                            ->storeFullGeometry()
                            ->center(-23.5505, -46.6333)
                            ->tileLayersUrl([
                                'Street Map' => TileLayer::OpenStreetMap,
                                'Satellite' => TileLayer::GoogleSatellite,
                                'Hybrid' => TileLayer::GoogleHybrid,
                            ])
                            ->height(500)
                            ->zoom(15)
                            ->maxZoom(22)
                            ->drawMarkerControl()
                            ->drawPolygonControl()
                            ->editLayersControl()
                            ->removeLayersControl()
                            ->scaleControl()
                            ->mapZoomable(),

                        Fieldset::make('Coordinates')
                            ->columns(2)
                            ->schema([
                                TextInput::make('location.lat')
                                    ->label('Latitude')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('location.lng')
                                    ->label('Longitude')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ]),
            ]);
    }
}
```

---

## Tips for Filament + Spatial

1. **Use `storeAsJson()`** - Always store location data as JSON for flexibility
2. **Handle null states** - Use the custom `MapPicker` component that handles null properly
3. **Lazy load maps** - Use `Lazy` or deferred loading for pages with many maps
4. **Cache viewport data** - Use `SpatialCache` for expensive queries
5. **Index your columns** - Add spatial indexes for better performance
6. **Use server clustering** - For large datasets, use `ViewportLoader` with clustering

## Service Provider Registration

Register the spatial macros in your `AppServiceProvider`:

```php
<?php

namespace App\Providers;

use App\Support\Spatial\SpatialQueryBuilder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register spatial query macros
        SpatialQueryBuilder::registerMacros();
    }
}
```

This enables `->withinRadius()`, `->withinBounds()`, etc. on all Eloquent queries.

