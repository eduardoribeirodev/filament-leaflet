<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Enums;

use Filament\Support\Contracts\HasLabel;

enum TileLayer: string implements HasLabel
{
    // OpenStreetMap
    case OpenStreetMap = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';

    // Google Maps
    case GoogleStreets = 'http://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}';
    case GoogleSatellite = 'http://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}';
    case GoogleHybrid = 'http://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}';
    case GoogleTerrain = 'http://mt1.google.com/vt/lyrs=p&x={x}&y={y}&z={z}';

    // Esri / ArcGIS
    case EsriWorldImagery = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
    case EsriWorldStreetMap = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}';
    case EsriNatGeo = 'https://server.arcgisonline.com/ArcGIS/rest/services/NatGeo_World_Map/MapServer/tile/{z}/{y}/{x}';

    // CartoDB
    case CartoPositron = 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';
    case CartoDarkMatter = 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png';

    // Mapbox (placeholder values - use getUrl() method for proper URLs with env variables)
    case MapboxStreets = 'mapbox://styles/mapbox/streets-v11';
    case MapboxOutdoors = 'mapbox://styles/mapbox/outdoors-v12';
    case MapboxLight = 'mapbox://styles/mapbox/light-v11';
    case MapboxDark = 'mapbox://styles/mapbox/dark-v11';
    case MapboxSatellite = 'mapbox://styles/mapbox/satellite-v9';
    case MapboxSatelliteStreets = 'mapbox://styles/mapbox/satellite-streets-v12';
    case MapboxNavigationDay = 'mapbox://styles/mapbox/navigation-day-v1';
    case MapboxNavigationNight = 'mapbox://styles/mapbox/navigation-night-v1';

    public function getLabel(): ?string
    {
        $name = preg_replace('/(?<!^)[A-Z]/', ' $0', $this->name);
        return __($name);
    }

    public function getAttribution(): string
    {
        return match ($this) {
            self::OpenStreetMap => '&copy; OpenStreetMap contributors',

            self::GoogleStreets,
            self::GoogleSatellite,
            self::GoogleHybrid,
            self::GoogleTerrain => '&copy; Google Maps',

            self::EsriWorldImagery,
            self::EsriWorldStreetMap,
            self::EsriNatGeo => 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',

            self::CartoPositron,
            self::CartoDarkMatter => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',

            self::MapboxStreets,
            self::MapboxDark,
            self::MapboxNavigationDay,
            self::MapboxNavigationNight,
            self::MapboxOutdoors,
            self::MapboxLight,
            self::MapboxSatelliteStreets,
            self::MapboxSatellite => '&copy; <a href="https://www.mapbox.com/about/maps/">Mapbox</a>',
        };
    }

    public static function googleLayers(): array
    {
        return [
            self::GoogleStreets,
            self::GoogleSatellite,
            self::GoogleHybrid,
            self::GoogleTerrain,
        ];
    }

    public static function esriLayers(): array
    {
        return [
            self::EsriWorldImagery,
            self::EsriWorldStreetMap,
            self::EsriNatGeo,
        ];
    }

    public static function cartoLayers(): array
    {
        return [
            self::CartoPositron,
            self::CartoDarkMatter,
        ];
    }

    public static function mapboxLayers(): array
    {
        return [
            self::MapboxStreets,
            self::MapboxDark,
            self::MapboxSatellite,
            self::MapboxNavigationDay,
            self::MapboxNavigationNight,
            self::MapboxOutdoors,
            self::MapboxLight,
            self::MapboxSatelliteStreets,
        ];
    }

    public function getUrl(): string
    {
        if (in_array($this, self::mapboxLayers(), true)) {
            $accessToken = config('services.mapbox.token');
            $tileSize = config('services.mapbox.tile_size', 512);

            if (!$accessToken) {
                throw new \InvalidArgumentException('MAPBOX_ACCESS_TOKEN environment variable is required for Mapbox tile layers.');
            }

            $styleId = match ($this) {
                self::MapboxStreets => 'streets-v11',
                self::MapboxDark => 'dark-v11',
                self::MapboxSatellite => 'satellite-v9',
                self::MapboxNavigationDay => 'navigation-day-v1',
                self::MapboxNavigationNight => 'navigation-night-v1',
                self::MapboxOutdoors => 'outdoors-v12',
                self::MapboxLight => 'light-v11',
                self::MapboxSatelliteStreets => 'satellite-streets-v12',
                default => 'streets-v11',
            };

            return "https://api.mapbox.com/styles/v1/mapbox/{$styleId}/tiles/{$tileSize}/{z}/{x}/{y}?access_token={$accessToken}";
        }

        return $this->value;
    }
}
