<?php

declare(strict_types=1);

use EduardoRibeiroDev\FilamentLeaflet\Support\Geometry\GeometryCollection;
use EduardoRibeiroDev\FilamentLeaflet\Support\Geometry\MultiLineString;
use EduardoRibeiroDev\FilamentLeaflet\Support\Geometry\MultiPoint;
use EduardoRibeiroDev\FilamentLeaflet\Support\Geometry\MultiPolygon;

describe('MultiPoint Geometry', function () {
    it('creates from array of points', function () {
        $points = [
            [-46.6333, -23.5505],
            [-46.6433, -23.5605],
            [-46.6533, -23.5705],
        ];

        $multiPoint = MultiPoint::make($points);

        expect($multiPoint->getPoints())->toBe($points)
            ->and($multiPoint->isValid())->toBeTrue()
            ->and($multiPoint->getType())->toBe('multipoint');
    });

    it('converts to GeoJSON', function () {
        $points = [
            [-46.6333, -23.5505],
            [-46.6433, -23.5605],
        ];

        $multiPoint = MultiPoint::make($points);
        $geoJson = $multiPoint->toGeoJson();

        expect($geoJson['type'])->toBe('MultiPoint')
            ->and($geoJson['coordinates'])->toBe($points);
    });

    it('converts to WKT', function () {
        $points = [
            [-46.6333, -23.5505],
            [-46.6433, -23.5605],
        ];

        $multiPoint = MultiPoint::make($points);
        $wkt = $multiPoint->toWKT();

        expect($wkt)->toContain('MULTIPOINT')
            ->and($wkt)->toContain('-46.633300')
            ->and($wkt)->toContain('-23.550500');
    });

    it('parses from WKT', function () {
        $wkt = 'MULTIPOINT(-46.6333 -23.5505, -46.6433 -23.5605)';
        $multiPoint = MultiPoint::fromWKT($wkt);

        expect($multiPoint->getPoints())->toHaveCount(2)
            ->and($multiPoint->getPoints()[0][0])->toBeCloseTo(-46.6333, 4);
    });

    it('parses from GeoJSON', function () {
        $geoJson = [
            'type' => 'MultiPoint',
            'coordinates' => [
                [-46.6333, -23.5505],
                [-46.6433, -23.5605],
            ],
        ];

        $multiPoint = MultiPoint::fromGeoJson($geoJson);

        expect($multiPoint->getPoints())->toHaveCount(2);
    });

    it('calculates centroid', function () {
        $points = [
            [-46.0, -23.0],
            [-46.0, -24.0],
            [-47.0, -23.0],
            [-47.0, -24.0],
        ];

        $multiPoint = MultiPoint::make($points);
        $centroid = $multiPoint->getCoordinates();

        // Centroid should be approximately [-23.5, -46.5] (lat, lng for Leaflet)
        expect($centroid[0])->toBeCloseTo(-23.5, 1)
            ->and($centroid[1])->toBeCloseTo(-46.5, 1);
    });

    it('validates empty multipoint as invalid', function () {
        $multiPoint = MultiPoint::make([]);

        expect($multiPoint->isValid())->toBeFalse();
    });
});

describe('MultiLineString Geometry', function () {
    it('creates from array of lines', function () {
        $lines = [
            [[-46.6333, -23.5505], [-46.6433, -23.5605]],
            [[-46.7333, -23.6505], [-46.7433, -23.6605]],
        ];

        $multiLine = MultiLineString::make($lines);

        expect($multiLine->getLines())->toBe($lines)
            ->and($multiLine->isValid())->toBeTrue()
            ->and($multiLine->getType())->toBe('multilinestring');
    });

    it('converts to GeoJSON', function () {
        $lines = [
            [[-46.6333, -23.5505], [-46.6433, -23.5605]],
        ];

        $multiLine = MultiLineString::make($lines);
        $geoJson = $multiLine->toGeoJson();

        expect($geoJson['type'])->toBe('MultiLineString')
            ->and($geoJson['coordinates'])->toBe($lines);
    });

    it('converts to WKT', function () {
        $lines = [
            [[-46.6333, -23.5505], [-46.6433, -23.5605]],
        ];

        $multiLine = MultiLineString::make($lines);
        $wkt = $multiLine->toWKT();

        expect($wkt)->toContain('MULTILINESTRING');
    });

    it('calculates total length', function () {
        // Create a simple line of approximately 111km (1 degree at equator)
        $lines = [
            [[0.0, 0.0], [1.0, 0.0]],
        ];

        $multiLine = MultiLineString::make($lines);
        $length = $multiLine->calculateLength();

        // 1 degree longitude at equator is approximately 111km
        expect($length)->toBeGreaterThan(100)
            ->and($length)->toBeLessThan(120);
    });

    it('validates line with single point as invalid', function () {
        $lines = [
            [[-46.6333, -23.5505]], // Only 1 point
        ];

        $multiLine = MultiLineString::make($lines);

        expect($multiLine->isValid())->toBeFalse();
    });
});

