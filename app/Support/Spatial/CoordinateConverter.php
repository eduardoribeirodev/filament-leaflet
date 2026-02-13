<?php

declare(strict_types=1);

namespace App\Support\Spatial;

/**
 * CoordinateConverter - Coordinate format conversion and CRS transformation.
 *
 * Supports conversion between:
 * - Decimal Degrees (DD): -23.550520, -46.633308
 * - Degrees Minutes Seconds (DMS): 23°33'01.9"S, 46°37'59.9"W
 * - Degrees Decimal Minutes (DDM): 23°33.0312'S, 46°37.9985'W
 * - UTM: 23K 333333 7394444
 * - MGRS: 23K PQ 33333 94444
 *
 * @example
 * $converter = new CoordinateConverter();
 * $dms = $converter->toDMS(-23.550520, -46.633308);
 * $dd = $converter->fromDMS("23°33'01.9\"S", "46°37'59.9\"W");
 * $utm = $converter->toUTM(-23.550520, -46.633308);
 */
class CoordinateConverter
{
    /**
     * Convert decimal degrees to DMS format.
     *
     * @param  float  $latitude  Decimal latitude
     * @param  float  $longitude  Decimal longitude
     * @param  int  $precision  Decimal places for seconds
     * @return array{lat: string, lng: string, formatted: string}
     */
    public function toDMS(float $latitude, float $longitude, int $precision = 1): array
    {
        $latDMS = $this->decimalToDMS(abs($latitude), $precision);
        $lngDMS = $this->decimalToDMS(abs($longitude), $precision);

        $latDir = $latitude >= 0 ? 'N' : 'S';
        $lngDir = $longitude >= 0 ? 'E' : 'W';

        $latStr = sprintf("%d°%d'%s\"%s", $latDMS['d'], $latDMS['m'], $latDMS['s'], $latDir);
        $lngStr = sprintf("%d°%d'%s\"%s", $lngDMS['d'], $lngDMS['m'], $lngDMS['s'], $lngDir);

        return [
            'lat' => $latStr,
            'lng' => $lngStr,
            'formatted' => "$latStr, $lngStr",
        ];
    }

    /**
     * Convert decimal degrees to DDM format.
     *
     * @param  float  $latitude  Decimal latitude
     * @param  float  $longitude  Decimal longitude
     * @param  int  $precision  Decimal places for minutes
     * @return array{lat: string, lng: string, formatted: string}
     */
    public function toDDM(float $latitude, float $longitude, int $precision = 4): array
    {
        $latDDM = $this->decimalToDDM(abs($latitude), $precision);
        $lngDDM = $this->decimalToDDM(abs($longitude), $precision);

        $latDir = $latitude >= 0 ? 'N' : 'S';
        $lngDir = $longitude >= 0 ? 'E' : 'W';

        $latStr = sprintf("%d°%s'%s", $latDDM['d'], $latDDM['m'], $latDir);
        $lngStr = sprintf("%d°%s'%s", $lngDDM['d'], $lngDDM['m'], $lngDir);

        return [
            'lat' => $latStr,
            'lng' => $lngStr,
            'formatted' => "$latStr, $lngStr",
        ];
    }

    /**
     * Convert DMS strings to decimal degrees.
     *
     * @param  string  $latDMS  Latitude in DMS format (e.g., "23°33'01.9\"S")
     * @param  string  $lngDMS  Longitude in DMS format (e.g., "46°37'59.9\"W")
     * @return array{lat: float, lng: float}
     */
    public function fromDMS(string $latDMS, string $lngDMS): array
    {
        return [
            'lat' => $this->dmsToDecimal($latDMS),
            'lng' => $this->dmsToDecimal($lngDMS),
        ];
    }

