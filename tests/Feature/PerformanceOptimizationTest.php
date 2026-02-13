<?php

declare(strict_types=1);

use App\Models\Infrastructure;
use App\Models\Subdivision;
use App\Support\Spatial\GeometrySimplifier;
use App\Support\Spatial\SpatialCache;
use App\Support\Spatial\SpatialIndex;
use App\Support\Spatial\ViewportLoader;
use Illuminate\Support\Facades\Cache;

describe('SpatialCache', function () {
    beforeEach(function () {
        Cache::flush();
    });

    it('caches viewport queries', function () {
        $cache = new SpatialCache;
        $bounds = ['minLat' => -24, 'maxLat' => -23, 'minLng' => -47, 'maxLng' => -46];
        $callCount = 0;

        $result1 = $cache->viewport('test', $bounds, function () use (&$callCount) {
            $callCount++;

            return 'cached data';
        });

        $result2 = $cache->viewport('test', $bounds, function () use (&$callCount) {
            $callCount++;

            return 'should not be called';
        });

        expect($result1)->toBe('cached data')
            ->and($result2)->toBe('cached data')
            ->and($callCount)->toBe(1);
    });

    it('caches tile queries', function () {
        $cache = new SpatialCache;
        $callCount = 0;

        $result = $cache->tile('markers', -23.55, -46.63, 10, function () use (&$callCount) {
            $callCount++;

            return ['data'];
        });

        expect($result)->toBe(['data'])
            ->and($callCount)->toBe(1);
    });

    it('invalidates viewport cache', function () {
        $cache = new SpatialCache;
        $bounds = ['minLat' => -24, 'maxLat' => -23, 'minLng' => -47, 'maxLng' => -46];

        $cache->viewport('test', $bounds, fn () => 'original');
        $cache->invalidateViewport('test', $bounds);

        $result = $cache->viewport('test', $bounds, fn () => 'new data');

        expect($result)->toBe('new data');
    });

    it('configures TTL and tile size', function () {
        $cache = (new SpatialCache)
            ->ttl(600)
            ->tileSize(0.05)
            ->prefix('custom');

        $stats = $cache->getStats();

        expect($stats['ttl'])->toBe(600)
            ->and($stats['tile_size'])->toBe(0.05)
            ->and($stats['prefix'])->toBe('custom');
    });
});

describe('ViewportLoader', function () {
    beforeEach(function () {
        $this->subdivision = Subdivision::factory()->create();
    });

    it('loads features within bounds', function () {
        Infrastructure::factory()->create([
            'subdivision_id' => $this->subdivision->id,
            'location' => ['lat' => -23.55, 'lng' => -46.63],
        ]);
        Infrastructure::factory()->create([
            'subdivision_id' => $this->subdivision->id,
            'location' => ['lat' => -23.56, 'lng' => -46.64],
        ]);
        Infrastructure::factory()->create([
            'subdivision_id' => $this->subdivision->id,
            'location' => ['lat' => -25.00, 'lng' => -48.00], // Outside bounds
        ]);

        $loader = ViewportLoader::make(Infrastructure::class)
            ->geometryColumn('location')
            ->withinBounds([
                'minLat' => -24,
                'maxLat' => -23,
                'minLng' => -47,
                'maxLng' => -46,
            ]);

        $data = $loader->load();

        expect($data)->toHaveCount(2);
    });

    it('limits results', function () {
        Infrastructure::factory(5)->create([
            'subdivision_id' => $this->subdivision->id,
            'location' => ['lat' => -23.55, 'lng' => -46.63],
        ]);

        $loader = ViewportLoader::make(Infrastructure::class)
            ->geometryColumn('location')
            ->limit(3);

        $data = $loader->load();

        expect($data)->toHaveCount(3);
    });

    it('counts features in bounds', function () {
        Infrastructure::factory(5)->create([
            'subdivision_id' => $this->subdivision->id,
            'location' => ['lat' => -23.55, 'lng' => -46.63],
        ]);

        $loader = ViewportLoader::make(Infrastructure::class)
            ->geometryColumn('location')
            ->withinBounds([
                'minLat' => -24,
                'maxLat' => -23,
                'minLng' => -47,
                'maxLng' => -46,
            ]);

        expect($loader->count())->toBe(5);
    });

    it('converts to GeoJSON', function () {
        Infrastructure::factory()->create([
            'subdivision_id' => $this->subdivision->id,
            'name' => 'Test Feature',
            'location' => ['lat' => -23.55, 'lng' => -46.63],
        ]);

        $loader = ViewportLoader::make(Infrastructure::class)
            ->geometryColumn('location');

        $geoJson = $loader->toGeoJson();

        expect($geoJson['type'])->toBe('FeatureCollection')
            ->and($geoJson['features'])->toHaveCount(1)
            ->and($geoJson['features'][0]['geometry']['type'])->toBe('Point')
            ->and($geoJson['features'][0]['properties']['name'])->toBe('Test Feature');
    });

    it('applies custom constraints', function () {
        Infrastructure::factory()->create([
            'subdivision_id' => $this->subdivision->id,
            'name' => 'Active',
            'location' => ['lat' => -23.55, 'lng' => -46.63],
        ]);
        Infrastructure::factory()->create([
            'subdivision_id' => $this->subdivision->id,
            'name' => 'Inactive',
            'location' => ['lat' => -23.56, 'lng' => -46.64],
        ]);

        $loader = ViewportLoader::make(Infrastructure::class)
            ->geometryColumn('location')
            ->where(fn ($query) => $query->where('name', 'Active'));

        $data = $loader->load();

        expect($data)->toHaveCount(1)
            ->and($data[0]->name)->toBe('Active');
    });
});

