<?php

namespace EduardoRibeiroDev\FilamentLeaflet;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;

class FilamentLeafletServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-leaflet')
            ->hasViews();
    }

    public function packageBooted(): void
    {
        // Asset Registration
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../resources/dist/leaflet/images' => public_path('vendor/filament-leaflet/images'),
                __DIR__ . '/../resources/js/maps' => public_path('vendor/filament-leaflet/maps')
            ], 'filament-leaflet');
        }
    }

    protected function getAssetPackageName(): ?string
    {
        return 'eduardoribeirodev/filament-leaflet';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            Css::make('leaflet', __DIR__ . '/../resources/dist/leaflet/leaflet.css'),
            Css::make('markercluster', __DIR__ . '/../resources/dist/leaflet/markercluster.css'),
            Css::make('fullscreen', __DIR__ . '/../resources/dist/leaflet/fullscreen.css'),
            Css::make('geosearch', __DIR__ . '/../resources/dist/leaflet/geosearch.css'),
            Css::make('draw', __DIR__ . '/../resources/dist/leaflet/draw.css'),

            Js::make('leaflet', __DIR__ . '/../resources/dist/leaflet/leaflet.js'),
            Js::make('markercluster', __DIR__ . '/../resources/dist/leaflet/markercluster.js'),
            Js::make('fullscreen', __DIR__ . '/../resources/dist/leaflet/fullscreen.js'),
            Js::make('geosearch', __DIR__ . '/../resources/dist/leaflet/geosearch.js'),
            Js::make('draw', __DIR__ . '/../resources/dist/leaflet/draw.js'),
        ];
    }
}
