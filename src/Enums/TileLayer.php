<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

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
}