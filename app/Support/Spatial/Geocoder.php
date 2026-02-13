<?php

declare(strict_types=1);

namespace App\Support\Spatial;

use Illuminate\Support\Facades\Http;

/**
 * Geocoder - Unified geocoding interface supporting multiple providers.
 *
 * Supports:
 * - Nominatim (OpenStreetMap) - Free, no API key required
 * - Mapbox - Requires API key
 * - Google Maps - Requires API key
 *
 * @example
 * $geocoder = new Geocoder();
 * $result = $geocoder->geocode('1600 Pennsylvania Avenue, Washington DC');
 * $address = $geocoder->reverse(-23.550520, -46.633308);
 * $results = $geocoder->batch(['Address 1', 'Address 2']);
 */
class Geocoder
{
    /**
     * Default provider.
     */
    protected string $provider = 'nominatim';

    /**
     * API keys for providers.
     */
    protected array $apiKeys = [];

    /**
     * User agent for Nominatim (required).
     */
    protected string $userAgent = 'LeafletApp/1.0';

    /**
     * Country code bias for search results.
     */
    protected ?string $countryBias = null;

    /**
     * Language for results.
     */
    protected string $language = 'en';

    /**
     * Maximum results to return.
     */
    protected int $limit = 5;

    /**
     * Create a new Geocoder instance.
     */
    public function __construct(?string $provider = null)
    {
        if ($provider) {
            $this->provider = $provider;
        }

        // Load API keys from config
        $this->apiKeys = [
            'mapbox' => config('services.mapbox.access_token'),
            'google' => config('services.google.maps_api_key'),
        ];
    }

    /**
     * Set the geocoding provider.
     */
    public function provider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Set API key for a provider.
     */
    public function apiKey(string $provider, string $key): static
    {
        $this->apiKeys[$provider] = $key;

        return $this;
    }

