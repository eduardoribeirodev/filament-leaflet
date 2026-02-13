<?php

declare(strict_types=1);

use App\Support\Spatial\CoordinateConverter;
use App\Support\Spatial\GeocoderResult;
use App\Support\Spatial\MeasurementTools;

describe('MeasurementTools', function () {
    beforeEach(function () {
        $this->tools = new MeasurementTools;
    });

    it('calculates distance between two points using Haversine', function () {
        // São Paulo to Rio de Janeiro (~360km)
        $saoPaulo = [-23.5505, -46.6333];
        $rio = [-22.9068, -43.1729];

        $distance = $this->tools->distance($saoPaulo, $rio);

        expect($distance)->toBeGreaterThan(350)
            ->and($distance)->toBeLessThan(380);
    });

    it('calculates distance in different units', function () {
        $point1 = [-23.5505, -46.6333];
        $point2 = [-23.5600, -46.6400];

        $km = $this->tools->distance($point1, $point2, 'km');
        $m = $this->tools->distance($point1, $point2, 'm');
        $mi = $this->tools->distance($point1, $point2, 'mi');

        // m should be roughly km * 1000
        expect($m)->toBeGreaterThan($km * 999)
            ->and($m)->toBeLessThan($km * 1001)
            ->and($mi)->toBeLessThan($km);
    });

    it('calculates polyline length', function () {
        $points = [
            [-23.55, -46.63],
            [-23.56, -46.64],
            [-23.57, -46.65],
        ];

        $length = $this->tools->polylineLength($points);

        expect($length)->toBeGreaterThan(0);
    });

    it('calculates polygon area', function () {
        // Small polygon (~1km x 1km)
        $polygon = [
            [-23.55, -46.63],
            [-23.55, -46.62],
            [-23.54, -46.62],
            [-23.54, -46.63],
            [-23.55, -46.63],
        ];

        $area = $this->tools->polygonArea($polygon);

        expect($area)->toBeGreaterThan(0)
            ->and($area)->toBeLessThan(10); // Should be around 1 km²
    });

    it('calculates initial bearing', function () {
        $point1 = [-23.5505, -46.6333];
        $point2 = [-22.9068, -43.1729]; // Northeast direction

        $bearing = $this->tools->bearing($point1, $point2);

        expect($bearing)->toBeGreaterThan(0)
            ->and($bearing)->toBeLessThan(90); // Northeast quadrant
    });

    it('calculates destination point', function () {
        $start = [-23.5505, -46.6333];
        $bearing = 45; // Northeast
        $distance = 100; // 100 km

        $destination = $this->tools->destination($start, $bearing, $distance);

        expect($destination[0])->toBeGreaterThan($start[0]) // More north
            ->and($destination[1])->toBeGreaterThan($start[1]); // More east
    });

    it('calculates midpoint', function () {
        $point1 = [-23.55, -46.63];
        $point2 = [-23.57, -46.65];

        $midpoint = $this->tools->midpoint($point1, $point2);

        expect($midpoint[0])->toBeBetween(-23.565, -23.555)
            ->and($midpoint[1])->toBeBetween(-46.645, -46.635);
    });

    it('calculates centroid', function () {
        $polygon = [
            [-23.55, -46.63],
            [-23.55, -46.62],
            [-23.54, -46.62],
            [-23.54, -46.63],
        ];

        $centroid = $this->tools->centroid($polygon);

        expect($centroid[0])->toBeBetween(-23.55, -23.54)
            ->and($centroid[1])->toBeBetween(-46.63, -46.62);
    });

    it('checks point in polygon', function () {
        $polygon = [
            [-23.55, -46.65],
            [-23.55, -46.62],
            [-23.52, -46.62],
            [-23.52, -46.65],
        ];

        $inside = [-23.53, -46.63];
        $outside = [-23.60, -46.60];

        expect($this->tools->pointInPolygon($inside, $polygon))->toBeTrue()
            ->and($this->tools->pointInPolygon($outside, $polygon))->toBeFalse();
    });

    it('calculates bounding box', function () {
        $points = [
            [-23.55, -46.65],
            [-23.50, -46.60],
            [-23.60, -46.70],
        ];

        $bbox = $this->tools->boundingBox($points);

        expect($bbox['minLat'])->toBe(-23.60)
            ->and($bbox['maxLat'])->toBe(-23.50)
            ->and($bbox['minLng'])->toBe(-46.70)
            ->and($bbox['maxLng'])->toBe(-46.60);
    });

    it('creates buffer around point', function () {
        $center = [-23.55, -46.63];
        $radius = 1; // 1 km

        $buffer = $this->tools->buffer($center, $radius, 8);

        expect($buffer)->toHaveCount(9); // 8 segments + closed ring
    });

    it('formats distance appropriately', function () {
        expect($this->tools->formatDistance(0.5))->toBe('500 m')
            ->and($this->tools->formatDistance(5.25))->toBe('5.25 km')
            ->and($this->tools->formatDistance(150.5))->toBe('150.5 km');
    });

    it('converts bearing to compass direction', function () {
        expect($this->tools->bearingToCompass(0))->toBe('N')
            ->and($this->tools->bearingToCompass(45))->toBe('NE')
            ->and($this->tools->bearingToCompass(90))->toBe('E')
            ->and($this->tools->bearingToCompass(180))->toBe('S')
            ->and($this->tools->bearingToCompass(270))->toBe('W');
    });
});

