<?php

declare(strict_types=1);

namespace EduardoRibeiroDev\FilamentLeafletPostgis\Support\Spatial;

/**
 * GeometrySimplifier - Simplify geometries for better performance.
 *
 * Implements:
 * - Douglas-Peucker algorithm for polylines
 * - Visvalingam-Whyatt algorithm for area-preserving simplification
 *
 * @example
 * $simplifier = new GeometrySimplifier();
 * $simplified = $simplifier->douglasPeucker($coordinates, 0.001);
 * $simplified = $simplifier->visvalingam($coordinates, 10);
 */
class GeometrySimplifier
{
    /**
     * Simplify a polyline using Douglas-Peucker algorithm.
     *
     * @param  array<int, array{0: float, 1: float}>  $coordinates  [lng, lat] pairs
     * @param  float  $tolerance  Tolerance in degrees
     * @return array<int, array{0: float, 1: float}> Simplified coordinates
     */
    public function douglasPeucker(array $coordinates, float $tolerance): array
    {
        $count = count($coordinates);

        if ($count < 3) {
            return $coordinates;
        }

        // Find point with maximum distance
        $maxDistance = 0;
        $maxIndex = 0;

        $start = $coordinates[0];
        $end = $coordinates[$count - 1];

        for ($i = 1; $i < $count - 1; $i++) {
            $distance = $this->perpendicularDistance($coordinates[$i], $start, $end);

            if ($distance > $maxDistance) {
                $maxDistance = $distance;
                $maxIndex = $i;
            }
        }

        // If max distance is greater than tolerance, recursively simplify
        if ($maxDistance > $tolerance) {
            $left = array_slice($coordinates, 0, $maxIndex + 1);
            $right = array_slice($coordinates, $maxIndex);

            $leftSimplified = $this->douglasPeucker($left, $tolerance);
            $rightSimplified = $this->douglasPeucker($right, $tolerance);

            // Remove duplicate point
            array_pop($leftSimplified);

            return array_merge($leftSimplified, $rightSimplified);
        }

        // Return only start and end points
        return [$start, $end];
    }

    /**
     * Simplify a polyline using Visvalingam-Whyatt algorithm.
     *
     * @param  array<int, array{0: float, 1: float}>  $coordinates  [lng, lat] pairs
     * @param  int  $targetCount  Target number of points
     * @return array<int, array{0: float, 1: float}> Simplified coordinates
     */
    public function visvalingam(array $coordinates, int $targetCount): array
    {
        $count = count($coordinates);

        if ($count <= $targetCount || $count < 3) {
            return $coordinates;
        }

        // Create indexed array of points with areas
        $points = [];

        for ($i = 0; $i < $count; $i++) {
            $points[$i] = [
                'coord' => $coordinates[$i],
                'area' => $i > 0 && $i < $count - 1
                    ? $this->triangleArea($coordinates[$i - 1], $coordinates[$i], $coordinates[$i + 1])
                    : PHP_FLOAT_MAX,
                'prev' => $i - 1,
                'next' => $i + 1,
            ];
        }

        // Remove points until we reach target count
        while (count(array_filter($points, fn ($p) => $p !== null)) > $targetCount) {
            // Find point with minimum area
            $minArea = PHP_FLOAT_MAX;
            $minIndex = -1;

            foreach ($points as $i => $point) {
                if ($point !== null && $point['area'] < $minArea) {
                    $minArea = $point['area'];
                    $minIndex = $i;
                }
            }

            if ($minIndex === -1) {
                break;
            }

            // Remove the point
            $prev = $points[$minIndex]['prev'];
            $next = $points[$minIndex]['next'];

            // Update neighbors
            if ($prev >= 0 && $points[$prev] !== null) {
                $points[$prev]['next'] = $next;

                // Recalculate area
                $prevPrev = $points[$prev]['prev'];

                if ($prevPrev >= 0 && $points[$prevPrev] !== null && $next < count($coordinates) && $points[$next] !== null) {
                    $points[$prev]['area'] = $this->triangleArea(
                        $points[$prevPrev]['coord'],
                        $points[$prev]['coord'],
                        $points[$next]['coord']
                    );
                }
            }

            if ($next < count($points) && $points[$next] !== null) {
                $points[$next]['prev'] = $prev;

                // Recalculate area
                $nextNext = $points[$next]['next'];

                if ($nextNext < count($coordinates) && $points[$nextNext] !== null && $prev >= 0 && $points[$prev] !== null) {
                    $points[$next]['area'] = $this->triangleArea(
                        $points[$prev]['coord'],
                        $points[$next]['coord'],
                        $points[$nextNext]['coord']
                    );
                }
            }

            $points[$minIndex] = null;
        }

        // Collect remaining points
        return array_values(
            array_map(
                fn ($p) => $p['coord'],
                array_filter($points, fn ($p) => $p !== null)
            )
        );
    }

