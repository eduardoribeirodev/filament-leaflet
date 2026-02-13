<?php

declare(strict_types=1);

namespace App\Support\Spatial;

/**
 * MeasurementTools - Distance, area, and bearing calculations.
 *
 * Provides geodesic calculations using the Haversine formula and
 * Vincenty's formulae for high-precision measurements.
 *
 * @example
 * $tools = new MeasurementTools();
 * $distance = $tools->distance([-23.55, -46.63], [-22.90, -43.17]); // ~360km
 * $area = $tools->polygonArea($coordinates);
 * $bearing = $tools->bearing([-23.55, -46.63], [-22.90, -43.17]);
 */
class MeasurementTools
{
    /**
     * Earth's radius in kilometers.
     */
    protected const EARTH_RADIUS_KM = 6371.0;

    /**
     * Earth's radius in meters.
     */
    protected const EARTH_RADIUS_M = 6371000.0;

    /**
     * Earth's radius in miles.
     */
    protected const EARTH_RADIUS_MI = 3958.8;

    /**
     * Calculate distance between two points using Haversine formula.
     *
     * @param  array{0: float, 1: float}  $point1  [latitude, longitude]
     * @param  array{0: float, 1: float}  $point2  [latitude, longitude]
     * @param  string  $unit  'km', 'm', 'mi', 'nm' (nautical miles)
     * @return float Distance in specified unit
     */
    public function distance(array $point1, array $point2, string $unit = 'km'): float
    {
        $lat1 = deg2rad($point1[0]);
        $lon1 = deg2rad($point1[1]);
        $lat2 = deg2rad($point2[0]);
        $lon2 = deg2rad($point2[1]);

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;

        $a = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlon / 2) ** 2;
        $c = 2 * asin(sqrt($a));

        $radius = match ($unit) {
            'km' => self::EARTH_RADIUS_KM,
            'm' => self::EARTH_RADIUS_M,
            'mi' => self::EARTH_RADIUS_MI,
            'nm' => 3440.065, // Nautical miles
            default => self::EARTH_RADIUS_KM,
        };

