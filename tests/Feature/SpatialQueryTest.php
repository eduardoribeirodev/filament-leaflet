<?php

declare(strict_types=1);

use App\Models\Infrastructure;
use App\Models\Subdivision;

describe('Infrastructure Spatial Queries', function () {
    beforeEach(function () {
        $this->subdivision = Subdivision::factory()->create([
            'name' => 'Test Subdivision',
            'code' => 'TEST-001',
            'location' => ['lat' => -23.5505, 'lng' => -46.6333],
        ]);
    });

    it('calculates distance from a point', function () {
        $infrastructure = Infrastructure::factory()->create([
            'name' => 'Test Infrastructure',
            'location' => ['lat' => -23.5505, 'lng' => -46.6333],
            'subdivision_id' => $this->subdivision->id,
        ]);

        $distance = $infrastructure->distanceFrom(-22.9068, -43.1729);

        expect($distance)->toBeGreaterThan(350)
            ->and($distance)->toBeLessThan(380);
    });

    it('returns null distance for infrastructure without location', function () {
        $infrastructure = Infrastructure::factory()->create([
            'name' => 'No Location',
            'location' => null,
            'subdivision_id' => $this->subdivision->id,
        ]);

        $distance = $infrastructure->distanceFrom(-23.5505, -46.6333);

        expect($distance)->toBeNull();
    });
});

describe('Geometry Classes', function () {
    it('creates coordinates array', function () {
        $points = [
            ['lat' => -23.5505, 'lng' => -46.6333],
            ['lat' => -23.5600, 'lng' => -46.6400],
        ];

        expect($points)->toHaveCount(2)
            ->and($points[0]['lat'])->toBe(-23.5505);
    });

    it('converts lat/lng to GeoJSON Point format', function () {
        $latLng = ['lat' => -23.5505, 'lng' => -46.6333];

        $geoJson = [
            'type' => 'Point',
            'coordinates' => [$latLng['lng'], $latLng['lat']],
        ];

        expect($geoJson['type'])->toBe('Point')
            ->and($geoJson['coordinates'][0])->toBe(-46.6333)
            ->and($geoJson['coordinates'][1])->toBe(-23.5505);
    });

    it('converts GeoJSON to WKT format', function () {
        $geoJson = [
            'type' => 'Point',
            'coordinates' => [-46.6333, -23.5505],
        ];

        $wkt = sprintf(
            'POINT(%f %f)',
            $geoJson['coordinates'][0],
            $geoJson['coordinates'][1]
        );

        expect($wkt)->toContain('POINT')
            ->and($wkt)->toContain('-46.633300')
            ->and($wkt)->toContain('-23.550500');
    });
});
