<?php

require __DIR__ . '/../vendor/autoload.php';

use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasMapConfig;
use Filament\Support\Concerns\EvaluatesClosures;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

$directory = sys_get_temp_dir() . '/leaflet-translations-' . bin2hex(random_bytes(6));
$filesystem = new Filesystem;
$filesystem->makeDirectory($directory);

function expectTranslation(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

try {
    $app = new Container;
    Container::setInstance($app);
    $app->instance('config', new Repository);
    Facade::setFacadeApplication($app);
    $loader = new FileLoader($filesystem, $directory);
    $loader->addNamespace('filament-leaflet', __DIR__ . '/../resources/lang');
    $translator = new Translator($loader, 'pt_BR');
    $translator->setFallback('en');
    $app->instance('translator', $translator);

    $map = new class {
        use HasMapConfig;
        use EvaluatesClosures;

        public function getId(): string { return 'translated-map'; }
    };
    $country = '88855331B58508533874502';
    $italy = '42024856B85240378613663';
    $portuguese = $map->getMapData();
    expectTranslation($portuguese['regionNames'][$country] === 'Brasil', 'The map must use the current request locale.');
    expectTranslation($portuguese['regionNames'][$italy] === 'Itália', 'Country names must use their ISO identity, not malformed source names.');
    expectTranslation(!isset($portuguese['regionNames']['56859067B53289335762080']), 'Untranslated municipalities must fall back to source names.');

    $translator->setLocale('fr');
    expectTranslation($map->getMapData()['regionNames'][$country] === 'Brésil', 'A locale change must refresh the dictionary on the same map instance.');
    $translator->setLocale('unknown-locale');
    expectTranslation($map->getMapData()['regionNames'] === [], 'An unsupported locale must use native names instead of a different language dictionary.');

    $overrides = $directory . '/vendor/filament-leaflet/pt_BR';
    $filesystem->makeDirectory($overrides, 0755, true);
    $filesystem->put($overrides . '/regions.php', "<?php return ['{$country}' => 'Nome personalizado'];");
    $loader = new FileLoader($filesystem, $directory);
    $loader->addNamespace('filament-leaflet', __DIR__ . '/../resources/lang');
    $translator = new Translator($loader, 'pt_BR');
    $app->instance('translator', $translator);
    Facade::clearResolvedInstance('translator');
    $custom = $map->getMapData()['regionNames'];
    expectTranslation($custom[$country] === 'Nome personalizado', 'Published Laravel translations must override bundled labels.');
    expectTranslation($custom[$italy] === 'Itália', 'Partial overrides must retain the other bundled names.');

    echo "Region locale and override checks passed.\n";
} finally {
    $filesystem->deleteDirectory($directory);
}
