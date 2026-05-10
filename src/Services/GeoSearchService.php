<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Services;

use EduardoRibeiroDev\FilamentLeaflet\Enums\GeoSearchProvider;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\Address;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\Coordinate;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\GeoSearchResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Lang;

class GeoSearchService
{
    protected GeoSearchProvider $provider = GeoSearchProvider::Nominatim;
    protected ?string $apiKey = null;
    protected int $limit = 25;
    protected bool $addressDetails = true;
    protected ?string $language = null;
    protected bool $bounded = false;
    protected ?int $cacheTtl = null;

    /** @var string[] */
    protected array $countryCodes = [];

    /** @var float[]|null [minLon, minLat, maxLon, maxLat] */
    protected ?array $viewbox = null;

    // ─── Fluent setters ───────────────────────────────────────────────────────

    /**
     * Define o provedor do geosearch.
     *
     * @param  GeoSearchProvider|string  $provider
     */
    public function provider(GeoSearchProvider|string $provider): static
    {
        if (is_string($provider)) {
            $this->provider = GeoSearchProvider::from($provider);
        } else {
            $this->provider = $provider;
        }

        return $this;
    }

    /**
     * Define a chave de API para provedores que a requerem.
     */
    public function apiKey(string $apiKey): static
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = max(1, $limit);

