<?php

require __DIR__ . '/../vendor/autoload.php';

use EduardoRibeiroDev\FilamentLeaflet\Console\Commands\MakeWidgetCommand;
use EduardoRibeiroDev\FilamentLeaflet\FilamentLeafletServiceProvider;
use EduardoRibeiroDev\FilamentLeaflet\Widgets\MapWidget;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Spatie\LaravelPackageTools\Package;
use Symfony\Component\Console\Tester\CommandTester;

class MapWidgetCommandFixture extends MakeWidgetCommand
{
    public string $outputDirectory;

    protected function configurePanel(string $question, ?string $initialQuestion = null): void
    {
        $this->panel = null;
    }

    protected function configureWidgetsLocation(): void
    {
        $this->widgetsNamespace = 'Generated\\Widgets';
        $this->widgetsDirectory = $this->outputDirectory;
    }
}

$directory = sys_get_temp_dir() . '/filament-leaflet-widget-' . bin2hex(random_bytes(6));
$filesystem = new Filesystem;
$filesystem->makeDirectory($directory);

try {
    $filesystem->put($directory . '/composer.json', json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]));
    $app = new Application($directory);
    $app->instance('config', new Repository);
    $app->instance('files', $filesystem);
    $app->instance('env', 'testing');
    Facade::setFacadeApplication($app);

    $package = new Package;
    (new FilamentLeafletServiceProvider($app))->configurePackage($package);
    if (!in_array(MakeWidgetCommand::class, $package->commands, true)) {
        throw new RuntimeException('The package must register the map widget command.');
    }

    $command = new MapWidgetCommandFixture;
    $command->outputDirectory = $directory . '/widgets';
    $command->setLaravel($app);
    if ($command->getDefinition()->getOption('map')->getShortcut() !== 'M') {
        throw new RuntimeException('The map option must support the -M shortcut.');
    }
    $tester = new CommandTester($command);
    $status = $tester->execute(['name' => 'VisitsMap', '--map' => true], ['interactive' => false]);
    if ($status !== 0) {
        throw new RuntimeException($tester->getDisplay());
    }

    $filename = $directory . '/widgets/VisitsMap.php';
    if (!$filesystem->exists($filename)) {
        throw new RuntimeException('The command must generate the requested widget.');
    }
    require $filename;
    $widget = new Generated\Widgets\VisitsMap;
    if (!$widget instanceof MapWidget || $widget->getHeading() !== 'My Locations') {
        throw new RuntimeException('The generated widget must extend MapWidget and load successfully.');
    }
    if ($filesystem->exists($directory . '/resources/views')) {
        throw new RuntimeException('Map widgets must use the package view.');
    }

    echo "Map widget generator checks passed.\n";
} finally {
    $filesystem->deleteDirectory($directory);
}