    /**
     * Set the user agent (required for Nominatim).
     */
    public function userAgent(string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    /**
     * Set country bias for search results.
     */
    public function countryBias(string $countryCode): static
    {
        $this->countryBias = strtoupper($countryCode);

        return $this;
    }

    /**
     * Set language for results.
     */
    public function language(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Set maximum results to return.
     */
    public function limit(int $limit): static
    {
        $this->limit = max(1, min(50, $limit));

        return $this;
    }

    /**
     * Geocode an address to coordinates.
     *
     * @param  string  $address  Address to geocode
     * @return array<int, GeocoderResult>
     */
    public function geocode(string $address): array
    {
        return match ($this->provider) {
            'nominatim' => $this->geocodeNominatim($address),
            'mapbox' => $this->geocodeMapbox($address),
            'google' => $this->geocodeGoogle($address),
            default => throw new \InvalidArgumentException("Unknown provider: {$this->provider}"),
        };
    }

    /**
     * Reverse geocode coordinates to address.
     *
     * @param  float  $latitude  Latitude
     * @param  float  $longitude  Longitude
     */
    public function reverse(float $latitude, float $longitude): ?GeocoderResult
    {
        return match ($this->provider) {
            'nominatim' => $this->reverseNominatim($latitude, $longitude),
            'mapbox' => $this->reverseMapbox($latitude, $longitude),
            'google' => $this->reverseGoogle($latitude, $longitude),
            default => throw new \InvalidArgumentException("Unknown provider: {$this->provider}"),
        };
    }

    /**
     * Batch geocode multiple addresses.
     *
     * @param  array<string>  $addresses  Array of addresses
     * @return array<string, array<GeocoderResult>> Results keyed by address
     */
    public function batch(array $addresses): array
    {
        $results = [];

        foreach ($addresses as $address) {
            $results[$address] = $this->geocode($address);
        }

        return $results;
    }

    /**
     * Get first result for an address (convenience method).
     */
    public function geocodeFirst(string $address): ?GeocoderResult
    {
        $results = $this->geocode($address);

        return $results[0] ?? null;
    }

    /**
     * Geocode using Nominatim (OpenStreetMap).
     */
    protected function geocodeNominatim(string $address): array
    {
        $params = [
            'q' => $address,
            'format' => 'json',
            'addressdetails' => 1,
            'limit' => $this->limit,
            'accept-language' => $this->language,
        ];

        if ($this->countryBias) {
            $params['countrycodes'] = strtolower($this->countryBias);
        }

        $response = Http::withHeaders(['User-Agent' => $this->userAgent])
            ->timeout(10)
            ->get('https://nominatim.openstreetmap.org/search', $params);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json())
            ->map(fn ($item) => $this->parseNominatimResult($item))
            ->toArray();
    }

    /**
     * Reverse geocode using Nominatim.
     */
    protected function reverseNominatim(float $latitude, float $longitude): ?GeocoderResult
    {
        $params = [
            'lat' => $latitude,
            'lon' => $longitude,
            'format' => 'json',
            'addressdetails' => 1,
            'accept-language' => $this->language,
        ];

        $response = Http::withHeaders(['User-Agent' => $this->userAgent])
            ->timeout(10)
            ->get('https://nominatim.openstreetmap.org/reverse', $params);

        if (! $response->successful() || $response->json('error')) {
            return null;
        }

        return $this->parseNominatimResult($response->json());
    }

    /**
     * Parse Nominatim response.
     */
    protected function parseNominatimResult(array $item): GeocoderResult
    {
        $address = $item['address'] ?? [];

        return new GeocoderResult(
            latitude: (float) $item['lat'],
            longitude: (float) $item['lon'],
            displayName: $item['display_name'] ?? '',
            street: trim(($address['road'] ?? '').' '.($address['house_number'] ?? '')),
            city: $address['city'] ?? $address['town'] ?? $address['village'] ?? '',
            state: $address['state'] ?? '',
            country: $address['country'] ?? '',
            countryCode: strtoupper($address['country_code'] ?? ''),
            postalCode: $address['postcode'] ?? '',
            confidence: $this->calculateConfidence($item),
            provider: 'nominatim',
            raw: $item,
        );
    }

    /**
     * Geocode using Mapbox.
     */
    protected function geocodeMapbox(string $address): array
    {
        $apiKey = $this->apiKeys['mapbox'] ?? throw new \RuntimeException('Mapbox API key not configured');

        $params = [
            'access_token' => $apiKey,
            'limit' => $this->limit,
            'language' => $this->language,
        ];

        if ($this->countryBias) {
            $params['country'] = strtolower($this->countryBias);
        }

        $response = Http::timeout(10)
            ->get('https://api.mapbox.com/geocoding/v5/mapbox.places/'.urlencode($address).'.json', $params);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('features', []))
            ->map(fn ($item) => $this->parseMapboxResult($item))
            ->toArray();
    }

    /**
     * Reverse geocode using Mapbox.
     */
    protected function reverseMapbox(float $latitude, float $longitude): ?GeocoderResult
    {
        $apiKey = $this->apiKeys['mapbox'] ?? throw new \RuntimeException('Mapbox API key not configured');

        $params = [
            'access_token' => $apiKey,
            'language' => $this->language,
        ];

        $response = Http::timeout(10)
            ->get("https://api.mapbox.com/geocoding/v5/mapbox.places/{$longitude},{$latitude}.json", $params);

        if (! $response->successful()) {
            return null;
        }

        $features = $response->json('features', []);

        return isset($features[0]) ? $this->parseMapboxResult($features[0]) : null;
    }