        return $this;
    }

    public function withAddressDetails(bool $enabled = true): static
    {
        $this->addressDetails = $enabled;

        return $this;
    }

    public function language(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Restrict results to one or more ISO 3166-1 alpha-2 country codes.
     * e.g. 'br', ['br', 'ar']
     *
     * @param  string|string[]  $codes
     */
    public function countryCodes(string|array $codes): static
    {
        $this->countryCodes = array_map(
            fn(string $c) => strtolower($c),
            (array) $codes,
        );

        return $this;
    }

    /**
     * Restrict results to the given viewbox.
     * Only relevant when a viewbox is also set via {@see viewbox()}.
     */
    public function bounded(bool $bounded = true): static
    {
        $this->bounded = $bounded;

        return $this;
    }

    /**
     * Set a bounding box to bias (or restrict when bounded) results.
     *
     * @param  float  $minLon  West longitude
     * @param  float  $minLat  South latitude
     * @param  float  $maxLon  East longitude
     * @param  float  $maxLat  North latitude
     */
    public function viewbox(float $minLon, float $minLat, float $maxLon, float $maxLat): static
    {
        $this->viewbox = [$minLon, $minLat, $maxLon, $maxLat];

        return $this;
    }

    /**
     * Enable result caching via Laravel's cache layer.
     *
     * @param int  $ttl  Seconds to keep cached results (default 3600)
     */
    public function cacheResults(int $ttl = 3600): static
    {
        $this->cacheTtl = $ttl;

        return $this;
    }

    // ─── Search ───────────────────────────────────────────────────────────────

    /**
     * Execute a geocoding search and return a keyed array of
     * [display_label => GeoSearchResult].
     *
     * Each GeoSearchResult contains the full Nominatim payload so callers can
     * react to address components (country, city, postcode, …) in addition to
     * the raw coordinates.
     *
     * @return array<string, GeoSearchResult>
     */
    public function search(string $query): array
    {
        if (blank($query)) {
            return [];
        }

        $cacheKey = $this->buildCacheKey($query);

        if ($this->cacheTtl && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $results = $this->fetchResults($query);

        $mapped = collect($results)->map(function ($result) {
            return new GeoSearchResult(
                coordinate: new Coordinate($result['lat'], $result['lon']),
                type: $result['type'],
                addresstype: $result['addresstype'],
                name: $result['name'],
                displayName: $result['display_name'],
                address: Address::fromArray($result['address']),
                boundingbox: $result['boundingbox'],
            );
        });

        if ($this->cacheTtl) {
            Cache::put($cacheKey, $mapped, $this->cacheTtl);
        }

        return $mapped->all();
    }

    // ─── Internals ────────────────────────────────────────────────────────────

    protected function fetchResults(string $query): array
    {
        $endpoint = $this->provider->getEndpoint();
        $params = $this->buildParams($query);

        $response = Http::withHeaders($this->buildHeaders())
            ->get($endpoint, $params);

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json() ?? [];

        // Normaliza os resultados baseado no provedor
        return $this->normalizeResults($data);
    }

    /**
     * Normaliza os resultados de diferentes provedores para um formato padrão.
     */
    protected function normalizeResults(array $data): array
    {
        return match ($this->provider) {
            GeoSearchProvider::Nominatim => $data,
            GeoSearchProvider::GoogleMaps => $this->normalizeGoogleResults($data),
            GeoSearchProvider::Mapbox => $this->normalizeMapboxResults($data),
            GeoSearchProvider::BingMaps => $this->normalizeBingResults($data),
        };
    }

    /**
     * Normaliza resultados do Google Maps para o formato Nominatim.
     */
    protected function normalizeGoogleResults(array $data): array
    {
        if (! isset($data['results']) || empty($data['results'])) {
            return [];
        }

        return array_slice(array_map(function ($result) {
            return [
                'lat' => $result['geometry']['location']['lat'],
                'lon' => $result['geometry']['location']['lng'],
                'type' => 'building',
                'addresstype' => 'building',
                'name' => $result['formatted_address'],
                'display_name' => $result['formatted_address'],
                'address' => $this->extractAddressComponents($result['address_components'] ?? []),
                'boundingbox' => isset($result['geometry']['bounds']) 
                    ? [
                        $result['geometry']['bounds']['southwest']['lat'],
                        $result['geometry']['bounds']['southwest']['lng'],
                        $result['geometry']['bounds']['northeast']['lat'],
                        $result['geometry']['bounds']['northeast']['lng'],
                    ]
                    : [],
            ];
        }, $data['results']), 0, $this->limit);
    }

    /**
     * Normaliza resultados do Mapbox para o formato Nominatim.
     */
    protected function normalizeMapboxResults(array $data): array
    {
        if (! isset($data['features']) || empty($data['features'])) {
            return [];
        }

        return array_slice(array_map(function ($feature) {
            $coords = $feature['geometry']['coordinates'];

            return [
                'lat' => $coords[1],
                'lon' => $coords[0],
                'type' => 'building',
                'addresstype' => $feature['properties']['place_type'][0] ?? 'building',
                'name' => $feature['text'] ?? $feature['place_name'],
                'display_name' => $feature['place_name'],
                'address' => [],
                'boundingbox' => isset($feature['bbox'])
                    ? [$feature['bbox'][1], $feature['bbox'][0], $feature['bbox'][3], $feature['bbox'][2]]
                    : [],
            ];
        }, $data['features']), 0, $this->limit);
    }

    /**
     * Normaliza resultados do Bing Maps para o formato Nominatim.
     */
    protected function normalizeBingResults(array $data): array
    {
        if (! isset($data['resourceSets']) || empty($data['resourceSets'])) {
            return [];
        }

        $resources = $data['resourceSets'][0]['resources'] ?? [];

        return array_slice(array_map(function ($resource) {
            $bbox = $resource['bbox'] ?? [];

            return [
                'lat' => $resource['point']['coordinates'][0],
                'lon' => $resource['point']['coordinates'][1],
                'type' => 'building',
                'addresstype' => $resource['entityType'] ?? 'building',
                'name' => $resource['name'],
                'display_name' => $resource['address']['formattedAddress'],
                'address' => [
                    'road' => $resource['address']['addressLine'] ?? '',
                    'city' => $resource['address']['locality'] ?? '',
                    'postcode' => $resource['address']['postalCode'] ?? '',
                    'country' => $resource['address']['countryRegion'] ?? '',
                ],
                'boundingbox' => ! empty($bbox)
                    ? [$bbox[0], $bbox[1], $bbox[2], $bbox[3]]
                    : [],
            ];
        }, $resources), 0, $this->limit);
    }

    /**
     * Extrai componentes de endereço do Google Maps.
     */
    protected function extractAddressComponents(array $components): array
    {
        $address = [];

        foreach ($components as $component) {
            $types = $component['types'];

            if (in_array('route', $types)) {
                $address['road'] = $component['long_name'];
            } elseif (in_array('locality', $types)) {
                $address['city'] = $component['long_name'];
            } elseif (in_array('postal_code', $types)) {
                $address['postcode'] = $component['long_name'];
            } elseif (in_array('country', $types)) {
                $address['country'] = $component['long_name'];
            }
        }

        return $address;
    }

    protected function buildParams(string $query): array
    {
        return match ($this->provider) {
            GeoSearchProvider::Nominatim => $this->buildNominatimParams($query),
            GeoSearchProvider::GoogleMaps => $this->buildGoogleParams($query),
            GeoSearchProvider::Mapbox => $this->buildMapboxParams($query),
            GeoSearchProvider::BingMaps => $this->buildBingParams($query),
        };
    }

    /**
     * Constrói parâmetros para Nominatim.
     */
    protected function buildNominatimParams(string $query): array
    {
        $params = [
            'q'              => $query,
            'format'         => 'json',
            'addressdetails' => (int) $this->addressDetails,
            'limit'          => $this->limit,
        ];

        if ($this->countryCodes !== []) {
            $params['countrycodes'] = implode(',', $this->countryCodes);
        }

        if ($this->viewbox !== null) {
            $params['viewbox'] = implode(',', $this->viewbox);
            $params['bounded'] = (int) $this->bounded;
        }

        return $params;
    }

    /**
     * Constrói parâmetros para Google Maps.
     */
    protected function buildGoogleParams(string $query): array
    {
        $params = [
            'address' => $query,
            'key'     => $this->getApiKey(),
        ];

        if ($this->countryCodes !== []) {
            $params['components'] = 'country:' . implode('|country:', $this->countryCodes);
        }

        return $params;
    }

    /**
     * Constrói parâmetros para Mapbox.
     */
    protected function buildMapboxParams(string $query): array
    {
        $endpoint =  $this->provider->getEndpoint() . '/' . urlencode($query) . '.json';
        
        return [
            'access_token' => $this->getApiKey(),
            'limit'        => min($this->limit, 5), // Mapbox tem limite de 5
            'language'     => $this->language ?? Lang::locale(),
        ];
    }

    /**
     * Constrói parâmetros para Bing Maps.
     */
    protected function buildBingParams(string $query): array
    {
        return [
            'query' => $query,
            'key'   => $this->getApiKey(),
        ];
    }

    protected function buildHeaders(): array
    {
        return [
            'Accept'          => 'application/json',
            'Accept-Language' => $this->language ?? Lang::locale(),
        ];
    }

    protected function buildCacheKey(string $query): string
    {
        $parts = array_filter([
            'geo_search',
            $this->provider->value,
            md5($query),
            $this->limit,
            $this->language,
            implode('_', $this->countryCodes),
            $this->viewbox ? implode('_', $this->viewbox) : null,
            (int) $this->bounded,
        ]);

        return implode(':', $parts);
    }

    /**
     * Retorna o provedor configurado.
     */
    public function getProvider(): GeoSearchProvider
    {
        return $this->provider;
    }

    /**
     * Obtém a chave de API para o provedor atual.
     * Se não for configurada manualmente, tenta carregar do env.
     */
    protected function getApiKey(): ?string
    {
        if ($this->apiKey) {
            return $this->apiKey;
        }

        $envVariable = $this->provider->getApiKeyEnvVariable();

        if ($envVariable) {
            return env($envVariable);
        }

        return null;
    }
}
