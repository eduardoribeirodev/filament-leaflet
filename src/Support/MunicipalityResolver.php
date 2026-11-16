<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support;

use EduardoRibeiroDev\FilamentLeaflet\Enums\AdministrativeLevel;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\Coordinate;
use InvalidArgumentException;
use RuntimeException;

class MunicipalityResolver
{
    private const DIRECTORY = __DIR__ . '/../../dist/geojson/';

    private static ?array $index = null;

    private array $resolved = [];

    /** Resolve a point against the polygons at the requested level. The optional country uses ISO3. */
    public function resolve(Coordinate $point, AdministrativeLevel $level = AdministrativeLevel::Municipality, ?string $country = null): ?string
    {
        if ($level === AdministrativeLevel::Auto) {
            throw new InvalidArgumentException('Coordinate lookup requires a concrete administrative level.');
        }

        if (!is_finite($point->lat) || !is_finite($point->lng) || abs($point->lat) > 90 || abs($point->lng) > 180) {
            return null;
        }

        $key = serialize([$level->value, $country, $point->lat, $point->lng]);
        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }
        if (count($this->resolved) >= 4096) {
            $this->resolved = [];
        }

        return $this->resolved[$key] = $this->find($point, $level, $country);
    }

    private function find(Coordinate $point, AdministrativeLevel $level, ?string $country): ?string
    {
        self::$index ??= json_decode(file_get_contents(self::DIRECTORY . 'administrative-geometry.json'), true, 512, JSON_THROW_ON_ERROR);
        $suffix = '/adm' . $level->value . '.geojson';
        $match = null;

        foreach (self::$index as $path => $features) {
            if (!str_ends_with($path, $suffix)) continue;
            $iso = explode('/', $path, 2)[0];
            if ($country !== null && $iso !== $country) continue;
            $bounds = LocationIndex::geometry($path)['bounds'] ?? null;
            if ($bounds !== null && !$this->inBounds($point, $bounds)) continue;

            $parents = $level === AdministrativeLevel::Municipality ? LocationIndex::municipalParents($iso) : null;
            foreach ($features as [$id, $box, $offset, $length]) {
                if (($parents !== null && !isset($parents[$id])) || !$this->inBounds($point, $box)) continue;

                // Read only the indexed feature, even when its source contains a whole country.
                $json = file_get_contents(self::DIRECTORY . $path, false, null, $offset, $length);
                $feature = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                if (($feature['properties']['shapeID'] ?? null) !== $id) {
                    throw new RuntimeException('Administrative geometry and index differ. Run npm run build:locations.');
                }
                if (!$this->covers($point, $feature['geometry'])) continue;
                if ($match !== null && $match !== $id) return null;
                $match = $id;
            }
        }

        return $match;
    }

    private function inBounds(Coordinate $point, array $bounds): bool
    {
        [$west, $south, $east, $north] = $bounds;
        if ($point->lat < $south || $point->lat > $north) return false;

        $lng = $point->lng;
        if (abs($lng) === 180.0 && ($west === -180 || $east === 180)) return true;

        return $west <= $east ? $lng >= $west && $lng <= $east : $lng >= $west || $lng <= $east;
    }

    private function covers(Coordinate $point, array $geometry): bool
    {
        $polygons = match ($geometry['type']) {
            'Polygon' => [$geometry['coordinates']],
            'MultiPolygon' => $geometry['coordinates'],
            default => [],
        };

        foreach ($polygons as $rings) {
            if (!$rings || $this->ringPosition($point, $rings[0]) < 0) continue;
            foreach (array_slice($rings, 1) as $hole) {
                if ($this->ringPosition($point, $hole) > 0) continue 2;
            }

            return true;
        }

        return false;
    }

    /** Return -1 outside, 0 on the edge, or 1 inside a ring. */
    private function ringPosition(Coordinate $point, array $ring): int
    {
        $origin = $ring[0][0];
        $x = $this->relativeLongitude($point->lng, $origin);
        $y = $point->lat;
        $inside = false;
        $count = count($ring);
        $ax = $this->relativeLongitude($ring[$count - 1][0], $origin);
        $ay = $ring[$count - 1][1];

        foreach ($ring as [$longitude, $by]) {
            $bx = $this->relativeLongitude($longitude, $origin);
            if ($x >= min($ax, $bx) && $x <= max($ax, $bx) && $y >= min($ay, $by) && $y <= max($ay, $by)
                && abs(($x - $ax) * ($by - $ay) - ($y - $ay) * ($bx - $ax)) <= 1e-12) {
                return 0;
            }
            if (($ay > $y) !== ($by > $y) && $x < $ax + ($bx - $ax) * ($y - $ay) / ($by - $ay)) {
                $inside = !$inside;
            }
            [$ax, $ay] = [$bx, $by];
        }

        return $inside ? 1 : -1;
    }

    private function relativeLongitude(float $longitude, float $origin): float
    {
        $delta = $longitude - $origin;

        return $delta > 180 ? $delta - 360 : ($delta < -180 ? $delta + 360 : $delta);
    }
}
