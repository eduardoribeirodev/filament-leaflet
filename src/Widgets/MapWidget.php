<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Widgets;

use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasMapConfig;
use EduardoRibeiroDev\FilamentLeaflet\Fields\MapPicker;
use EduardoRibeiroDev\FilamentLeaflet\Layers\BaseLayer;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\Coordinate;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Filament\Widgets\Widget;
use Exception;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Illuminate\Contracts\View\View;

abstract class MapWidget extends Widget implements HasSchemas, HasActions
{
    use InteractsWithSchemas;
    use InteractsWithActions;
    use HasMapConfig {
        handleLayerClick as private handleMapLayerClick;
    }

    // Configurações do widget
    protected string $view = 'filament-leaflet::widgets.map-widget';
    protected ?string $heading = null;

    // Configurações dos marcadores
    protected ?string $markerModel = null;
    protected ?string $markerResource = null;
    protected ?string $coordinatesColumnName = null;
    protected int $formColumns = 2;
    protected ?string $markerClickAction = 'edit';

    /**
     * Retorna o título do widget
     */
    public function getHeading(): ?string
    {
        return $this->heading;
    }

    // === CREATE ACTION & FORM ===

    public function handleMapClick(float $latitude, float $longitude): void
    {
        if ($this->getMarkerModel()) {
            $this->mountAction('createMarker', [
                $this->getCoordinatesColumnName() => new Coordinate($latitude, $longitude)
            ]);
        }
    }

    public function handleLayerClick(string|BaseLayer $layerId): void
    {
        $layer = $this->getLayerById($layerId);
        $this->handleMapLayerClick($layer);

        if (!$this->getMarkerModel()) {
            return;
        }

        if (($record = $layer->getRecord())) {
            $action = match ($this->markerClickAction) {
                'view' => 'viewMarker',
                'edit' => 'editMarker',
                'delete' => 'deleteMarker',
                default => throw new Exception('Invalid markerClickAction configuration: ' . $this->markerClickAction),
            };


            $this->mountAction($action, compact('record'));
        }
    }

    /**
     * Define os componentes do formulário de criação.
     */
    protected function getFormComponents(): array
    {
        return [
            TextInput::make('name')
                ->translateLabel()
                ->required()
                ->maxLength(255),

            ColorPicker::make('color')
                ->translateLabel(),

            Textarea::make('description')
                ->translateLabel()
                ->maxLength(1000)
                ->columnSpanFull(),
        ];
    }

    /**
     * Define o schema do formulário de criação.
     */
    protected function getFormSchema(Schema $schema): Schema
    {
        if ($this->getMarkerResource()) {
            $schema = $this->getMarkerResource()::form($schema);
        } else {
            $schema->schema($this->getFormComponents());
        }

        $this->ensureFormHasCoordinateFields($schema);

        return $schema->columns($this->getFormColumns());
    }

    /**
     * Garante que o formulário possua o campo de coordenadas.
     */
    private function ensureFormHasCoordinateFields(Schema &$form): void
    {
        $coordsColumn = $this->getCoordinatesColumnName();
        $hasCoords = $form->getComponent($coordsColumn);

        if ($hasCoords !== null) {
            return;
        }

        $components = $form->getComponents();
        
        $components[] = MapPicker::make($coordsColumn)
            ->dehydratedWhenHidden(true)
            ->hidden();

        $form->schema($components);
    }

    /**
     * Retorna a Action de criação de marker.
     */
    public function createMarkerAction(): Action
    {
        return CreateAction::make('createMarker')
            ->model($this->getMarkerModel())
            ->mountUsing(function (Schema $form, array $arguments) {
                $form->fill();

                $data = array_merge(
                    $form->getRawState(),
                    $arguments
                );

                $form->fill($data);
            })
            ->schema(fn(Schema $schema) => $this->getFormSchema($schema))
            ->mutateDataUsing(fn(array $data) => $this->mutateFormDataBeforeCreate($data))
            ->using(function (?string $model, array $data) {

                if ($model === null) {
                    throw new Exception('The $markerModel should be defined in the class ' . static::class);
                }

                try {
                    $newRecord = $model::create($data);
                    $this->refreshMap();
                    $this->dispatch('marker-updated');
                    $this->afterMarkerCreated($newRecord);
                } catch (Exception $e) {
                    throw new Exception('Error on creating Marker: ' . $e->getMessage());
                }
            });
    }

    public function viewMarkerAction(): ViewAction
    {
        return ViewAction::make('viewMarker')
            ->schema(fn(Schema $schema) => $this->getFormSchema($schema))
            ->record(fn($arguments) => $arguments['record']);
    }

    public function editMarkerAction(): EditAction
    {
        return EditAction::make('editMarker')
            ->record(fn($arguments) => $arguments['record'])
            ->schema(fn(Schema $schema) => $this->getFormSchema($schema))
            ->mutateDataUsing(fn(array $data) => $this->mutateFormDataBeforeCreate($data))
            ->using(function (Model $record, array $data) {
                $record->update($data);
                $this->refreshMap();
                $this->dispatch('marker-updated');
            });
    }

    public function deleteMarkerAction(): DeleteAction
    {
        return DeleteAction::make('deleteMarker')
            ->record(fn($arguments) => $arguments['record'])
            ->using(function (Model $record) {
                $record->delete();
                $this->refreshMap();
                $this->dispatch('marker-updated');
            });
    }

    /**
     * Modifica os dados do formulário antes de criar o registro.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Coordinates are always saved to the single coordinates column
        return $data;
    }

    /**
     * Executa após a criação de um marker.
     */
    protected function afterMarkerCreated(Model $record): void {}

    // === HELPERS ===

    protected function getMarkerModel(): ?string
    {
        return $this->markerModel ?? ($this->markerResource ? $this->markerResource::getModel() : null);
    }

    protected function getMarkerResource(): ?string
    {
        return $this->markerResource;
    }

    protected function getFormColumns(): int
    {
        return $this->formColumns;
    }

    protected function getCoordinatesColumnName(): string
    {
        return $this->coordinatesColumnName ?? config('filament-leaflet.columns.coords');
    }

    /**
     * Atualiza o mapa toda vez que é renderizado
     */
    public function render(): View
    {
        $this->refreshMap();
        return parent::render();
    }
}