    /**
     * Parse Mapbox response.
     */
    protected function parseMapboxResult(array $item): GeocoderResult
    {
        $context = collect($item['context'] ?? []);

        return new GeocoderResult(
            latitude: $item['center'][1],
            longitude: $item['center'][0],
            displayName: $item['place_name'] ?? '',
            street: $item['text'] ?? '',
            city: $context->firstWhere(fn ($c) => str_starts_with($c['id'], 'place'))['text'] ?? '',
            state: $context->firstWhere(fn ($c) => str_starts_with($c['id'], 'region'))['text'] ?? '',
            country: $context->firstWhere(fn ($c) => str_starts_with($c['id'], 'country'))['text'] ?? '',
            countryCode: strtoupper($context->firstWhere(fn ($c) => str_starts_with($c['id'], 'country'))['short_code'] ?? ''),
            postalCode: $context->firstWhere(fn ($c) => str_starts_with($c['id'], 'postcode'))['text'] ?? '',
            confidence: $item['relevance'] ?? 0,
            provider: 'mapbox',
            raw: $item,
        );
    }

    /**
     * Geocode using Google Maps.
     */
    protected function geocodeGoogle(string $address): array
    {
        $apiKey = $this->apiKeys['google'] ?? throw new \RuntimeException('Google Maps API key not configured');

        $params = [
            'address' => $address,
            'key' => $apiKey,
            'language' => $this->language,
        ];

        if ($this->countryBias) {
            $params['components'] = "country:{$this->countryBias}";
        }

        $response = Http::timeout(10)
            ->get('https://maps.googleapis.com/maps/api/geocode/json', $params);

        if (! $response->successful() || $response->json('status') !== 'OK') {
            return [];
        }

        return collect($response->json('results', []))
            ->take($this->limit)
            ->map(fn ($item) => $this->parseGoogleResult($item))
            ->toArray();
    }

    /**
     * Reverse geocode using Google Maps.
     */
    protected function reverseGoogle(float $latitude, float $longitude): ?GeocoderResult
    {
        $apiKey = $this->apiKeys['google'] ?? throw new \RuntimeException('Google Maps API key not configured');

        $params = [
            'latlng' => "{$latitude},{$longitude}",
            'key' => $apiKey,
            'language' => $this->language,
        ];

        $response = Http::timeout(10)
            ->get('https://maps.googleapis.com/maps/api/geocode/json', $params);

        if (! $response->successful() || $response->json('status') !== 'OK') {
            return null;
        }

        $results = $response->json('results', []);

        return isset($results[0]) ? $this->parseGoogleResult($results[0]) : null;
    }

    /**
     * Parse Google Maps response.
     */
    protected function parseGoogleResult(array $item): GeocoderResult
    {
        $components = collect($item['address_components'] ?? []);
        $location = $item['geometry']['location'] ?? [];

        $getComponent = fn (string $type) => $components
            ->firstWhere(fn ($c) => in_array($type, $c['types'] ?? []))['long_name'] ?? '';

        $getShortComponent = fn (string $type) => $components
            ->firstWhere(fn ($c) => in_array($type, $c['types'] ?? []))['short_name'] ?? '';

        return new GeocoderResult(
            latitude: (float) ($location['lat'] ?? 0),
            longitude: (float) ($location['lng'] ?? 0),
            displayName: $item['formatted_address'] ?? '',
            street: trim($getComponent('route').' '.$getComponent('street_number')),
            city: $getComponent('locality') ?: $getComponent('administrative_area_level_2'),
            state: $getComponent('administrative_area_level_1'),
            country: $getComponent('country'),
            countryCode: strtoupper($getShortComponent('country')),
            postalCode: $getComponent('postal_code'),
            confidence: $this->mapGoogleLocationType($item['geometry']['location_type'] ?? ''),
            provider: 'google',
            raw: $item,
        );
    }

    /**
     * Calculate confidence score for Nominatim results.
     */
    protected function calculateConfidence(array $item): float
    {
        $importance = (float) ($item['importance'] ?? 0.5);

        return min(1, max(0, $importance));
    }

    /**
     * Map Google location type to confidence score.
     */
    protected function mapGoogleLocationType(string $type): float
    {
        return match ($type) {
            'ROOFTOP' => 1.0,
            'RANGE_INTERPOLATED' => 0.8,
            'GEOMETRIC_CENTER' => 0.6,
            'APPROXIMATE' => 0.4,
            default => 0.5,
        };
    }
}