    /**
     * Convert decimal degrees to UTM.
     *
     * @param  float  $latitude  Decimal latitude
     * @param  float  $longitude  Decimal longitude
     * @return array{zone: int, band: string, easting: float, northing: float, formatted: string}
     */
    public function toUTM(float $latitude, float $longitude): array
    {
        // WGS84 parameters
        $a = 6378137.0;
        $f = 1 / 298.257223563;
        $k0 = 0.9996;

        $e2 = 2 * $f - $f ** 2;
        $e4 = $e2 ** 2;
        $e6 = $e2 ** 3;
        $ep2 = $e2 / (1 - $e2);

        $zone = (int) floor(($longitude + 180) / 6) + 1;

        // Special zones for Norway and Svalbard
        if ($latitude >= 56 && $latitude < 64 && $longitude >= 3 && $longitude < 12) {
            $zone = 32;
        }
        if ($latitude >= 72 && $latitude < 84) {
            if ($longitude >= 0 && $longitude < 9) {
                $zone = 31;
            } elseif ($longitude >= 9 && $longitude < 21) {
                $zone = 33;
            } elseif ($longitude >= 21 && $longitude < 33) {
                $zone = 35;
            } elseif ($longitude >= 33 && $longitude < 42) {
                $zone = 37;
            }
        }

        $band = $this->getUTMBand($latitude);
        $centralMeridian = ($zone - 1) * 6 - 180 + 3;

        $latRad = deg2rad($latitude);
        $lonRad = deg2rad($longitude);
        $lonOrigin = deg2rad($centralMeridian);

        $N = $a / sqrt(1 - $e2 * sin($latRad) ** 2);
        $T = tan($latRad) ** 2;
        $C = $ep2 * cos($latRad) ** 2;
        $A = ($lonRad - $lonOrigin) * cos($latRad);

        $M = $a * (
            (1 - $e2 / 4 - 3 * $e4 / 64 - 5 * $e6 / 256) * $latRad
            - (3 * $e2 / 8 + 3 * $e4 / 32 + 45 * $e6 / 1024) * sin(2 * $latRad)
            + (15 * $e4 / 256 + 45 * $e6 / 1024) * sin(4 * $latRad)
            - (35 * $e6 / 3072) * sin(6 * $latRad)
        );

        $easting = $k0 * $N * (
            $A + (1 - $T + $C) * $A ** 3 / 6
            + (5 - 18 * $T + $T ** 2 + 72 * $C - 58 * $ep2) * $A ** 5 / 120
        ) + 500000;

        $northing = $k0 * (
            $M + $N * tan($latRad) * (
                $A ** 2 / 2
                + (5 - $T + 9 * $C + 4 * $C ** 2) * $A ** 4 / 24
                + (61 - 58 * $T + $T ** 2 + 600 * $C - 330 * $ep2) * $A ** 6 / 720
            )
        );

        if ($latitude < 0) {
            $northing += 10000000;
        }

        return [
            'zone' => $zone,
            'band' => $band,
            'easting' => round($easting, 2),
            'northing' => round($northing, 2),
            'formatted' => sprintf('%d%s %.0f %.0f', $zone, $band, $easting, $northing),
        ];
    }

    /**
     * Convert UTM to decimal degrees.
     *
     * @param  int  $zone  UTM zone number
     * @param  string  $band  UTM band letter
     * @param  float  $easting  Easting in meters
     * @param  float  $northing  Northing in meters
     * @return array{lat: float, lng: float}
     */
    public function fromUTM(int $zone, string $band, float $easting, float $northing): array
    {
        $a = 6378137.0;
        $f = 1 / 298.257223563;
        $k0 = 0.9996;

        $e2 = 2 * $f - $f ** 2;
        $e1 = (1 - sqrt(1 - $e2)) / (1 + sqrt(1 - $e2));
        $ep2 = $e2 / (1 - $e2);

        $x = $easting - 500000;
        $y = $northing;

        // Southern hemisphere
        if (ord($band) < ord('N')) {
            $y -= 10000000;
        }

        $centralMeridian = ($zone - 1) * 6 - 180 + 3;

        $M = $y / $k0;
        $mu = $M / ($a * (1 - $e2 / 4 - 3 * $e2 ** 2 / 64 - 5 * $e2 ** 3 / 256));

        $phi1 = $mu
            + (3 * $e1 / 2 - 27 * $e1 ** 3 / 32) * sin(2 * $mu)
            + (21 * $e1 ** 2 / 16 - 55 * $e1 ** 4 / 32) * sin(4 * $mu)
            + (151 * $e1 ** 3 / 96) * sin(6 * $mu)
            + (1097 * $e1 ** 4 / 512) * sin(8 * $mu);

        $N1 = $a / sqrt(1 - $e2 * sin($phi1) ** 2);
        $T1 = tan($phi1) ** 2;
        $C1 = $ep2 * cos($phi1) ** 2;
        $R1 = $a * (1 - $e2) / (1 - $e2 * sin($phi1) ** 2) ** 1.5;
        $D = $x / ($N1 * $k0);

        $lat = $phi1 - ($N1 * tan($phi1) / $R1) * (
            $D ** 2 / 2
            - (5 + 3 * $T1 + 10 * $C1 - 4 * $C1 ** 2 - 9 * $ep2) * $D ** 4 / 24
            + (61 + 90 * $T1 + 298 * $C1 + 45 * $T1 ** 2 - 252 * $ep2 - 3 * $C1 ** 2) * $D ** 6 / 720
        );

        $lng = deg2rad($centralMeridian) + (
            $D
            - (1 + 2 * $T1 + $C1) * $D ** 3 / 6
            + (5 - 2 * $C1 + 28 * $T1 - 3 * $C1 ** 2 + 8 * $ep2 + 24 * $T1 ** 2) * $D ** 5 / 120
        ) / cos($phi1);

        return [
            'lat' => rad2deg($lat),
            'lng' => rad2deg($lng),
        ];
    }

