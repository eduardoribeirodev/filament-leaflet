<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support;

use RuntimeException;

class LocationIndex
{
    protected static ?array $index = null;
    protected static array $municipalParents = [];
    protected static ?array $geometries = null;
    protected static ?array $countries = null;
    protected static ?array $regions = null;
    protected static array $cityNames = [];

    public static function countries(): array
    {
        return static::$countries ??= json_decode(file_get_contents(dirname(__DIR__, 2) . '/dist/geojson/catalog.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, array{country: string, level: int, parent: ?string, state: ?string, bounds: ?array}> */
    public static function regions(): array
    {
        if (static::$regions !== null) {
            return static::$regions;
        }

        $regions = [];
        foreach (static::countries() as $country => $entry) {
            $regions[$entry['id']] = ['country' => $country, 'level' => 0, 'parent' => null, 'state' => null,
                'bounds' => static::geometry("{$country}/adm0.geojson")['bounds'] ?? null];
            foreach (static::states($country) as $code => $state) {
                if ($state['featureId'] === null) {
                    continue;
                }
                $regions[$state['featureId']] = ['country' => $country, 'level' => 1, 'parent' => $entry['id'],
                    'state' => (string) $code, 'bounds' => $state['focusBounds'] ?? $state['bounds']];
                foreach ($state['cities'] as $city) {
                    $regions[$city[0]] = ['country' => $country, 'level' => 2, 'parent' => $state['featureId'],
                        'state' => (string) $code, 'bounds' => $city[2] ?? null];
                }
            }
        }

        return static::$regions = $regions;
    }

    public static function cityId(string $country, string $state, string $name): string
    {
        $key = $country . '/' . $state;
        if (!isset(static::$cityNames[$key])) {
            $names = [];
            foreach (static::states($country)[$state]['cities'] ?? [] as [$id, $label]) {
                $names[$label][] = $id;
            }
            static::$cityNames[$key] = $names;
        }
        $ids = static::$cityNames[$key][$name] ?? [];
        if (count($ids) !== 1) {
            throw new \InvalidArgumentException("Unknown or ambiguous city in {$key}: {$name}. Use the exact catalog name or a shapeID.");
        }

        return $ids[0];
    }

    public static function contains(string $ancestor, string $id): bool
    {
        $regions = static::regions();
        while (isset($regions[$id])) {
            if ($ancestor === $id) {
                return true;
            }
            $id = $regions[$id]['parent'];
            if ($id === null) {
                break;
            }
        }

        return false;
    }

    /** Whether a polygon belongs to any of the supplied territories. */
    public static function containsAny(array $ancestors, string $id): bool
    {
        foreach ($ancestors as $ancestor) {
            if (static::contains($ancestor, $id)) {
                return true;
            }
        }

        return false;
    }

    /** Intersect selections with optional limits, retaining their broadest nonoverlapping regions. */
    public static function restrictRegions(array $ids, ?array $limits = null): array
    {
        $selected = array_fill_keys($ids, true);
        if ($limits !== null) {
            $selected = [];
            foreach ($ids as $id) {
                foreach ($limits as $limit) {
                    if (static::contains($limit, $id)) {
                        $selected[$id] = true;
                    } elseif (static::contains($id, $limit)) {
                        $selected[$limit] = true;
                    }
                }
            }
        }
        $regions = static::regions();
        foreach (array_keys($selected) as $id) {
            $parent = $regions[$id]['parent'];
            while ($parent !== null) {
                if (isset($selected[$parent])) {
                    unset($selected[$id]);
                    break;
                }
                $parent = $regions[$parent]['parent'];
            }
        }

        return array_keys($selected);
    }

    public static function mergeBounds(array $bounds): ?array
    {
        $result = null;
        foreach (array_filter($bounds) as [$west, $south, $east, $north]) {
            if ($east < $west) {
                $east += 360;
            }
            if ($result === null) {
                $result = [$west, $south, $east, $north];
                continue;
            }
            $shifts = [-360, 0, 360];
            usort($shifts, fn (int $a, int $b): int => (max($result[2], $east + $a) - min($result[0], $west + $a))
                <=> (max($result[2], $east + $b) - min($result[0], $west + $b)));
            $result = [min($result[0], $west + $shifts[0]), min($result[1], $south), max($result[2], $east + $shifts[0]), max($result[3], $north)];
        }

        return $result;
    }

    public static function geometry(string $path): ?array
    {
        if (static::$geometries === null) {
            $file = dirname(__DIR__, 2) . '/dist/geojson/geometry.json';
            static::$geometries = is_file($file) ? json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR) : [];
        }
        return static::$geometries[$path] ?? null;
    }

    public static function states(string $country): array
    {
        if (static::$index === null) {
            $path = dirname(__DIR__, 2) . '/dist/geojson/locations.json';
            if (!is_file($path)) {
                throw new RuntimeException('The administrative location index is missing. Run npm run build:locations when building the package.');
            }
            static::$index = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        }
        return static::$index[$country] ?? [];
    }

    /** @return array<string, string> ADM2 shapeID to parent state code */
    public static function municipalParents(string $country): array
    {
        if (!isset(static::$municipalParents[$country])) {
            $parents = [];
            foreach (static::states($country) as $state => $entry) {
                foreach ($entry['cities'] as [$id]) {
                    $parents[$id] = (string) $state;
                }
            }
            static::$municipalParents[$country] = $parents;
        }

        return static::$municipalParents[$country];
    }
}