        return $radius * $c;
    }

    /**
     * Calculate distance using Vincenty's formula (more accurate for long distances).
     *
     * @param  array{0: float, 1: float}  $point1  [latitude, longitude]
     * @param  array{0: float, 1: float}  $point2  [latitude, longitude]
     * @return float Distance in meters
     */
    public function vincentyDistance(array $point1, array $point2): float
    {
        $a = 6378137.0; // WGS-84 semi-major axis
        $f = 1 / 298.257223563; // WGS-84 flattening
        $b = (1 - $f) * $a;

        $lat1 = deg2rad($point1[0]);
        $lon1 = deg2rad($point1[1]);
        $lat2 = deg2rad($point2[0]);
        $lon2 = deg2rad($point2[1]);

        $U1 = atan((1 - $f) * tan($lat1));
        $U2 = atan((1 - $f) * tan($lat2));
        $L = $lon2 - $lon1;

        $sinU1 = sin($U1);
        $cosU1 = cos($U1);
        $sinU2 = sin($U2);
        $cosU2 = cos($U2);

        $lambda = $L;
        $lambdaP = 2 * M_PI;
        $iterLimit = 100;

        while (abs($lambda - $lambdaP) > 1e-12 && --$iterLimit > 0) {
            $sinLambda = sin($lambda);
            $cosLambda = cos($lambda);
            $sinSigma = sqrt(
                ($cosU2 * $sinLambda) ** 2 +
                ($cosU1 * $sinU2 - $sinU1 * $cosU2 * $cosLambda) ** 2
            );

            if ($sinSigma == 0) {
                return 0; // Co-incident points
            }

            $cosSigma = $sinU1 * $sinU2 + $cosU1 * $cosU2 * $cosLambda;
            $sigma = atan2($sinSigma, $cosSigma);
            $sinAlpha = $cosU1 * $cosU2 * $sinLambda / $sinSigma;
            $cosSqAlpha = 1 - $sinAlpha ** 2;
            $cos2SigmaM = $cosSqAlpha != 0 ? $cosSigma - 2 * $sinU1 * $sinU2 / $cosSqAlpha : 0;
            $C = $f / 16 * $cosSqAlpha * (4 + $f * (4 - 3 * $cosSqAlpha));
            $lambdaP = $lambda;
            $lambda = $L + (1 - $C) * $f * $sinAlpha * (
                $sigma + $C * $sinSigma * (
                    $cos2SigmaM + $C * $cosSigma * (-1 + 2 * $cos2SigmaM ** 2)
                )
            );
        }

        if ($iterLimit == 0) {
            return NAN; // Formula failed to converge
        }

        $uSq = $cosSqAlpha * ($a ** 2 - $b ** 2) / ($b ** 2);
        $A = 1 + $uSq / 16384 * (4096 + $uSq * (-768 + $uSq * (320 - 175 * $uSq)));
        $B = $uSq / 1024 * (256 + $uSq * (-128 + $uSq * (74 - 47 * $uSq)));
        $deltaSigma = $B * $sinSigma * (
            $cos2SigmaM + $B / 4 * (
                $cosSigma * (-1 + 2 * $cos2SigmaM ** 2) -
                $B / 6 * $cos2SigmaM * (-3 + 4 * $sinSigma ** 2) * (-3 + 4 * $cos2SigmaM ** 2)
            )
        );

        return $b * $A * ($sigma - $deltaSigma);
    }

    /**
     * Calculate total length of a polyline.
     *
     * @param  array<int, array{0: float, 1: float}>  $points  Array of [lat, lng] points
     * @param  string  $unit  'km', 'm', 'mi', 'nm'
     * @return float Total length in specified unit
     */
    public function polylineLength(array $points, string $unit = 'km'): float
    {
        if (count($points) < 2) {
            return 0;
        }

        $length = 0;
        for ($i = 0; $i < count($points) - 1; $i++) {
            $length += $this->distance($points[$i], $points[$i + 1], $unit);
        }

        return $length;
    }

    /**
     * Calculate the area of a polygon using the Shoelace formula with geodesic correction.
     *
     * @param  array<int, array{0: float, 1: float}>  $coordinates  Ring coordinates [lat, lng]
     * @param  string  $unit  'km2', 'm2', 'ha' (hectares), 'acres'
     * @return float Area in specified unit
     */
    public function polygonArea(array $coordinates, string $unit = 'km2'): float
    {
        $n = count($coordinates);
        if ($n < 3) {
            return 0;
        }

        // Close the ring if not closed
        if ($coordinates[0] !== $coordinates[$n - 1]) {
            $coordinates[] = $coordinates[0];
            $n++;
        }

        // Use spherical excess formula for geodesic area
        $area = 0;
        for ($i = 0; $i < $n - 1; $i++) {
            $lat1 = deg2rad($coordinates[$i][0]);
            $lng1 = deg2rad($coordinates[$i][1]);
            $lat2 = deg2rad($coordinates[$i + 1][0]);
            $lng2 = deg2rad($coordinates[$i + 1][1]);

            $area += ($lng2 - $lng1) * (2 + sin($lat1) + sin($lat2));
        }

        $area = abs($area * self::EARTH_RADIUS_KM ** 2 / 2);

        return match ($unit) {
            'km2' => $area,
            'm2' => $area * 1_000_000,
            'ha' => $area * 100,
            'acres' => $area * 247.105,
            default => $area,
        };
    }

    /**
     * Calculate initial bearing (azimuth) from point1 to point2.
     *
     * @param  array{0: float, 1: float}  $point1  [latitude, longitude]
     * @param  array{0: float, 1: float}  $point2  [latitude, longitude]
     * @return float Bearing in degrees (0-360)
     */
    public function bearing(array $point1, array $point2): float
    {
        $lat1 = deg2rad($point1[0]);
        $lon1 = deg2rad($point1[1]);
        $lat2 = deg2rad($point2[0]);
        $lon2 = deg2rad($point2[1]);

        $dlon = $lon2 - $lon1;

        $x = cos($lat2) * sin($dlon);
        $y = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dlon);

        $bearing = rad2deg(atan2($x, $y));

        return fmod($bearing + 360, 360);
    }

    /**
     * Calculate final bearing (bearing at destination).
     *
     * @param  array{0: float, 1: float}  $point1  [latitude, longitude]
     * @param  array{0: float, 1: float}  $point2  [latitude, longitude]
     * @return float Final bearing in degrees (0-360)
     */
    public function finalBearing(array $point1, array $point2): float
    {
        // Reverse the points and add 180°
        $bearing = $this->bearing($point2, $point1);

        return fmod($bearing + 180, 360);
    }

    /**
     * Calculate destination point given start point, bearing, and distance.
     *
     * @param  array{0: float, 1: float}  $point  Start point [latitude, longitude]
     * @param  float  $bearing  Bearing in degrees
     * @param  float  $distance  Distance in kilometers
     * @return array{0: float, 1: float} Destination [latitude, longitude]
     */
    public function destination(array $point, float $bearing, float $distance): array
    {
        $lat1 = deg2rad($point[0]);
        $lon1 = deg2rad($point[1]);
        $brng = deg2rad($bearing);
        $d = $distance / self::EARTH_RADIUS_KM;

        $lat2 = asin(sin($lat1) * cos($d) + cos($lat1) * sin($d) * cos($brng));
        $lon2 = $lon1 + atan2(
            sin($brng) * sin($d) * cos($lat1),
            cos($d) - sin($lat1) * sin($lat2)
        );

        return [rad2deg($lat2), rad2deg($lon2)];
    }

    /**
     * Calculate midpoint between two points.
     *
     * @param  array{0: float, 1: float}  $point1  [latitude, longitude]
     * @param  array{0: float, 1: float}  $point2  [latitude, longitude]
     * @return array{0: float, 1: float} Midpoint [latitude, longitude]
     */
    public function midpoint(array $point1, array $point2): array
    {
        $lat1 = deg2rad($point1[0]);
        $lon1 = deg2rad($point1[1]);
        $lat2 = deg2rad($point2[0]);
        $lon2 = deg2rad($point2[1]);

        $dlon = $lon2 - $lon1;

        $Bx = cos($lat2) * cos($dlon);
        $By = cos($lat2) * sin($dlon);

        $lat3 = atan2(
            sin($lat1) + sin($lat2),
            sqrt((cos($lat1) + $Bx) ** 2 + $By ** 2)
        );
        $lon3 = $lon1 + atan2($By, cos($lat1) + $Bx);

        return [rad2deg($lat3), rad2deg($lon3)];
    }

    /**
     * Calculate centroid of a polygon.
     *
     * @param  array<int, array{0: float, 1: float}>  $coordinates  Polygon coordinates
     * @return array{0: float, 1: float} Centroid [latitude, longitude]
     */
    public function centroid(array $coordinates): array
    {
        $n = count($coordinates);
        if ($n === 0) {
            return [0, 0];
        }

        $latSum = 0;
        $lngSum = 0;

        foreach ($coordinates as $coord) {
            $latSum += $coord[0];
            $lngSum += $coord[1];
        }

        return [$latSum / $n, $lngSum / $n];
    }

    /**
     * Check if a point is inside a polygon using ray casting algorithm.
     *
     * @param  array{0: float, 1: float}  $point  [latitude, longitude]
     * @param  array<int, array{0: float, 1: float}>  $polygon  Polygon coordinates
     * @return bool True if point is inside polygon
     */
    public function pointInPolygon(array $point, array $polygon): bool
    {
        $n = count($polygon);
        if ($n < 3) {
            return false;
        }

        $inside = false;
        $x = $point[1]; // longitude
        $y = $point[0]; // latitude

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $polygon[$i][1];
            $yi = $polygon[$i][0];
            $xj = $polygon[$j][1];
            $yj = $polygon[$j][0];

            if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * Calculate bounding box for a set of coordinates.
     *
     * @param  array<int, array{0: float, 1: float}>  $coordinates
     * @return array{minLat: float, maxLat: float, minLng: float, maxLng: float}
     */
    public function boundingBox(array $coordinates): array
    {
        if (empty($coordinates)) {
            return ['minLat' => 0, 'maxLat' => 0, 'minLng' => 0, 'maxLng' => 0];
        }

        $lats = array_column($coordinates, 0);
        $lngs = array_column($coordinates, 1);

        return [
            'minLat' => min($lats),
            'maxLat' => max($lats),
            'minLng' => min($lngs),
            'maxLng' => max($lngs),
        ];
    }

    /**
     * Calculate buffer zone around a point (circle approximation).
     *
     * @param  array{0: float, 1: float}  $center  [latitude, longitude]
     * @param  float  $radius  Radius in kilometers
     * @param  int  $segments  Number of segments for circle approximation
     * @return array<int, array{0: float, 1: float}> Buffer polygon coordinates
     */
    public function buffer(array $center, float $radius, int $segments = 64): array
    {
        $points = [];
        $angleStep = 360 / $segments;

        for ($i = 0; $i < $segments; $i++) {
            $bearing = $i * $angleStep;
            $points[] = $this->destination($center, $bearing, $radius);
        }

        // Close the ring
        $points[] = $points[0];

        return $points;
    }

    /**
     * Format distance with appropriate unit.
     *
     * @param  float  $distanceKm  Distance in kilometers
     * @return string Formatted distance
     */
    public function formatDistance(float $distanceKm): string
    {
        if ($distanceKm < 1) {
            return round($distanceKm * 1000).' m';
        }

        if ($distanceKm < 100) {
            return round($distanceKm, 2).' km';
        }

        return round($distanceKm, 1).' km';
    }

    /**
     * Format area with appropriate unit.
     *
     * @param  float  $areaKm2  Area in square kilometers
     * @return string Formatted area
     */
    public function formatArea(float $areaKm2): string
    {
        if ($areaKm2 < 0.01) {
            return round($areaKm2 * 1_000_000).' m²';
        }

        if ($areaKm2 < 1) {
            return round($areaKm2 * 100, 2).' ha';
        }

        return round($areaKm2, 2).' km²';
    }

    /**
     * Format bearing as compass direction.
     *
     * @param  float  $bearing  Bearing in degrees
     * @return string Compass direction (N, NE, E, SE, S, SW, W, NW)
     */
    public function bearingToCompass(float $bearing): string
    {
        $directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        $index = (int) round($bearing / 45) % 8;

        return $directions[$index];
    }
}
