<?php

declare(strict_types=1);

use App\Models\Infrastructure;
use App\Models\Subdivision;
use App\Support\Geo\CsvGeoImporter;
use App\Support\Geo\GeoJsonExporter;
use App\Support\Geo\GeoJsonImporter;
use App\Support\Geo\GpxImporter;
use App\Support\Geo\KmlExporter;
use App\Support\Geo\KmlImporter;

describe('GeoJsonImporter', function () {
    it('parses a FeatureCollection', function () {
        $geoJson = json_encode([
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => ['type' => 'Point', 'coordinates' => [-46.6333, -23.5505]],
                    'properties' => ['name' => 'Test Point'],
                ],
            ],
        ]);

        $importer = new GeoJsonImporter;
        $features = $importer->fromString($geoJson);

        expect($features)->toHaveCount(1)
            ->and($features[0]['geometry_type'])->toBe('Point')
            ->and($features[0]['longitude'])->toBe(-46.6333)
            ->and($features[0]['latitude'])->toBe(-23.5505)
            ->and($features[0]['properties']['name'])->toBe('Test Point');
    });

    it('parses a single Feature', function () {
        $geoJson = json_encode([
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [-46.6333, -23.5505]],
            'properties' => ['name' => 'Single Point'],
        ]);

        $importer = new GeoJsonImporter;
        $features = $importer->fromString($geoJson);

        expect($features)->toHaveCount(1)
            ->and($features[0]['properties']['name'])->toBe('Single Point');
    });

    it('validates GeoJSON structure', function () {
        $importer = new GeoJsonImporter;

        $validJson = json_encode(['type' => 'FeatureCollection', 'features' => []]);
        $invalidJson = '{"invalid": "json"';

        expect($importer->validate($validJson))->toBeEmpty()
            ->and($importer->validate($invalidJson))->not->toBeEmpty();
    });

    it('maps properties to model attributes', function () {
        $geoJson = json_encode([
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => ['type' => 'Point', 'coordinates' => [-46.6333, -23.5505]],
                    'properties' => ['title' => 'Test', 'desc' => 'Description'],
                ],
            ],
        ]);

        $importer = (new GeoJsonImporter)
            ->mapProperties([
                'name' => 'properties.title',
                'description' => 'properties.desc',
            ])
            ->geometryColumn('location');

        $features = $importer->fromString($geoJson);

        expect($features[0]['properties']['title'])->toBe('Test');
    });
});

describe('GeoJsonExporter', function () {
    it('exports model data to GeoJSON', function () {
        $subdivision = Subdivision::factory()->create();
        Infrastructure::factory(2)->create([
            'subdivision_id' => $subdivision->id,
            'location' => ['lat' => -23.5505, 'lng' => -46.6333],
        ]);

        $exporter = new GeoJsonExporter;
        $result = $exporter
            ->fromModel(Infrastructure::class)
            ->geometryColumn('location')
            ->properties(['name'])
            ->toArray();

        expect($result['type'])->toBe('FeatureCollection')
            ->and($result['features'])->toHaveCount(2)
            ->and($result['features'][0]['type'])->toBe('Feature')
            ->and($result['features'][0]['geometry']['type'])->toBe('Point');
    });

    it('exports collection to GeoJSON', function () {
        $data = collect([
            ['name' => 'Point 1', 'location' => ['lat' => -23.55, 'lng' => -46.63]],
            ['name' => 'Point 2', 'location' => ['lat' => -23.56, 'lng' => -46.64]],
        ]);

        $exporter = new GeoJsonExporter;
        $result = $exporter
            ->fromCollection($data)
            ->geometryColumn('location')
            ->toArray();

        expect($result['features'])->toHaveCount(2);
    });

    it('exports as JSON string', function () {
        $data = collect([
            ['name' => 'Test', 'location' => ['lat' => -23.55, 'lng' => -46.63]],
        ]);

        $exporter = new GeoJsonExporter;
        $json = $exporter
            ->fromCollection($data)
            ->geometryColumn('location')
            ->export();

        expect($json)->toBeString()
            ->and(json_decode($json, true))->toHaveKey('type');
    });

    it('adds CRS to output', function () {
        $data = collect([
            ['location' => ['lat' => -23.55, 'lng' => -46.63]],
        ]);

        $exporter = new GeoJsonExporter;
        $result = $exporter
            ->fromCollection($data)
            ->geometryColumn('location')
            ->wgs84()
            ->toArray();

        expect($result)->toHaveKey('crs');
    });
});

