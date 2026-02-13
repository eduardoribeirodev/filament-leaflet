<?php

declare(strict_types=1);

use App\Models\Infrastructure;
use App\Models\Subdivision;
use App\Models\User;

describe('SpatialDataController', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->subdivision = Subdivision::factory()->create();
    });

    describe('models endpoint', function () {
        it('returns list of available models', function () {
            $response = $this->getJson('/api/spatial/models');

            $response->assertOk()
                ->assertJsonStructure(['models'])
                ->assertJsonFragment(['models' => ['infrastructures', 'subdivisions']]);
        });
    });

    describe('features endpoint', function () {
        it('returns features within bounds', function () {
            Infrastructure::factory(3)->create([
                'subdivision_id' => $this->subdivision->id,
                'location' => ['lat' => -23.55, 'lng' => -46.63],
            ]);

            $response = $this->getJson('/api/spatial/infrastructures/features?'.http_build_query([
                'bounds' => [
                    'minLat' => -24,
                    'maxLat' => -23,
                    'minLng' => -47,
                    'maxLng' => -46,
                ],
                'zoom' => 12,
                'cluster' => false,
            ]));

            $response->assertOk()
                ->assertJsonStructure(['type', 'data', 'total']);
        });

        it('returns 404 for invalid model', function () {
            $response = $this->getJson('/api/spatial/invalid/features?'.http_build_query([
                'bounds' => [
                    'minLat' => -24,
                    'maxLat' => -23,
                    'minLng' => -47,
                    'maxLng' => -46,
                ],
            ]));

            $response->assertNotFound();
        });

        it('validates bounds parameters', function () {
            $response = $this->getJson('/api/spatial/infrastructures/features?'.http_build_query([
                'bounds' => [
                    'minLat' => -100, // Invalid
                    'maxLat' => -23,
                    'minLng' => -47,
                    'maxLng' => -46,
                ],
            ]));

            $response->assertUnprocessable();
        });
    });

    describe('geojson endpoint', function () {
        it('returns GeoJSON FeatureCollection', function () {
            Infrastructure::factory()->create([
                'subdivision_id' => $this->subdivision->id,
                'name' => 'Test Feature',
                'location' => ['lat' => -23.55, 'lng' => -46.63],
            ]);

            $response = $this->getJson('/api/spatial/infrastructures/geojson');

            $response->assertOk()
                ->assertJson([
                    'type' => 'FeatureCollection',
                ])
                ->assertJsonStructure([
                    'type',
                    'features' => [
                        '*' => [
                            'type',
                            'geometry',
                            'properties',
                        ],
                    ],
                ]);
        });

        it('filters by bounds when provided', function () {
            Infrastructure::factory()->create([
                'subdivision_id' => $this->subdivision->id,
                'location' => ['lat' => -23.55, 'lng' => -46.63],
            ]);
            Infrastructure::factory()->create([
                'subdivision_id' => $this->subdivision->id,
                'location' => ['lat' => -30.00, 'lng' => -50.00], // Outside bounds
            ]);

            $response = $this->getJson('/api/spatial/infrastructures/geojson?'.http_build_query([
                'bounds' => [
                    'minLat' => -24,
                    'maxLat' => -23,
                    'minLng' => -47,
                    'maxLng' => -46,
                ],
            ]));

            $response->assertOk();
            expect($response->json('features'))->toHaveCount(1);
        });
    });

    describe('count endpoint', function () {
        it('returns feature count', function () {
            Infrastructure::factory(5)->create([
                'subdivision_id' => $this->subdivision->id,
                'location' => ['lat' => -23.55, 'lng' => -46.63],
            ]);

            $response = $this->getJson('/api/spatial/infrastructures/count');

            $response->assertOk()
                ->assertJson(['count' => 5]);
        });

        it('filters count by bounds', function () {
            Infrastructure::factory(3)->create([
                'subdivision_id' => $this->subdivision->id,
                'location' => ['lat' => -23.55, 'lng' => -46.63],
            ]);
            Infrastructure::factory(2)->create([
                'subdivision_id' => $this->subdivision->id,
                'location' => ['lat' => -30.00, 'lng' => -50.00],
            ]);

            $response = $this->getJson('/api/spatial/infrastructures/count?'.http_build_query([
                'bounds' => [
                    'minLat' => -24,
                    'maxLat' => -23,
                    'minLng' => -47,
                    'maxLng' => -46,
                ],
            ]));

            $response->assertOk()
                ->assertJson(['count' => 3]);
        });
    });

    describe('distance endpoint', function () {
        it('calculates distance between two points', function () {
            $response = $this->postJson('/api/spatial/distance', [
                'from' => ['lat' => -23.5505, 'lng' => -46.6333],
                'to' => ['lat' => -22.9068, 'lng' => -43.1729],
                'unit' => 'km',
            ]);

            $response->assertOk()
                ->assertJsonStructure([
                    'distance',
                    'unit',
                    'bearing',
                    'compass',
                    'formatted',
                ]);

            // São Paulo to Rio is ~360km
            expect($response->json('distance'))->toBeGreaterThan(350)
                ->and($response->json('distance'))->toBeLessThan(380)
                ->and($response->json('unit'))->toBe('km');
        });

        it('validates coordinates', function () {
            $response = $this->postJson('/api/spatial/distance', [
                'from' => ['lat' => -100, 'lng' => -46.63], // Invalid
                'to' => ['lat' => -22.90, 'lng' => -43.17],
            ]);

            $response->assertUnprocessable();
        });
    });

    describe('area endpoint', function () {
        it('calculates polygon area', function () {
            $response = $this->postJson('/api/spatial/area', [
                'coordinates' => [
                    ['lat' => -23.55, 'lng' => -46.63],
                    ['lat' => -23.55, 'lng' => -46.62],
                    ['lat' => -23.54, 'lng' => -46.62],
                    ['lat' => -23.54, 'lng' => -46.63],
                ],
                'unit' => 'km2',
            ]);

            $response->assertOk()
                ->assertJsonStructure([
                    'area',
                    'unit',
                    'formatted',
                ]);

            expect($response->json('area'))->toBeGreaterThan(0)
                ->and($response->json('unit'))->toBe('km2');
        });

        it('validates minimum coordinates', function () {
            $response = $this->postJson('/api/spatial/area', [
                'coordinates' => [
                    ['lat' => -23.55, 'lng' => -46.63],
                    ['lat' => -23.54, 'lng' => -46.62],
                ],
                'unit' => 'km2',
            ]);

            $response->assertUnprocessable();
        });
    });

    describe('cache invalidation endpoint', function () {
        it('invalidates cache for model', function () {
            $response = $this->postJson('/api/spatial/infrastructures/cache/invalidate');

            $response->assertOk()
                ->assertJson(['success' => true]);
        });

        it('invalidates cache for specific bounds', function () {
            $response = $this->postJson('/api/spatial/infrastructures/cache/invalidate', [
                'bounds' => [
                    'minLat' => -24,
                    'maxLat' => -23,
                    'minLng' => -47,
                    'maxLng' => -46,
                ],
            ]);

            $response->assertOk()
                ->assertJson(['success' => true]);
        });
    });
});