    /**
     * Simplify a polygon (applies to outer ring and holes).
     *
     * @param  array<int, array<int, array{0: float, 1: float}>>  $rings  Array of rings
     * @param  float  $tolerance  Tolerance for simplification
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    public function simplifyPolygon(array $rings, float $tolerance): array
    {
        return array_map(function ($ring) use ($tolerance) {
            $simplified = $this->douglasPeucker($ring, $tolerance);

            // Ensure ring is closed
            if ($simplified[0] !== $simplified[count($simplified) - 1]) {
                $simplified[] = $simplified[0];
            }

            // Ensure minimum 4 points for valid polygon
            if (count($simplified) < 4) {
                return $ring;
            }

            return $simplified;
        }, $rings);
    }

    /**
     * Simplify a GeoJSON geometry.
     */
    public function simplifyGeoJson(array $geometry, float $tolerance): array
    {
        $type = $geometry['type'] ?? null;

        return match ($type) {
            'LineString' => [
                'type' => 'LineString',
                'coordinates' => $this->douglasPeucker($geometry['coordinates'], $tolerance),
            ],
            'Polygon' => [
                'type' => 'Polygon',
                'coordinates' => $this->simplifyPolygon($geometry['coordinates'], $tolerance),
            ],
            'MultiLineString' => [
                'type' => 'MultiLineString',
                'coordinates' => array_map(
                    fn ($line) => $this->douglasPeucker($line, $tolerance),
                    $geometry['coordinates']
                ),
            ],
            'MultiPolygon' => [
                'type' => 'MultiPolygon',
                'coordinates' => array_map(
                    fn ($polygon) => $this->simplifyPolygon($polygon, $tolerance),
                    $geometry['coordinates']
                ),
            ],
            default => $geometry, // Return as-is for Point, etc.
        };
    }

    /**
     * Calculate tolerance based on zoom level.
     *
     * @param  int  $zoom  Map zoom level (0-20)
     * @return float Tolerance in degrees
     */
    public function toleranceForZoom(int $zoom): float
    {
        // At zoom 0, tolerance is high (very simplified)
        // At zoom 20, tolerance is low (full detail)
        $baseTolerance = 0.1; // ~10km at equator

        return $baseTolerance / (1 << $zoom);
    }

    /**
     * Calculate perpendicular distance from point to line.
     */
    protected function perpendicularDistance(array $point, array $lineStart, array $lineEnd): float
    {
        $dx = $lineEnd[0] - $lineStart[0];
        $dy = $lineEnd[1] - $lineStart[1];

        // If line is a point, return distance to that point
        if ($dx === 0.0 && $dy === 0.0) {
            $dx = $point[0] - $lineStart[0];
            $dy = $point[1] - $lineStart[1];

            return sqrt($dx * $dx + $dy * $dy);
        }

        $t = (($point[0] - $lineStart[0]) * $dx + ($point[1] - $lineStart[1]) * $dy) / ($dx * $dx + $dy * $dy);

        if ($t < 0) {
            $dx = $point[0] - $lineStart[0];
            $dy = $point[1] - $lineStart[1];
        } elseif ($t > 1) {
            $dx = $point[0] - $lineEnd[0];
            $dy = $point[1] - $lineEnd[1];
        } else {
            $nearX = $lineStart[0] + $t * $dx;
            $nearY = $lineStart[1] + $t * $dy;
            $dx = $point[0] - $nearX;
            $dy = $point[1] - $nearY;
        }

        return sqrt($dx * $dx + $dy * $dy);
    }

    /**
     * Calculate area of a triangle.
     */
    protected function triangleArea(array $p1, array $p2, array $p3): float
    {
        return abs(
            ($p1[0] * ($p2[1] - $p3[1]) +
                $p2[0] * ($p3[1] - $p1[1]) +
                $p3[0] * ($p1[1] - $p2[1])) / 2
        );
    }

    /**
     * Count points in a geometry.
     */
    public function countPoints(array $geometry): int
    {
        $type = $geometry['type'] ?? null;

        return match ($type) {
            'Point' => 1,
            'LineString' => count($geometry['coordinates']),
            'Polygon' => array_sum(array_map('count', $geometry['coordinates'])),
            'MultiPoint' => count($geometry['coordinates']),
            'MultiLineString' => array_sum(array_map('count', $geometry['coordinates'])),
            'MultiPolygon' => array_sum(array_map(
                fn ($polygon) => array_sum(array_map('count', $polygon)),
                $geometry['coordinates']
            )),
            default => 0,
        };
    }

    /**
     * Calculate compression ratio after simplification.
     */
    public function compressionRatio(array $original, array $simplified): float
    {
        $originalCount = $this->countPoints($original);
        $simplifiedCount = $this->countPoints($simplified);

        if ($originalCount === 0) {
            return 1.0;
        }

        return 1 - ($simplifiedCount / $originalCount);
    }
}
