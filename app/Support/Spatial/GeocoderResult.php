<?php

declare(strict_types=1);

namespace App\Support\Spatial;

/**
 * GeocoderResult - Geocoding result data object.
 */
class GeocoderResult
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly string $displayName,
        public readonly string $street,
        public readonly string $city,
        public readonly string $state,
        public readonly string $country,
        public readonly string $countryCode,
        public readonly string $postalCode,
        public readonly float $confidence,
        public readonly string $provider,
        public readonly array $raw = [],
    ) {}

    /**
     * Get coordinates as array.
     *
     * @return array{lat: float, lng: float}
     */
    public function toArray(): array
    {
        return [
            'lat' => $this->latitude,
            'lng' => $this->longitude,
        ];
    }

    /**
     * Get coordinates as GeoJSON Point.
     */
    public function toGeoJSON(): array
    {
        return [
            'type' => 'Point',
            'coordinates' => [$this->longitude, $this->latitude],
        ];
    }

    /**
     * Get formatted address.
     */
    public function getFormattedAddress(): string
    {
        $parts = array_filter([
            $this->street,
            $this->city,
            $this->state,
            $this->postalCode,
            $this->country,
        ]);

        return implode(', ', $parts);
    }
}