describe('MultiPolygon Geometry', function () {
    it('creates from array of polygons', function () {
        $polygons = [
            [[
                [-46.6333, -23.5505],
                [-46.6433, -23.5505],
                [-46.6433, -23.5605],
                [-46.6333, -23.5605],
                [-46.6333, -23.5505], // Close the ring
            ]],
        ];

        $multiPolygon = MultiPolygon::make($polygons);

        expect($multiPolygon->getPolygons())->toBe($polygons)
            ->and($multiPolygon->isValid())->toBeTrue()
            ->and($multiPolygon->getType())->toBe('multipolygon');
    });

    it('converts to GeoJSON', function () {
        $polygons = [
            [[
                [-46.6333, -23.5505],
                [-46.6433, -23.5505],
                [-46.6433, -23.5605],
                [-46.6333, -23.5605],
                [-46.6333, -23.5505],
            ]],
        ];

        $multiPolygon = MultiPolygon::make($polygons);
        $geoJson = $multiPolygon->toGeoJson();

        expect($geoJson['type'])->toBe('MultiPolygon')
            ->and($geoJson['coordinates'])->toBe($polygons);
    });

    it('converts to WKT', function () {
        $polygons = [
            [[
                [-46.6333, -23.5505],
                [-46.6433, -23.5505],
                [-46.6433, -23.5605],
                [-46.6333, -23.5605],
                [-46.6333, -23.5505],
            ]],
        ];

        $multiPolygon = MultiPolygon::make($polygons);
        $wkt = $multiPolygon->toWKT();

        expect($wkt)->toContain('MULTIPOLYGON');
    });

    it('counts polygons', function () {
        $polygons = [
            [[[-46.6, -23.5], [-46.7, -23.5], [-46.7, -23.6], [-46.6, -23.5]]],
            [[[-47.6, -24.5], [-47.7, -24.5], [-47.7, -24.6], [-47.6, -24.5]]],
        ];

        $multiPolygon = MultiPolygon::make($polygons);

        expect($multiPolygon->count())->toBe(2);
    });

    it('validates polygon with insufficient points as invalid', function () {
        $polygons = [
            [[
                [-46.6333, -23.5505],
                [-46.6433, -23.5505],
                [-46.6433, -23.5605],
                // Missing 4th point to close
            ]],
        ];

        $multiPolygon = MultiPolygon::make($polygons);

        expect($multiPolygon->isValid())->toBeFalse();
    });
});

describe('GeometryCollection', function () {
    it('creates with mixed geometries', function () {
        $geometries = [
            ['type' => 'Point', 'coordinates' => [-46.6333, -23.5505]],
            ['type' => 'LineString', 'coordinates' => [[-46.6333, -23.5505], [-46.6433, -23.5605]]],
        ];

        $collection = GeometryCollection::make($geometries);

        expect($collection->getGeometries())->toBe($geometries)
            ->and($collection->isValid())->toBeTrue()
            ->and($collection->count())->toBe(2);
    });

    it('adds point geometry', function () {
        $collection = GeometryCollection::make();
        $collection->addPoint(-46.6333, -23.5505);

        expect($collection->getGeometries())->toHaveCount(1)
            ->and($collection->getGeometries()[0]['type'])->toBe('Point');
    });

    it('adds linestring geometry', function () {
        $collection = GeometryCollection::make();
        $collection->addLineString([[-46.6333, -23.5505], [-46.6433, -23.5605]]);

        expect($collection->getGeometries())->toHaveCount(1)
            ->and($collection->getGeometries()[0]['type'])->toBe('LineString');
    });

    it('adds polygon geometry', function () {
        $collection = GeometryCollection::make();
        $collection->addPolygon([[
            [-46.6333, -23.5505],
            [-46.6433, -23.5505],
            [-46.6433, -23.5605],
            [-46.6333, -23.5605],
            [-46.6333, -23.5505],
        ]]);

        expect($collection->getGeometries())->toHaveCount(1)
            ->and($collection->getGeometries()[0]['type'])->toBe('Polygon');
    });

    it('converts to GeoJSON', function () {
        $geometries = [
            ['type' => 'Point', 'coordinates' => [-46.6333, -23.5505]],
        ];

        $collection = GeometryCollection::make($geometries);
        $geoJson = $collection->toGeoJson();

        expect($geoJson['type'])->toBe('GeometryCollection')
            ->and($geoJson['geometries'])->toBe($geometries);
    });

    it('converts to WKT', function () {
        $collection = GeometryCollection::make();
        $collection->addPoint(-46.6333, -23.5505);
        $collection->addLineString([[-46.6333, -23.5505], [-46.6433, -23.5605]]);

        $wkt = $collection->toWKT();

        expect($wkt)->toContain('GEOMETRYCOLLECTION')
            ->and($wkt)->toContain('POINT')
            ->and($wkt)->toContain('LINESTRING');
    });

    it('parses from GeoJSON', function () {
        $geoJson = [
            'type' => 'GeometryCollection',
            'geometries' => [
                ['type' => 'Point', 'coordinates' => [-46.6333, -23.5505]],
            ],
        ];

        $collection = GeometryCollection::fromGeoJson($geoJson);

        expect($collection->count())->toBe(1);
    });

    it('validates empty collection as invalid', function () {
        $collection = GeometryCollection::make([]);

        expect($collection->isValid())->toBeFalse();
    });
});