describe('KmlImporter', function () {
    it('parses KML with placemarks', function () {
        $kml = '<?xml version="1.0" encoding="UTF-8"?>
            <kml xmlns="http://www.opengis.net/kml/2.2">
              <Document>
                <Placemark>
                  <name>Test Point</name>
                  <description>A test point</description>
                  <Point>
                    <coordinates>-46.6333,-23.5505,0</coordinates>
                  </Point>
                </Placemark>
              </Document>
            </kml>';

        $importer = new KmlImporter;
        $features = $importer->fromString($kml);

        expect($features)->toHaveCount(1)
            ->and($features[0]['name'])->toBe('Test Point')
            ->and($features[0]['geometry_type'])->toBe('Point')
            ->and($features[0]['longitude'])->toBe(-46.6333)
            ->and($features[0]['latitude'])->toBe(-23.5505);
    });

    it('parses LineString geometry', function () {
        $kml = '<?xml version="1.0" encoding="UTF-8"?>
            <kml xmlns="http://www.opengis.net/kml/2.2">
              <Placemark>
                <name>Test Line</name>
                <LineString>
                  <coordinates>-46.63,-23.55,0 -46.64,-23.56,0 -46.65,-23.57,0</coordinates>
                </LineString>
              </Placemark>
            </kml>';

        $importer = new KmlImporter;
        $features = $importer->fromString($kml);

        expect($features[0]['geometry_type'])->toBe('LineString')
            ->and($features[0]['geometry']['coordinates'])->toHaveCount(3);
    });

    it('parses Polygon geometry', function () {
        $kml = '<?xml version="1.0" encoding="UTF-8"?>
            <kml xmlns="http://www.opengis.net/kml/2.2">
              <Placemark>
                <name>Test Polygon</name>
                <Polygon>
                  <outerBoundaryIs>
                    <LinearRing>
                      <coordinates>-46.63,-23.55,0 -46.64,-23.55,0 -46.64,-23.56,0 -46.63,-23.56,0 -46.63,-23.55,0</coordinates>
                    </LinearRing>
                  </outerBoundaryIs>
                </Polygon>
              </Placemark>
            </kml>';

        $importer = new KmlImporter;
        $features = $importer->fromString($kml);

        expect($features[0]['geometry_type'])->toBe('Polygon')
            ->and($features[0]['geometry']['coordinates'][0])->toHaveCount(5);
    });

    it('converts to GeoJSON', function () {
        $kml = '<?xml version="1.0" encoding="UTF-8"?>
            <kml xmlns="http://www.opengis.net/kml/2.2">
              <Placemark>
                <name>Test</name>
                <Point><coordinates>-46.63,-23.55</coordinates></Point>
              </Placemark>
            </kml>';

        $importer = new KmlImporter;
        $features = $importer->fromString($kml);
        $geoJson = $importer->toGeoJson($features);

        expect($geoJson['type'])->toBe('FeatureCollection')
            ->and($geoJson['features'])->toHaveCount(1);
    });
});

describe('KmlExporter', function () {
    it('exports to KML format', function () {
        $data = collect([
            ['name' => 'Point 1', 'description' => 'Desc 1', 'location' => ['lat' => -23.55, 'lng' => -46.63]],
        ]);

        $exporter = new KmlExporter;
        $kml = $exporter
            ->fromCollection($data)
            ->name('Test Export')
            ->geometryColumn('location')
            ->nameColumn('name')
            ->descriptionColumn('description')
            ->export();

        expect($kml)->toContain('<?xml')
            ->and($kml)->toContain('<kml')
            ->and($kml)->toContain('<Placemark>')
            ->and($kml)->toContain('Point 1')
            ->and($kml)->toContain('-46.63,-23.55');
    });

    it('includes styles', function () {
        $data = collect([
            ['name' => 'Test', 'location' => ['lat' => -23.55, 'lng' => -46.63]],
        ]);

        $exporter = new KmlExporter;
        $kml = $exporter
            ->fromCollection($data)
            ->geometryColumn('location')
            ->addPointStyle('myStyle', 'http://example.com/icon.png')
            ->defaultStyle('myStyle')
            ->export();

        expect($kml)->toContain('<Style id="myStyle">')
            ->and($kml)->toContain('#myStyle');
    });
});

