<?php

declare(strict_types=1);

use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasSpatialColumn;
use EduardoRibeiroDev\FilamentLeaflet\Enums\SpatialColumnType;

// Mock class for testing the trait
class SpatialColumnTestClass
{
    use HasSpatialColumn;

    protected ?string $latitudeFieldName = 'lat';

    protected ?string $longitudeFieldName = 'lng';

    public function evaluate($value)
    {
        return is_callable($value) ? $value() : $value;
    }

    public function getRecord()
    {
        return null; // For testing without a database
    }

    public function getName()
    {
        return 'location';
    }
}

describe('HasSpatialColumn Trait', function () {
    beforeEach(function () {
        $this->instance = new SpatialColumnTestClass;
    });

    describe('WKT Parsing', function () {
        it('parses POINT WKT', function () {
            $wkt = 'POINT(-46.6333 -23.5505)';
            $geoJson = $this->instance->parseWKT($wkt);

            expect($geoJson['type'])->toBe('Point')
                ->and($geoJson['coordinates'][0])->toBeCloseTo(-46.6333, 4)
                ->and($geoJson['coordinates'][1])->toBeCloseTo(-23.5505, 4);
        });

        it('parses POINT WKT with SRID prefix', function () {
            $wkt = 'SRID=4326;POINT(-46.6333 -23.5505)';
            $geoJson = $this->instance->parseWKT($wkt);

            expect($geoJson['type'])->toBe('Point');
        });

        it('parses LINESTRING WKT', function () {
            $wkt = 'LINESTRING(-46.6333 -23.5505, -46.6433 -23.5605)';
            $geoJson = $this->instance->parseWKT($wkt);

            expect($geoJson['type'])->toBe('LineString')
                ->and($geoJson['coordinates'])->toHaveCount(2);
        });

        it('parses POLYGON WKT', function () {
            $wkt = 'POLYGON((-46.6333 -23.5505, -46.6433 -23.5505, -46.6433 -23.5605, -46.6333 -23.5505))';
            $geoJson = $this->instance->parseWKT($wkt);

            expect($geoJson['type'])->toBe('Polygon')
                ->and($geoJson['coordinates'])->toHaveCount(1)
                ->and($geoJson['coordinates'][0])->toHaveCount(4);
        });

        it('parses MULTIPOINT WKT', function () {
            $wkt = 'MULTIPOINT(-46.6333 -23.5505, -46.6433 -23.5605)';
            $geoJson = $this->instance->parseWKT($wkt);

            expect($geoJson['type'])->toBe('MultiPoint')
                ->and($geoJson['coordinates'])->toHaveCount(2);
        });

        it('parses MULTILINESTRING WKT', function () {
            $wkt = 'MULTILINESTRING((-46.6333 -23.5505, -46.6433 -23.5605), (-46.7333 -23.6505, -46.7433 -23.6605))';
            $geoJson = $this->instance->parseWKT($wkt);

            expect($geoJson['type'])->toBe('MultiLineString')
                ->and($geoJson['coordinates'])->toHaveCount(2);
        });

        it('parses MULTIPOLYGON WKT', function () {
            $wkt = 'MULTIPOLYGON(((-46.6 -23.5, -46.7 -23.5, -46.7 -23.6, -46.6 -23.5)))';
            $geoJson = $this->instance->parseWKT($wkt);

            expect($geoJson['type'])->toBe('MultiPolygon')
                ->and($geoJson['coordinates'])->toHaveCount(1);
        });

        it('returns null for invalid WKT', function () {
            $wkt = 'INVALID(something)';
            $geoJson = $this->instance->parseWKT($wkt);

            expect($geoJson)->toBeNull();
        });
    });

    describe('GeoJSON to WKT Conversion', function () {
        it('converts Point to WKT', function () {
            $data = ['lat' => -23.5505, 'lng' => -46.6333];
            $wkt = $this->instance->toWKT($data);

            expect($wkt)->toContain('POINT')
                ->and($wkt)->toContain('-46.633300')
                ->and($wkt)->toContain('-23.550500');
        });

        it('converts GeoJSON Point to WKT', function () {
            $geoJson = ['type' => 'Point', 'coordinates' => [-46.6333, -23.5505]];
            $wkt = $this->instance->toWKT($geoJson);

            expect($wkt)->toBe('POINT(-46.633300 -23.550500)');
        });

        it('converts GeoJSON LineString to WKT', function () {
            $geoJson = [
                'type' => 'LineString',
                'coordinates' => [
                    [-46.6333, -23.5505],
                    [-46.6433, -23.5605],
                ],
            ];
            $wkt = $this->instance->toWKT($geoJson);

            expect($wkt)->toContain('LINESTRING');
        });

        it('converts GeoJSON Polygon to WKT', function () {
            $geoJson = [
                'type' => 'Polygon',
                'coordinates' => [[
                    [-46.6333, -23.5505],
                    [-46.6433, -23.5505],
                    [-46.6433, -23.5605],
                    [-46.6333, -23.5505],
                ]],
            ];
            $wkt = $this->instance->toWKT($geoJson);

            expect($wkt)->toContain('POLYGON');
        });
    });

    describe('GeoJSON to LatLng Conversion', function () {
        it('converts Point GeoJSON to lat/lng', function () {
            $geoJson = ['type' => 'Point', 'coordinates' => [-46.6333, -23.5505]];
            $latLng = $this->instance->geoJsonToLatLng($geoJson);

            expect($latLng['lat'])->toBeCloseTo(-23.5505, 4)
                ->and($latLng['lng'])->toBeCloseTo(-46.6333, 4);
        });

        it('passes through already formatted lat/lng', function () {
            $data = ['lat' => -23.5505, 'lng' => -46.6333];
            $latLng = $this->instance->geoJsonToLatLng($data);

            expect($latLng)->toBe($data);
        });

        it('calculates centroid for non-Point geometries', function () {
            $geoJson = [
                'type' => 'LineString',
                'coordinates' => [
                    [-46.0, -23.0],
                    [-47.0, -24.0],
                ],
            ];
            $latLng = $this->instance->geoJsonToLatLng($geoJson);

            expect($latLng['lat'])->toBeCloseTo(-23.5, 1)
                ->and($latLng['lng'])->toBeCloseTo(-46.5, 1);
        });
    });

    describe('Spatial Column Configuration', function () {
        it('sets spatial column flag', function () {
            $this->instance->spatialColumn(true);

            expect($this->instance->isSpatialColumn())->toBeTrue();
        });

        it('sets SRID', function () {
            $this->instance->srid(32633);

            expect($this->instance->getSrid())->toBe(32633);
        });

        it('sets geometry type', function () {
            $this->instance->geometryType('POLYGON');

            expect($this->instance->getGeometryType())->toBe('POLYGON');
        });

        it('defaults SRID to 4326', function () {
            expect($this->instance->getSrid())->toBe(4326);
        });
    });

    describe('PostGIS Conversion', function () {
        it('converts simple lat/lng to PostGIS format', function () {
            $data = ['lat' => -23.5505, 'lng' => -46.6333];
            $sql = $this->instance->convertToPostGIS($data);

            expect($sql)->toContain('ST_SetSRID')
                ->and($sql)->toContain('ST_GeomFromGeoJSON')
                ->and($sql)->toContain('4326')
                ->and($sql)->toContain('Point');
        });

        it('converts GeoJSON to PostGIS format', function () {
            $geoJson = ['type' => 'Point', 'coordinates' => [-46.6333, -23.5505]];
            $sql = $this->instance->convertToPostGIS($geoJson);

            expect($sql)->toContain('ST_SetSRID')
                ->and($sql)->toContain('ST_GeomFromGeoJSON');
        });

        it('uses configured SRID', function () {
            $this->instance->srid(32633);
            $data = ['lat' => -23.5505, 'lng' => -46.6333];
            $sql = $this->instance->convertToPostGIS($data);

            expect($sql)->toContain('32633');
        });
    });
});

