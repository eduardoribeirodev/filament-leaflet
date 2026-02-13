<?php

declare(strict_types=1);

use App\Models\Infrastructure;
use App\Models\Subdivision;
use App\Support\Layers\ClusterLayer;
use App\Support\Layers\HeatmapLayer;
use App\Support\Layers\LayerManager;
use App\Support\Layers\VectorTileLayer;
use App\Support\Layers\WMSLayer;

describe('HeatmapLayer', function () {
    it('creates a heatmap with points', function () {
        $heatmap = HeatmapLayer::make()
            ->points([
                [-23.5505, -46.6333, 1.0],
                [-23.5600, -46.6400, 0.8],
            ])
            ->radius(25)
            ->blur(15);

        $array = $heatmap->toArray();

        expect($array['type'])->toBe('heatmap')
            ->and($array['data'])->toHaveCount(2)
            ->and($array['options']['radius'])->toBe(25)
            ->and($array['options']['blur'])->toBe(15);
    });

    it('loads points from collection', function () {
        $collection = collect([
            ['name' => 'Point 1', 'location' => ['lat' => -23.5505, 'lng' => -46.6333]],
            ['name' => 'Point 2', 'location' => ['lat' => -23.5600, 'lng' => -46.6400]],
        ]);

        $heatmap = HeatmapLayer::make()
            ->fromCollection($collection, 'location');

        expect($heatmap->count())->toBe(2);
    });

    it('applies gradient presets', function () {
        $heatmap = HeatmapLayer::make()
            ->usePresetGradient('viridis');

        $array = $heatmap->toArray();

        expect($array['options']['gradient'])->toHaveKey(0.8)
            ->and($array['options']['gradient'][0.8])->toBe('#5ec962');
    });

    it('loads from model', function () {
        $subdivision = Subdivision::factory()->create();
        Infrastructure::factory()->create([
            'subdivision_id' => $subdivision->id,
            'location' => ['lat' => -23.5505, 'lng' => -46.6333],
        ]);
        Infrastructure::factory()->create([
            'subdivision_id' => $subdivision->id,
            'location' => ['lat' => -23.5600, 'lng' => -46.6400],
        ]);

        $heatmap = HeatmapLayer::make()
            ->fromModel(Infrastructure::class, 'location');

        expect($heatmap->count())->toBe(2);
    });
});

describe('WMSLayer', function () {
    it('creates a WMS layer', function () {
        $wms = WMSLayer::make('https://wms.example.com/geoserver/wms')
            ->layers('workspace:layer')
            ->format('image/png')
            ->transparent()
            ->attribution('© Example');

        $array = $wms->toArray();

        expect($array['type'])->toBe('wms')
            ->and($array['baseUrl'])->toBe('https://wms.example.com/geoserver/wms')
            ->and($array['params']['layers'])->toBe('workspace:layer')
            ->and($array['params']['format'])->toBe('image/png')
            ->and($array['params']['transparent'])->toBeTrue();
    });

    it('configures for GeoServer', function () {
        $wms = WMSLayer::make('https://geoserver.example.com/wms')
            ->geoServer('myworkspace', 'mylayer', 'mystyle');

        $array = $wms->toArray();

        expect($array['params']['layers'])->toBe('myworkspace:mylayer')
            ->and($array['params']['styles'])->toBe('mystyle');
    });

    it('builds GetCapabilities URL', function () {
        $wms = WMSLayer::make('https://wms.example.com/wms')
            ->version('1.3.0');

        $url = $wms->getCapabilitiesUrl();

        expect($url)->toContain('SERVICE=WMS')
            ->and($url)->toContain('REQUEST=GetCapabilities')
            ->and($url)->toContain('VERSION=1.3.0');
    });

    it('supports CQL filters', function () {
        $wms = WMSLayer::make('https://geoserver.example.com/wms')
            ->layers('data:buildings')
            ->cqlFilter("type = 'residential'");

        $array = $wms->toArray();

        expect($array['params']['CQL_FILTER'])->toBe("type = 'residential'");
    });
});

describe('ClusterLayer', function () {
    it('creates a cluster layer with markers', function () {
        $cluster = ClusterLayer::make()
            ->markers([
                ['lat' => -23.5505, 'lng' => -46.6333, 'title' => 'Point 1'],
                ['lat' => -23.5600, 'lng' => -46.6400, 'title' => 'Point 2'],
            ])
            ->maxClusterRadius(80)
            ->spiderfyOnMaxZoom();

        $array = $cluster->toArray();

        expect($array['type'])->toBe('cluster')
            ->and($array['markers'])->toHaveCount(2)
            ->and($array['options']['maxClusterRadius'])->toBe(80)
            ->and($array['options']['spiderfyOnMaxZoom'])->toBeTrue();
    });

    it('loads from model', function () {
        $subdivision = Subdivision::factory()->create();
        Infrastructure::factory(3)->create([
            'subdivision_id' => $subdivision->id,
            'location' => ['lat' => -23.5505, 'lng' => -46.6333],
        ]);

        $cluster = ClusterLayer::make()
            ->fromModel(Infrastructure::class, 'location');

        expect($cluster->count())->toBe(3);
    });

    it('calculates bounds', function () {
        $cluster = ClusterLayer::make()
            ->markers([
                ['lat' => -23.5505, 'lng' => -46.6333],
                ['lat' => -23.5600, 'lng' => -46.6400],
                ['lat' => -23.5700, 'lng' => -46.6500],
            ]);

        $bounds = $cluster->getBounds();

        expect($bounds['minLat'])->toBe(-23.57)
            ->and($bounds['maxLat'])->toBe(-23.5505)
            ->and($bounds['minLng'])->toBe(-46.65)
            ->and($bounds['maxLng'])->toBe(-46.6333);
    });
});

