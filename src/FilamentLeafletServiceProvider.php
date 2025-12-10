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
            Js::make('leaflet', __DIR__ . '/../resources/dist/leaflet/leaflet.js'),
        ];
    }
}
