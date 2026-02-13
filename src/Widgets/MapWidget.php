<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Widgets;

use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasMapConfig;
use EduardoRibeiroDev\FilamentLeaflet\Enums\Color;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\Hidden;
use Filament\Widgets\Widget;
use Exception;

abstract class MapWidget extends Widget implements HasSchemas, HasActions
{
    use InteractsWithSchemas;
    use InteractsWithActions;
    use HasMapConfig;

    // Configurações do widget
    protected static bool $isLazy = false;
    protected string $view = 'filament-leaflet::widgets.map-widget';
    protected ?string $heading = null;

    // Configurações dos marcadores
    protected ?string $markerModel = null;
    protected ?string $markerResource = null;
    protected string $latitudeColumnName = 'latitude';
    protected string $longitudeColumnName = 'longitude';
    protected ?string $jsonCoordinatesColumnName = null;
    protected int $formColumns = 2;

    /**
     * Retorna o título do widget
     */
    public function getHeading(): string
    {
        return $this->heading ?? __('Map');
    }

    // === CREATE ACTION & FORM ===

    public function handleMapClick(float $latitude, float $longitude): void
    {
        if ($this->markerModel) {
            $this->mountAction('createMarker', [
                $this->getLatitudeColumnName() => $latitude,
                $this->getLongitudeColumnName() => $longitude
            ]);
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

            Select::make('color')
                ->translateLabel()
                ->native(false)
                ->options(Color::class),

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
     * Garante que o formulário possua os campos de coordenadas.
     */
    private function ensureFormHasCoordinateFields(Schema &$form): void
    {
        $hasLat = $form->getComponent($this->getLatitudeColumnName());
        $hasLng = $form->getComponent($this->getLongitudeColumnName());

        if ($hasLat && $hasLng) {
            return;
        }

        $components = $form->getComponents();

        if (!$hasLat) {
            $components[] = Hidden::make($this->getLatitudeColumnName());
        }

        if (!$hasLng) {
            $components[] = Hidden::make($this->getLongitudeColumnName());
        }

        $form->schema($components);
    }

    /**
     * Retorna a Action de criação de marker.
     */
    public function createMarkerAction(): Action
    {
        return CreateAction::make('createMarker')
            ->model(self::getMarkerModel())
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
                    $this->dispatch('marker-created');
                    $this->afterMarkerCreated($newRecord);
                } catch (Exception $e) {
                    throw new Exception('Error on creating Marker: ' . $e->getMessage());
                }
            });
    }

    /**
     * Modifica os dados do formulário antes de criar o registro.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Se a configuração for para salvar em JSON, converte os campos planos para array
        if ($this->shouldSaveCoordinatesAsJson()) {
            $latCol = $this->getLatitudeColumnName();
            $lngCol = $this->getLongitudeColumnName();
            $jsonCol = $this->getJsonCoordinatesColumnName();

            $data[$jsonCol] = [
                'latitude' => $data[$latCol],
                'longitude' => $data[$lngCol]
            ];

            unset($data[$latCol], $data[$lngCol]);
        }

        return $data;
    }

    /**
     * Executa após a criação de um marker.
     */
    protected function afterMarkerCreated(Model $record): void {}

    // === HELPERS ===

    protected function getMarkerModel(): ?string
    {
        return $this->markerModel;
    }

    protected function getMarkerResource(): ?string
    {
        return $this->markerResource;
    }

    protected function getFormColumns(): int
    {
        return $this->formColumns;
    }

    protected function shouldSaveCoordinatesAsJson(): bool
    {
        return !is_null($this->getJsonCoordinatesColumnName());
    }

    protected function getLatitudeColumnName(): string
    {
        return $this->latitudeColumnName;
    }

    protected function getLongitudeColumnName(): string
    {
        return $this->longitudeColumnName;
    }

    protected function getJsonCoordinatesColumnName(): ?string
    {
        return $this->jsonCoordinatesColumnName;
    }
}
