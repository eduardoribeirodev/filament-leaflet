<?php

declare(strict_types=1);
use App\Models\Infrastructure;
use App\Models\Subdivision;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});
describe('MapPicker Data Handling', function () {
    it('stores subdivision with simple lat/lng location via model', function () {
        $subdivision = Subdivision::create([
            'name' => 'Test Subdivision',
            'code' => 'SUB-001',
            'description' => 'A test subdivision',
            'location' => [
                'latitude' => -23.5505,
                'longitude' => -46.6333,
            ],
        ]);
        expect($subdivision)->not->toBeNull()
            ->and($subdivision->name)->toBe('Test Subdivision')
            ->and($subdivision->location)->toBeArray()
            ->and($subdivision->location['latitude'])->toBe(-23.5505);
    });
    it('stores subdivision with null location', function () {
        $subdivision = Subdivision::create([
            'name' => 'Test Subdivision No Location',
            'code' => 'SUB-002',
            'description' => 'A subdivision without location',
            'location' => null,
        ]);
        expect($subdivision)->not->toBeNull()
            ->and($subdivision->location)->toBeNull();
    });
    it('stores infrastructure with polygon geometry', function () {
        $subdivision = Subdivision::factory()->create();
        $polygonData = [
            'latitude' => -23.5507,
            'longitude' => -46.6336,
            'type' => 'polygon',
            'points' => [
                [-23.5505, -46.6333],
                [-23.5510, -46.6333],
                [-23.5510, -46.6340],
                [-23.5505, -46.6340],
                [-23.5505, -46.6333],
            ],
        ];
        $infrastructure = Infrastructure::create([
            'name' => 'Test Building',
            'description' => 'A test building',
            'type' => 'school',
            'subdivision_id' => $subdivision->id,
            'location' => $polygonData,
        ]);
        expect($infrastructure)->not->toBeNull()
            ->and($infrastructure->location)->toBeArray()
            ->and($infrastructure->location['type'])->toBe('polygon')
            ->and($infrastructure->location['points'])->toHaveCount(5);
    });
    it('stores infrastructure with simple marker location', function () {
        $subdivision = Subdivision::factory()->create();
        $infrastructure = Infrastructure::create([
            'name' => 'Test Point Building',
            'description' => 'A building with point location',
            'type' => 'hospital',
            'subdivision_id' => $subdivision->id,
            'location' => [
                'latitude' => -23.5505,
                'longitude' => -46.6333,
            ],
        ]);
        expect($infrastructure)->not->toBeNull()
            ->and($infrastructure->location)->toBeArray()
            ->and($infrastructure->location['latitude'])->toBe(-23.5505);
    });
    it('handles empty location gracefully', function () {
        $subdivision = Subdivision::factory()->create();
        $infrastructure = Infrastructure::create([
            'name' => 'Test No Map',
            'description' => 'Infrastructure without map data',
            'type' => 'road',
            'subdivision_id' => $subdivision->id,
            'location' => null,
        ]);
        expect($infrastructure)->not->toBeNull()
            ->and($infrastructure->location)->toBeNull();
    });
});