describe('GpxImporter', function () {
    it('parses waypoints', function () {
        $gpx = '<?xml version="1.0" encoding="UTF-8"?>
            <gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1">
              <wpt lat="-23.5505" lon="-46.6333">
                <ele>760</ele>
                <name>Test Waypoint</name>
                <desc>A test waypoint</desc>
              </wpt>
            </gpx>';

        $importer = new GpxImporter;
        $data = $importer->fromString($gpx);

        expect($data['waypoints'])->toHaveCount(1)
            ->and($data['waypoints'][0]['name'])->toBe('Test Waypoint')
            ->and($data['waypoints'][0]['latitude'])->toBe(-23.5505)
            ->and($data['waypoints'][0]['longitude'])->toBe(-46.6333)
            ->and($data['waypoints'][0]['elevation'])->toBe(760.0);
    });

    it('parses tracks with statistics', function () {
        $gpx = '<?xml version="1.0" encoding="UTF-8"?>
            <gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1">
              <trk>
                <name>Test Track</name>
                <trkseg>
                  <trkpt lat="-23.55" lon="-46.63"><ele>760</ele></trkpt>
                  <trkpt lat="-23.56" lon="-46.64"><ele>780</ele></trkpt>
                  <trkpt lat="-23.57" lon="-46.65"><ele>770</ele></trkpt>
                </trkseg>
              </trk>
            </gpx>';

        $importer = new GpxImporter;
        $data = $importer->fromString($gpx);

        expect($data['tracks'])->toHaveCount(1)
            ->and($data['tracks'][0]['name'])->toBe('Test Track')
            ->and($data['tracks'][0]['geometry']['type'])->toBe('LineString')
            ->and($data['tracks'][0]['statistics']['pointCount'])->toBe(3)
            ->and($data['tracks'][0]['statistics']['distance'])->toBeGreaterThan(0);
    });

    it('parses routes', function () {
        $gpx = '<?xml version="1.0" encoding="UTF-8"?>
            <gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1">
              <rte>
                <name>Test Route</name>
                <rtept lat="-23.55" lon="-46.63"><name>Point 1</name></rtept>
                <rtept lat="-23.56" lon="-46.64"><name>Point 2</name></rtept>
              </rte>
            </gpx>';

        $importer = new GpxImporter;
        $data = $importer->fromString($gpx);

        expect($data['routes'])->toHaveCount(1)
            ->and($data['routes'][0]['name'])->toBe('Test Route')
            ->and($data['routes'][0]['points'])->toHaveCount(2);
    });

    it('converts to GeoJSON', function () {
        $gpx = '<?xml version="1.0" encoding="UTF-8"?>
            <gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1">
              <wpt lat="-23.55" lon="-46.63"><name>WP</name></wpt>
              <trk><name>Track</name><trkseg>
                <trkpt lat="-23.55" lon="-46.63"></trkpt>
                <trkpt lat="-23.56" lon="-46.64"></trkpt>
              </trkseg></trk>
            </gpx>';

        $importer = new GpxImporter;
        $data = $importer->fromString($gpx);
        $geoJson = $importer->toGeoJson($data);

        expect($geoJson['type'])->toBe('FeatureCollection')
            ->and($geoJson['features'])->toHaveCount(2);
    });
});

describe('CsvGeoImporter', function () {
    it('parses CSV with lat/lng columns', function () {
        $csv = "name,latitude,longitude,description\n";
        $csv .= "Point 1,-23.5505,-46.6333,First point\n";
        $csv .= "Point 2,-23.5600,-46.6400,Second point\n";

        $importer = new CsvGeoImporter;
        $features = $importer->fromString($csv);

        expect($features)->toHaveCount(2)
            ->and($features[0]['latitude'])->toBe(-23.5505)
            ->and($features[0]['longitude'])->toBe(-46.6333)
            ->and($features[0]['properties']['name'])->toBe('Point 1');
    });

    it('handles European CSV format', function () {
        $csv = "name;lat;lng\n";
        $csv .= "Test;-23,5505;-46,6333\n";

        $importer = (new CsvGeoImporter)
            ->europeanFormat()
            ->latitudeColumn('lat')
            ->longitudeColumn('lng');

        $features = $importer->fromString($csv);

        expect($features)->toHaveCount(1)
            ->and($features[0]['latitude'])->toBe(-23.5505);
    });

    it('parses DMS coordinates', function () {
        $csv = "name,latitude,longitude\n";
        $csv .= "Test,23d33'01.9\"S,46d37'59.9\"W\n";

        $importer = new CsvGeoImporter;
        $features = $importer->fromString($csv);

        expect($features)->toHaveCount(1)
            ->and($features[0]['latitude'])->toBeBetween(-23.56, -23.54)
            ->and($features[0]['longitude'])->toBeBetween(-46.65, -46.62);
    });

    it('skips invalid rows by default', function () {
        $csv = "name,latitude,longitude\n";
        $csv .= "Valid,-23.55,-46.63\n";
        $csv .= "Invalid,not-a-number,-46.63\n";
        $csv .= "Also Valid,-23.56,-46.64\n";

        $importer = new CsvGeoImporter;
        $features = $importer->fromString($csv);

        expect($features)->toHaveCount(2);
    });

    it('validates CSV structure', function () {
        $importer = (new CsvGeoImporter)
            ->latitudeColumn('lat')
            ->longitudeColumn('lng');

        $validCsv = "lat,lng,name\n-23.55,-46.63,Test\n";
        $invalidCsv = "name,other\nTest,value\n";

        expect($importer->validate($validCsv))->toBeEmpty()
            ->and($importer->validate($invalidCsv))->not->toBeEmpty();
    });

    it('converts to GeoJSON', function () {
        $csv = "name,latitude,longitude\nTest,-23.55,-46.63\n";

        $importer = new CsvGeoImporter;
        $features = $importer->fromString($csv);
        $geoJson = $importer->toGeoJson($features);

        expect($geoJson['type'])->toBe('FeatureCollection')
            ->and($geoJson['features'])->toHaveCount(1);
    });
});