describe('CoordinateConverter', function () {
    beforeEach(function () {
        $this->converter = new CoordinateConverter;
    });

    it('converts decimal degrees to DMS', function () {
        $result = $this->converter->toDMS(-23.550520, -46.633308);

        expect($result['lat'])->toContain('23')
            ->and($result['lat'])->toContain('S')
            ->and($result['lng'])->toContain('46')
            ->and($result['lng'])->toContain('W');
    });

    it('converts decimal degrees to DDM', function () {
        $result = $this->converter->toDDM(-23.550520, -46.633308);

        expect($result['lat'])->toContain('23')
            ->and($result['lat'])->toContain('S')
            ->and($result['lng'])->toContain('46')
            ->and($result['lng'])->toContain('W');
    });

    it('converts DMS to decimal degrees', function () {
        // Use simple format that the regex can parse
        $result = $this->converter->fromDMS("23d33'01.9\"S", "46d37'59.9\"W");

        expect($result['lat'])->toBeBetween(-23.56, -23.54)
            ->and($result['lng'])->toBeBetween(-46.65, -46.62);
    });

    it('converts to UTM', function () {
        $result = $this->converter->toUTM(-23.550520, -46.633308);

        expect($result['zone'])->toBe(23)
            ->and($result['band'])->toBe('K')
            ->and($result['easting'])->toBeGreaterThan(300000)
            ->and($result['northing'])->toBeGreaterThan(7000000);
    });

    it('converts from UTM', function () {
        // Convert to UTM first
        $utm = $this->converter->toUTM(-23.550520, -46.633308);

        // Convert back
        $result = $this->converter->fromUTM($utm['zone'], $utm['band'], $utm['easting'], $utm['northing']);

        expect($result['lat'])->toBeBetween(-23.56, -23.54)
            ->and($result['lng'])->toBeBetween(-46.65, -46.62);
    });

    it('converts to GeoJSON', function () {
        $result = $this->converter->toGeoJSON(-23.5505, -46.6333);

        expect($result['type'])->toBe('Point')
            ->and($result['coordinates'][0])->toBe(-46.6333) // GeoJSON is [lng, lat]
            ->and($result['coordinates'][1])->toBe(-23.5505);
    });

    it('converts from GeoJSON', function () {
        $geoJson = ['type' => 'Point', 'coordinates' => [-46.6333, -23.5505]];

        $result = $this->converter->fromGeoJSON($geoJson);

        expect($result['lat'])->toBe(-23.5505)
            ->and($result['lng'])->toBe(-46.6333);
    });

    it('validates coordinates', function () {
        expect($this->converter->isValidLatitude(45.0))->toBeTrue()
            ->and($this->converter->isValidLatitude(95.0))->toBeFalse()
            ->and($this->converter->isValidLongitude(-120.0))->toBeTrue()
            ->and($this->converter->isValidLongitude(-200.0))->toBeFalse();
    });

    it('normalizes longitude', function () {
        expect($this->converter->normalizeLongitude(190))->toBe(-170.0)
            ->and($this->converter->normalizeLongitude(-200))->toBe(160.0)
            ->and($this->converter->normalizeLongitude(90))->toBe(90.0);
    });
});

describe('GeocoderResult', function () {
    it('creates a geocoder result', function () {
        $result = new GeocoderResult(
            latitude: -23.5505,
            longitude: -46.6333,
            displayName: 'São Paulo, Brazil',
            street: 'Paulista Avenue',
            city: 'São Paulo',
            state: 'SP',
            country: 'Brazil',
            countryCode: 'BR',
            postalCode: '01310-100',
            confidence: 0.95,
            provider: 'nominatim',
        );

        expect($result->latitude)->toBe(-23.5505)
            ->and($result->longitude)->toBe(-46.6333)
            ->and($result->city)->toBe('São Paulo')
            ->and($result->countryCode)->toBe('BR');
    });

    it('converts to array', function () {
        $result = new GeocoderResult(
            latitude: -23.5505,
            longitude: -46.6333,
            displayName: '',
            street: '',
            city: '',
            state: '',
            country: '',
            countryCode: '',
            postalCode: '',
            confidence: 1.0,
            provider: 'test',
        );

        $array = $result->toArray();

        expect($array['lat'])->toBe(-23.5505)
            ->and($array['lng'])->toBe(-46.6333);
    });

    it('converts to GeoJSON', function () {
        $result = new GeocoderResult(
            latitude: -23.5505,
            longitude: -46.6333,
            displayName: '',
            street: '',
            city: '',
            state: '',
            country: '',
            countryCode: '',
            postalCode: '',
            confidence: 1.0,
            provider: 'test',
        );

        $geoJson = $result->toGeoJSON();

        expect($geoJson['type'])->toBe('Point')
            ->and($geoJson['coordinates'])->toBe([-46.6333, -23.5505]);
    });

    it('gets formatted address', function () {
        $result = new GeocoderResult(
            latitude: -23.5505,
            longitude: -46.6333,
            displayName: '',
            street: 'Paulista Avenue',
            city: 'São Paulo',
            state: 'SP',
            country: 'Brazil',
            countryCode: 'BR',
            postalCode: '01310-100',
            confidence: 1.0,
            provider: 'test',
        );

        $formatted = $result->getFormattedAddress();

        expect($formatted)->toContain('Paulista Avenue')
            ->and($formatted)->toContain('São Paulo')
            ->and($formatted)->toContain('Brazil');
    });
});