    /**
     * Format coordinates as GeoJSON Point.
     *
     * @param  float  $latitude  Decimal latitude
     * @param  float  $longitude  Decimal longitude
     * @return array{type: string, coordinates: array{0: float, 1: float}}
     */
    public function toGeoJSON(float $latitude, float $longitude): array
    {
        return [
            'type' => 'Point',
            'coordinates' => [$longitude, $latitude], // GeoJSON is [lng, lat]
        ];
    }

    /**
     * Parse GeoJSON Point to lat/lng.
     *
     * @param  array{type: string, coordinates: array{0: float, 1: float}}  $geoJson
     * @return array{lat: float, lng: float}
     */
    public function fromGeoJSON(array $geoJson): array
    {
        if ($geoJson['type'] !== 'Point' || ! isset($geoJson['coordinates'])) {
            throw new \InvalidArgumentException('Invalid GeoJSON Point');
        }

        return [
            'lat' => $geoJson['coordinates'][1],
            'lng' => $geoJson['coordinates'][0],
        ];
    }

    /**
     * Validate latitude value.
     */
    public function isValidLatitude(float $latitude): bool
    {
        return $latitude >= -90 && $latitude <= 90;
    }

    /**
     * Validate longitude value.
     */
    public function isValidLongitude(float $longitude): bool
    {
        return $longitude >= -180 && $longitude <= 180;
    }

    /**
     * Validate coordinate pair.
     */
    public function isValidCoordinate(float $latitude, float $longitude): bool
    {
        return $this->isValidLatitude($latitude) && $this->isValidLongitude($longitude);
    }

    /**
     * Normalize longitude to -180 to 180 range.
     */
    public function normalizeLongitude(float $longitude): float
    {
        while ($longitude > 180) {
            $longitude -= 360;
        }
        while ($longitude < -180) {
            $longitude += 360;
        }

        return $longitude;
    }

    /**
     * Convert decimal to degrees, minutes, seconds.
     */
    protected function decimalToDMS(float $decimal, int $precision = 1): array
    {
        $d = (int) $decimal;
        $mf = ($decimal - $d) * 60;
        $m = (int) $mf;
        $s = round(($mf - $m) * 60, $precision);

        // Handle 60 seconds overflow
        if ($s >= 60) {
            $s -= 60;
            $m++;
        }
        if ($m >= 60) {
            $m -= 60;
            $d++;
        }

        return ['d' => $d, 'm' => $m, 's' => number_format($s, $precision)];
    }

    /**
     * Convert decimal to degrees, decimal minutes.
     */
    protected function decimalToDDM(float $decimal, int $precision = 4): array
    {
        $d = (int) $decimal;
        $m = round(($decimal - $d) * 60, $precision);

        if ($m >= 60) {
            $m -= 60;
            $d++;
        }

        return ['d' => $d, 'm' => number_format($m, $precision)];
    }

    /**
     * Convert DMS string to decimal.
     */
    protected function dmsToDecimal(string $dms): float
    {
        // Match patterns like: 23°33'01.9"S, 23 33' 01.9" S, 23d33'01.9"S etc.
        $pattern = '/(\d+)[°d\s]\s*(\d+)[\'′]\s*([\d.]+)[\"″]?\s*([NSEW])?/i';

        if (! preg_match($pattern, $dms, $matches)) {
            throw new \InvalidArgumentException("Invalid DMS format: $dms");
        }

        $degrees = (int) $matches[1];
        $minutes = (int) $matches[2];
        $seconds = (float) $matches[3];
        $direction = strtoupper($matches[4] ?? '');

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

        if ($direction === 'S' || $direction === 'W') {
            $decimal = -$decimal;
        }

        return $decimal;
    }

    /**
     * Get UTM band letter for latitude.
     */
    protected function getUTMBand(float $latitude): string
    {
        $bands = 'CDEFGHJKLMNPQRSTUVWX';

        if ($latitude < -80 || $latitude > 84) {
            return 'Z'; // Outside UTM bounds
        }

        $index = (int) floor(($latitude + 80) / 8);

        return $bands[$index] ?? 'Z';
    }
}