describe('SpatialColumnType Enum', function () {
    it('has correct values', function () {
        expect(SpatialColumnType::Json->value)->toBe('json')
            ->and(SpatialColumnType::PostGIS->value)->toBe('postgis')
            ->and(SpatialColumnType::MySQLSpatial->value)->toBe('mysql_spatial');
    });

    it('identifies native spatial query support', function () {
        expect(SpatialColumnType::PostGIS->supportsNativeSpatialQueries())->toBeTrue()
            ->and(SpatialColumnType::MySQLSpatial->supportsNativeSpatialQueries())->toBeTrue()
            ->and(SpatialColumnType::Json->supportsNativeSpatialQueries())->toBeFalse();
    });

    it('identifies full PostGIS support', function () {
        expect(SpatialColumnType::PostGIS->supportsFullPostGIS())->toBeTrue()
            ->and(SpatialColumnType::MySQLSpatial->supportsFullPostGIS())->toBeFalse();
    });

    it('provides correct spatial function prefix', function () {
        expect(SpatialColumnType::PostGIS->getSpatialFunctionPrefix())->toBe('ST_')
            ->and(SpatialColumnType::MySQLSpatial->getSpatialFunctionPrefix())->toBe('ST_')
            ->and(SpatialColumnType::Json->getSpatialFunctionPrefix())->toBe('');
    });
});
