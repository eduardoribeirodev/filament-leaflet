<?php

namespace EduardoRibeiroDev\FilamentLeaflet;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Js;
use Illuminate\Support\Facades\Lang;

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
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'filament-leaflet');

        $this->publishes([
            __DIR__ . '/../resources/lang' => $this->app->langPath('/vendor/filament-leaflet'),
        ], 'filament-leaflet-translations');

        FilamentAsset::registerScriptData([
            'translations' => Lang::get('filament-leaflet::filament-leaflet'),
            'language' => Lang::getLocale(),
        ]);

        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        $this->publishes([
            __DIR__ . '/../resources/dist/images' => public_path('vendor/filament-leaflet/images'),
            __DIR__ . '/../resources/json/maps' => public_path('vendor/filament-leaflet/maps')
        ], 'filament-leaflet');
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
            Css::make('leaflet', __DIR__ . '/../resources/dist/leaflet-map.css'),
            Js::make('leaflet-map', __DIR__ . '/../resources/dist/leaflet-map.umd.cjs'),
        ];
    }
}