describe('GeometrySimplifier', function () {
    beforeEach(function () {
        $this->simplifier = new GeometrySimplifier;
    });

    it('simplifies a polyline with Douglas-Peucker', function () {
        $coordinates = [
            [-46.63, -23.55],
            [-46.631, -23.551], // Should be removed (close to line)
            [-46.632, -23.552], // Should be removed
            [-46.65, -23.57],   // Should be kept (significant)
            [-46.651, -23.571], // Should be removed
            [-46.67, -23.59],
        ];

        $simplified = $this->simplifier->douglasPeucker($coordinates, 0.01);

        expect(count($simplified))->toBeLessThan(count($coordinates))
            ->and($simplified[0])->toBe($coordinates[0])
            ->and($simplified[count($simplified) - 1])->toBe($coordinates[count($coordinates) - 1]);
    });

    it('simplifies with Visvalingam algorithm', function () {
        $coordinates = [
            [-46.63, -23.55],
            [-46.64, -23.56],
            [-46.65, -23.55],
            [-46.66, -23.56],
            [-46.67, -23.55],
        ];

        $simplified = $this->simplifier->visvalingam($coordinates, 3);

        expect($simplified)->toHaveCount(3);
    });

    it('simplifies a polygon', function () {
        $rings = [
            [
                [-46.63, -23.55],
                [-46.631, -23.551],
                [-46.65, -23.55],
                [-46.65, -23.57],
                [-46.63, -23.57],
                [-46.63, -23.55],
            ],
        ];

        $simplified = $this->simplifier->simplifyPolygon($rings, 0.01);

        expect(count($simplified[0]))->toBeLessThanOrEqual(count($rings[0]))
            ->and($simplified[0][0])->toBe($simplified[0][count($simplified[0]) - 1]); // Ring is closed
    });

    it('simplifies GeoJSON geometry', function () {
        $geometry = [
            'type' => 'LineString',
            'coordinates' => [
                [-46.63, -23.55],
                [-46.631, -23.551],
                [-46.65, -23.57],
                [-46.651, -23.571],
                [-46.67, -23.59],
            ],
        ];

        $simplified = $this->simplifier->simplifyGeoJson($geometry, 0.01);

        expect($simplified['type'])->toBe('LineString')
            ->and(count($simplified['coordinates']))->toBeLessThanOrEqual(count($geometry['coordinates']));
    });

    it('calculates tolerance for zoom level', function () {
        $tolerance0 = $this->simplifier->toleranceForZoom(0);
        $tolerance10 = $this->simplifier->toleranceForZoom(10);
        $tolerance20 = $this->simplifier->toleranceForZoom(20);

        expect($tolerance0)->toBeGreaterThan($tolerance10)
            ->and($tolerance10)->toBeGreaterThan($tolerance20);
    });

    it('counts points in geometry', function () {
        $point = ['type' => 'Point', 'coordinates' => [-46.63, -23.55]];
        $line = ['type' => 'LineString', 'coordinates' => [[-46.63, -23.55], [-46.64, -23.56], [-46.65, -23.57]]];
        $polygon = [
            'type' => 'Polygon',
            'coordinates' => [[[-46.63, -23.55], [-46.65, -23.55], [-46.65, -23.57], [-46.63, -23.57], [-46.63, -23.55]]],
        ];

        expect($this->simplifier->countPoints($point))->toBe(1)
            ->and($this->simplifier->countPoints($line))->toBe(3)
            ->and($this->simplifier->countPoints($polygon))->toBe(5);
    });

    it('calculates compression ratio', function () {
        $original = [
            'type' => 'LineString',
            'coordinates' => [
                [-46.63, -23.55],
                [-46.64, -23.56],
                [-46.65, -23.57],
                [-46.66, -23.58],
                [-46.67, -23.59],
            ],
        ];

        $simplified = $this->simplifier->simplifyGeoJson($original, 0.05);
        $ratio = $this->simplifier->compressionRatio($original, $simplified);

        expect($ratio)->toBeGreaterThanOrEqual(0)
            ->and($ratio)->toBeLessThanOrEqual(1);
    });
});

describe('SpatialIndex', function () {
    it('retrieves table indexes', function () {
        $index = new SpatialIndex;
        $indexes = $index->getIndexes('infrastructures');

        expect($indexes)->toBeArray();
    });

    it('retrieves table statistics', function () {
        $index = new SpatialIndex;
        $stats = $index->getTableStats('infrastructures');

        expect($stats)->toHaveKey('row_count')
            ->and($stats['row_count'])->toBeInt();
    });

    it('suggests indexes for spatial columns', function () {
        $index = new SpatialIndex;
        $suggestions = $index->suggestIndexes('infrastructures', 'location');

        expect($suggestions)->toBeArray();
    });
});
