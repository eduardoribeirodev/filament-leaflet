<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Enums;

use Filament\Support\Contracts\HasLabel;

enum GeoSearchProvider: string implements HasLabel
{
    case Nominatim = 'nominatim';
    case GoogleMaps = 'google';
    case Mapbox = 'mapbox';
    case BingMaps = 'bing';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Nominatim => 'Nominatim (OpenStreetMap)',
            self::GoogleMaps => 'Google Maps',
            self::Mapbox => 'Mapbox',
            self::BingMaps => 'Bing Maps',
        };
    }

    public function getEndpoint(): string
    {
        return match ($this) {
            self::Nominatim => 'https://nominatim.openstreetmap.org/search',
            self::GoogleMaps => 'https://maps.googleapis.com/maps/api/geocode/json',
            self::Mapbox => 'https://api.mapbox.com/geocoding/v5/mapbox.places',
            self::BingMaps => 'http://dev.virtualearth.net/REST/v1/Locations',
        };
    }

    /**
     * Retorna se o provedor requer uma chave de API.
     */
    public function requiresApiKey(): bool
    {
        return match ($this) {
            self::Nominatim => false,
            self::GoogleMaps => true,
            self::Mapbox => true,
            self::BingMaps => true,
        };
    }

    /**
     * Retorna a variável de ambiente para a chave de API.
     */
    public function getApiKeyEnvVariable(): ?string
    {
        return match ($this) {
            self::Nominatim => null,
            self::GoogleMaps => 'GOOGLE_MAPS_API_KEY',
            self::Mapbox => 'MAPBOX_API_KEY',
            self::BingMaps => 'BING_MAPS_API_KEY',
        };
    }
}
