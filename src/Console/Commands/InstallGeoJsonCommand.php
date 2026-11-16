<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

use function Laravel\Prompts\multiselect;

class InstallGeoJsonCommand extends Command
{
    protected $signature = 'filament-leaflet:install-geojson
                            {--all : Publish all bundled countries without prompting}';

    protected $description = 'Publish bundled GeoJSON files for selected countries.';

    public function handle(): int
    {
        $basePath = dirname(__DIR__, 3) . '/dist/geojson';

        try {
            $manifest = json_decode(File::get("$basePath/manifest.json"), true, 512, JSON_THROW_ON_ERROR);
            $geometry = json_decode(File::get("$basePath/geometry.json"), true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $exception) {
            $this->error('Could not read the bundled GeoJSON indexes: ' . $exception->getMessage());

            return self::FAILURE;
        }

        if (!is_array($manifest) || $manifest === [] || !is_array($geometry) || $geometry === []) {
            $this->error('No geographical data available in the bundled GeoJSON indexes.');

            return self::FAILURE;
        }

        $availableCountries = array_keys($manifest);
        $selectedCountries = $this->option('all') ? $availableCountries : multiselect(
            label: 'Which countries do you want to publish GeoJSON files for?',
            options: array_combine($availableCountries, $availableCountries),
            hint: 'Includes all available administrative levels for each selected country.',
            required: true,
        );

        $pathsByCountry = [];
        foreach (array_keys($geometry) as $path) {
            [$country] = explode('/', $path, 2);
            $pathsByCountry[$country][] = $path;
        }

        foreach ($selectedCountries as $country) {
            $paths = $pathsByCountry[$country] ?? [];
            if ($paths === []) {
                $this->error("No bundled GeoJSON files are indexed for $country.");

                return self::FAILURE;
            }

            $this->info("Publishing GeoJSON files for $country...");
            foreach ($paths as $path) {
                try {
                    $this->publishFile("$basePath/$path", public_path("vendor/filament-leaflet/geojson/$path"));
                } catch (Exception $exception) {
                    $this->error("Could not publish $path: {$exception->getMessage()}");

                    return self::FAILURE;
                }
            }
        }

        $this->info('All selected GeoJSON files published successfully.');

        return self::SUCCESS;
    }

    protected function publishFile(string $source, string $destination): void
    {
        File::ensureDirectoryExists(dirname($destination));
        if (!File::copy($source, $destination)) {
            throw new RuntimeException("Failed to copy to $destination.");
        }

        $base = substr($destination, 0, -strlen('.geojson'));
        foreach (["$base.display.geojson", "$base.original.geojson"] as $obsolete) {
            if (File::isFile($obsolete) && !File::delete($obsolete)) {
                throw new RuntimeException("Failed to remove obsolete file $obsolete.");
            }
        }
    }
}
