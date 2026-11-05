<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Console\Commands;

use EduardoRibeiroDev\FilamentLeaflet\Widgets\MapWidget;
use Filament\Support\Commands\Exceptions\FailureCommandOutput;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Commands\MakeWidgetCommand as BaseMakeWidgetCommand;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\TableWidget;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\select;

class MakeWidgetCommand extends BaseMakeWidgetCommand
{
    /**
     * @return array<InputOption>
     */
    protected function getOptions(): array
    {
        return [
            ...parent::getOptions(),
            new InputOption(
                name: 'map',
                shortcut: 'M',
                mode: InputOption::VALUE_NONE,
                description: 'Create a Leaflet map widget',
            ),
        ];
    }

    /**
     * Igual ao do Filament, mas com a flag --map e a opção "Leaflet map" no menu interativo.
     */
    protected function configureType(): void
    {
        $this->type = match (true) {
            boolval($this->option('map')) => MapWidget::class,
            boolval($this->option('chart')) => ChartWidget::class,
            boolval($this->option('stats-overview')) => StatsOverviewWidget::class,
            boolval($this->option('table')) => TableWidget::class,
            default => null,
        } ?? select(
            label: 'Which type of widget would you like to create?',
            options: [
                Widget::class => 'Custom',
                ChartWidget::class => 'Chart',
                StatsOverviewWidget::class => 'Stats overview',
                TableWidget::class => 'Table',
                MapWidget::class => 'Leaflet map',
            ],
            default: $this->input->isInteractive() ? null : Widget::class,
        );
    }

    /**
     * handle() chama este método logo depois de configurar o local do widget,
     * então é o ponto de entrada para gerar o nosso arquivo sem reescrever o fluxo todo.
     *
     * Para o tipo MapWidget nenhuma view é pedida (configureLocation() só pergunta
     * pela view quando o tipo é Widget), então createView() não faz nada.
     */
    protected function createCustomWidget(): void
    {
        if ($this->type !== MapWidget::class) {
            parent::createCustomWidget();

            return;
        }

        $path = (string) str("{$this->widgetsDirectory}\\{$this->fqnEnd}.php")
            ->replace('\\', '/')
            ->replace('//', '/');

        if (! $this->option('force') && $this->checkForCollision($path)) {
            throw new FailureCommandOutput;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->getMapWidgetContents($this->fqn));
    }

    protected function getMapWidgetContents(string $fqn): string
    {
        $stub = <<<'PHP'
<?php

namespace {{ namespace }};

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Marker;
use EduardoRibeiroDev\FilamentLeaflet\Widgets\MapWidget;

class {{ class }} extends MapWidget
{
    protected ?string $heading = 'My Locations';
    protected array|Closure|null $mapCenter = [-23.5505, -46.6333];
    protected int|Closure $defaultZoom = 12;
    protected int|Closure $mapHeight = 600;

    protected function getMarkers(): array
    {
        return [
            //
        ];
    }
}

PHP;

        return str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [(string) str($fqn)->beforeLast('\\'), class_basename($fqn)],
            $stub,
        );
    }
}