describe('VectorTileLayer', function () {
    it('creates a vector tile layer', function () {
        $vt = VectorTileLayer::make('https://tiles.example.com/{z}/{x}/{y}.pbf')
            ->vectorTileLayerStyles([
                'buildings' => ['fillColor' => '#888'],
                'water' => ['fillColor' => '#00f'],
            ])
            ->interactive(['buildings'])
            ->zoomRange(10, 18);

        $array = $vt->toArray();

        expect($array['type'])->toBe('vectorTile')
            ->and($array['url'])->toBe('https://tiles.example.com/{z}/{x}/{y}.pbf')
            ->and($array['options']['vectorTileLayerStyles'])->toHaveKey('buildings')
            ->and($array['options']['interactive'])->toContain('buildings')
            ->and($array['options']['minZoom'])->toBe(10)
            ->and($array['options']['maxZoom'])->toBe(18);
    });

    it('configures for Mapbox', function () {
        $vt = VectorTileLayer::make('')
            ->mapbox('mapbox.streets', 'pk.test123');

        $array = $vt->toArray();

        expect($array['url'])->toContain('api.mapbox.com')
            ->and($array['url'])->toContain('pk.test123')
            ->and($array['options']['attribution'])->toContain('Mapbox');
    });

    it('applies preset styles', function () {
        $vt = VectorTileLayer::make('https://example.com/{z}/{x}/{y}.pbf')
            ->presetStyle('buildings', 'default')
            ->presetStyle('water', 'dark');

        $array = $vt->toArray();

        expect($array['options']['vectorTileLayerStyles']['buildings']['fillColor'])->toBe('#d4d4d4')
            ->and($array['options']['vectorTileLayerStyles']['water']['fillColor'])->toBe('#1a365d');
    });
});

describe('LayerManager', function () {
    it('manages multiple layers', function () {
        $manager = LayerManager::make()
            ->addHeatmap('heat', fn ($l) => $l->points([[-23.5505, -46.6333, 1.0]]))
            ->addWMS('imagery', 'https://wms.example.com', fn ($l) => $l->layers('satellite'))
            ->addCluster('markers', fn ($l) => $l->markers([['lat' => -23.5505, 'lng' => -46.6333]]));

        expect($manager->count())->toBe(3)
            ->and($manager->hasLayer('heat'))->toBeTrue()
            ->and($manager->hasLayer('imagery'))->toBeTrue()
            ->and($manager->hasLayer('markers'))->toBeTrue();
    });

    it('gets layers by type', function () {
        $manager = LayerManager::make()
            ->addHeatmap('heat1', fn ($l) => $l->points([]))
            ->addHeatmap('heat2', fn ($l) => $l->points([]))
            ->addCluster('markers', fn ($l) => $l->markers([]));

        $heatmaps = $manager->getLayersByType('heatmap');

        expect($heatmaps)->toHaveCount(2);
    });

    it('converts to array for JavaScript', function () {
        $manager = LayerManager::make()
            ->addHeatmap('heat', fn ($l) => $l->points([[-23.5505, -46.6333, 1.0]]))
            ->setBaseLayers(['OpenStreetMap' => ['url' => 'https://tile.osm.org/{z}/{x}/{y}.png']])
            ->defaultBaseLayer('OpenStreetMap')
            ->layerControl('topleft', false);

        $array = $manager->toArray();

        expect($array)->toHaveKey('layers')
            ->and($array)->toHaveKey('baseLayers')
            ->and($array)->toHaveKey('control')
            ->and($array['defaultBaseLayer'])->toBe('OpenStreetMap')
            ->and($array['control']['position'])->toBe('topleft')
            ->and($array['control']['collapsed'])->toBeFalse();
    });

    it('calculates combined bounds', function () {
        $manager = LayerManager::make()
            ->addCluster('cluster1', fn ($l) => $l->markers([
                ['lat' => -23.55, 'lng' => -46.63],
                ['lat' => -23.56, 'lng' => -46.64],
            ]))
            ->addHeatmap('heat', fn ($l) => $l->points([
                [-23.57, -46.65, 1.0],
            ]));

        $bounds = $manager->getBounds();

        expect($bounds['minLat'])->toBe(-23.57)
            ->and($bounds['maxLat'])->toBe(-23.55)
            ->and($bounds['minLng'])->toBe(-46.65)
            ->and($bounds['maxLng'])->toBe(-46.63);
    });
});
