<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis\Support\Geo;

use Illuminate\Support\Collection;

/**
 * GpxImporter - Import GPX (GPS Exchange Format) files.
 *
 * Supports:
 * - Waypoints (wpt)
 * - Tracks (trk) with segments
 * - Routes (rte)
 *
 * @example
 * $importer = new GpxImporter();
 * $data = $importer->fromFile('/path/to/file.gpx');
 * $waypoints = $data['waypoints'];
 * $tracks = $data['tracks'];
 * $routes = $data['routes'];
 */
class GpxImporter
{
    /**
     * Whether to include elevation data.
     */
    protected bool $includeElevation = true;

    /**
     * Whether to include time data.
     */
    protected bool $includeTime = true;

    /**
     * Parse GPX from file path.
     *
     * @return array{waypoints: Collection, tracks: Collection, routes: Collection, metadata: array}
     */
    public function fromFile(string $path): array
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("File not found: {$path}");
        }

        $content = file_get_contents($path);

        return $this->fromString($content);
    }

    /**
     * Parse GPX from string.
     *
     * @return array{waypoints: Collection, tracks: Collection, routes: Collection, metadata: array}
     */
    public function fromString(string $gpx): array
    {
        $previousErrors = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($gpx);

            if ($xml === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                throw new \InvalidArgumentException('Invalid GPX: '.($errors[0]->message ?? 'Unknown error'));
            }

            // Register namespace
            $xml->registerXPathNamespace('gpx', 'http://www.topografix.com/GPX/1/1');

            return [
                'waypoints' => $this->parseWaypoints($xml),
                'tracks' => $this->parseTracks($xml),
                'routes' => $this->parseRoutes($xml),
                'metadata' => $this->parseMetadata($xml),
            ];
        } finally {
            libxml_use_internal_errors($previousErrors);
        }
    }

    /**
     * Enable/disable elevation data.
     */
    public function includeElevation(bool $include = true): static
    {
        $this->includeElevation = $include;

        return $this;
    }

    /**
     * Enable/disable time data.
     */
    public function includeTime(bool $include = true): static
    {
        $this->includeTime = $include;

        return $this;
    }

    /**
     * Parse metadata.
     */
    protected function parseMetadata(\SimpleXMLElement $xml): array
    {
        $metadata = [];

        // Try with namespace
        $meta = $xml->xpath('//gpx:metadata') ?: $xml->xpath('//metadata');

        if (! empty($meta)) {
            $meta = $meta[0];
            $metadata['name'] = (string) ($meta->name ?? '');
            $metadata['description'] = (string) ($meta->desc ?? '');
            $metadata['author'] = (string) ($meta->author->name ?? '');
            $metadata['time'] = (string) ($meta->time ?? '');

            if ($meta->bounds) {
                $metadata['bounds'] = [
                    'minLat' => (float) $meta->bounds['minlat'],
                    'maxLat' => (float) $meta->bounds['maxlat'],
                    'minLng' => (float) $meta->bounds['minlon'],
                    'maxLng' => (float) $meta->bounds['maxlon'],
                ];
            }
        }

        return $metadata;
    }

    /**
     * Parse waypoints.
     */
    protected function parseWaypoints(\SimpleXMLElement $xml): Collection
    {
        $waypoints = collect();

        $wpts = $xml->xpath('//gpx:wpt') ?: $xml->xpath('//wpt') ?: [];

        foreach ($wpts as $wpt) {
            $waypoints->push($this->parseWaypoint($wpt));
        }

        return $waypoints;
    }

    /**
     * Parse a single waypoint.
     */
    protected function parseWaypoint(\SimpleXMLElement $wpt): array
    {
        $waypoint = [
            'latitude' => (float) $wpt['lat'],
            'longitude' => (float) $wpt['lon'],
            'name' => (string) ($wpt->name ?? ''),
            'description' => (string) ($wpt->desc ?? ''),
            'comment' => (string) ($wpt->cmt ?? ''),
            'symbol' => (string) ($wpt->sym ?? ''),
            'type' => (string) ($wpt->type ?? ''),
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [(float) $wpt['lon'], (float) $wpt['lat']],
            ],
        ];

        if ($this->includeElevation && $wpt->ele) {
            $waypoint['elevation'] = (float) $wpt->ele;
            $waypoint['geometry']['coordinates'][] = (float) $wpt->ele;
        }

        if ($this->includeTime && $wpt->time) {
            $waypoint['time'] = (string) $wpt->time;
        }

        return $waypoint;
    }

    /**
     * Parse tracks.
     */
    protected function parseTracks(\SimpleXMLElement $xml): Collection
    {
        $tracks = collect();

        $trks = $xml->xpath('//gpx:trk') ?: $xml->xpath('//trk') ?: [];

        foreach ($trks as $trk) {
            $tracks->push($this->parseTrack($trk));
        }

        return $tracks;
    }

    /**
     * Parse a single track.
     */
    protected function parseTrack(\SimpleXMLElement $trk): array
    {
        $track = [
            'name' => (string) ($trk->name ?? ''),
            'description' => (string) ($trk->desc ?? ''),
            'comment' => (string) ($trk->cmt ?? ''),
            'type' => (string) ($trk->type ?? ''),
            'segments' => [],
            'geometry' => null,
            'statistics' => null,
        ];

        $allCoordinates = [];

        // Parse track segments
        $trksegs = $trk->trkseg ?? [];

        foreach ($trksegs as $trkseg) {
            $segment = $this->parseTrackSegment($trkseg);
            $track['segments'][] = $segment;
            $allCoordinates = array_merge($allCoordinates, $segment['coordinates']);
        }

        // Build geometry
        if (! empty($allCoordinates)) {
            if (count($track['segments']) === 1) {
                $track['geometry'] = [
                    'type' => 'LineString',
                    'coordinates' => $allCoordinates,
                ];
            } else {
                $track['geometry'] = [
                    'type' => 'MultiLineString',
                    'coordinates' => array_map(fn ($s) => $s['coordinates'], $track['segments']),
                ];
            }

            // Calculate statistics
            $track['statistics'] = $this->calculateTrackStatistics($track['segments']);
        }

        return $track;
    }

    /**
     * Parse a track segment.
     */
    protected function parseTrackSegment(\SimpleXMLElement $trkseg): array
    {
        $segment = [
            'coordinates' => [],
            'points' => [],
        ];

        foreach ($trkseg->trkpt as $trkpt) {
            $point = [
                'latitude' => (float) $trkpt['lat'],
                'longitude' => (float) $trkpt['lon'],
            ];

            $coordinate = [(float) $trkpt['lon'], (float) $trkpt['lat']];

            if ($this->includeElevation && $trkpt->ele) {
                $point['elevation'] = (float) $trkpt->ele;
                $coordinate[] = (float) $trkpt->ele;
            }

            if ($this->includeTime && $trkpt->time) {
                $point['time'] = (string) $trkpt->time;
            }

            $segment['coordinates'][] = $coordinate;
            $segment['points'][] = $point;
        }

        return $segment;
    }

    /**
     * Calculate track statistics.
     */
    protected function calculateTrackStatistics(array $segments): array
    {
        $allPoints = [];
        foreach ($segments as $segment) {
            $allPoints = array_merge($allPoints, $segment['points']);
        }

        if (empty($allPoints)) {
            return [];
        }

        $stats = [
            'pointCount' => count($allPoints),
            'segmentCount' => count($segments),
        ];

        // Calculate distance
        $totalDistance = 0;
        for ($i = 1; $i < count($allPoints); $i++) {
            $totalDistance += $this->haversineDistance(
                $allPoints[$i - 1]['latitude'],
                $allPoints[$i - 1]['longitude'],
                $allPoints[$i]['latitude'],
                $allPoints[$i]['longitude']
            );
        }
        $stats['distance'] = round($totalDistance, 2);
        $stats['distanceUnit'] = 'km';

        // Calculate elevation stats if available
        $elevations = array_filter(array_column($allPoints, 'elevation'), fn ($e) => $e !== null);

        if (! empty($elevations)) {
            $stats['elevationMin'] = min($elevations);
            $stats['elevationMax'] = max($elevations);
            $stats['elevationGain'] = $this->calculateElevationGain($allPoints);
            $stats['elevationLoss'] = $this->calculateElevationLoss($allPoints);
        }

        // Calculate time stats if available
        $times = array_filter(array_column($allPoints, 'time'));

        if (count($times) >= 2) {
            $startTime = new \DateTime(reset($times));
            $endTime = new \DateTime(end($times));
            $duration = $endTime->getTimestamp() - $startTime->getTimestamp();
            $stats['duration'] = $duration;
            $stats['startTime'] = reset($times);
            $stats['endTime'] = end($times);

            if ($duration > 0 && $totalDistance > 0) {
                $stats['avgSpeed'] = round($totalDistance / ($duration / 3600), 2);
                $stats['avgSpeedUnit'] = 'km/h';
            }
        }

        return $stats;
    }

    /**
     * Calculate Haversine distance in km.
     */
    protected function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;

        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);
        $dlat = deg2rad($lat2 - $lat1);
        $dlng = deg2rad($lng2 - $lng1);

        $a = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlng / 2) ** 2;
        $c = 2 * asin(sqrt($a));

        return $earthRadius * $c;
    }

    /**
     * Calculate elevation gain.
     */
    protected function calculateElevationGain(array $points): float
    {
        $gain = 0;

        for ($i = 1; $i < count($points); $i++) {
            if (isset($points[$i]['elevation'], $points[$i - 1]['elevation'])) {
                $diff = $points[$i]['elevation'] - $points[$i - 1]['elevation'];

                if ($diff > 0) {
                    $gain += $diff;
                }
            }
        }

        return round($gain, 1);
    }

    /**
     * Calculate elevation loss.
     */
    protected function calculateElevationLoss(array $points): float
    {
        $loss = 0;

        for ($i = 1; $i < count($points); $i++) {
            if (isset($points[$i]['elevation'], $points[$i - 1]['elevation'])) {
                $diff = $points[$i - 1]['elevation'] - $points[$i]['elevation'];

                if ($diff > 0) {
                    $loss += $diff;
                }
            }
        }

        return round($loss, 1);
    }

    /**
     * Parse routes.
     */
    protected function parseRoutes(\SimpleXMLElement $xml): Collection
    {
        $routes = collect();

        $rtes = $xml->xpath('//gpx:rte') ?: $xml->xpath('//rte') ?: [];

        foreach ($rtes as $rte) {
            $routes->push($this->parseRoute($rte));
        }

        return $routes;
    }

    /**
     * Parse a single route.
     */
    protected function parseRoute(\SimpleXMLElement $rte): array
    {
        $route = [
            'name' => (string) ($rte->name ?? ''),
            'description' => (string) ($rte->desc ?? ''),
            'comment' => (string) ($rte->cmt ?? ''),
            'points' => [],
            'geometry' => null,
        ];

        $coordinates = [];

        foreach ($rte->rtept as $rtept) {
            $point = [
                'latitude' => (float) $rtept['lat'],
                'longitude' => (float) $rtept['lon'],
                'name' => (string) ($rtept->name ?? ''),
            ];

            $coordinate = [(float) $rtept['lon'], (float) $rtept['lat']];

            if ($this->includeElevation && $rtept->ele) {
                $point['elevation'] = (float) $rtept->ele;
                $coordinate[] = (float) $rtept->ele;
            }

            $route['points'][] = $point;
            $coordinates[] = $coordinate;
        }

        if (! empty($coordinates)) {
            $route['geometry'] = [
                'type' => 'LineString',
                'coordinates' => $coordinates,
            ];
        }

        return $route;
    }

    /**
     * Convert to GeoJSON format.
     */
    public function toGeoJson(array $data): array
    {
        $features = [];

        // Convert waypoints
        foreach ($data['waypoints'] as $waypoint) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => $waypoint['geometry'],
                'properties' => [
                    'featureType' => 'waypoint',
                    'name' => $waypoint['name'],
                    'description' => $waypoint['description'],
                    'symbol' => $waypoint['symbol'],
                    'elevation' => $waypoint['elevation'] ?? null,
                    'time' => $waypoint['time'] ?? null,
                ],
            ];
        }

        // Convert tracks
        foreach ($data['tracks'] as $track) {
            if ($track['geometry']) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => $track['geometry'],
                    'properties' => [
                        'featureType' => 'track',
                        'name' => $track['name'],
                        'description' => $track['description'],
                        'statistics' => $track['statistics'],
                    ],
                ];
            }
        }

        // Convert routes
        foreach ($data['routes'] as $route) {
            if ($route['geometry']) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => $route['geometry'],
                    'properties' => [
                        'featureType' => 'route',
                        'name' => $route['name'],
                        'description' => $route['description'],
                    ],
                ];
            }
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }
}
