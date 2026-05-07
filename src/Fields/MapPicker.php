<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Fields;

use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasMapState;
use EduardoRibeiroDev\FilamentLeaflet\StateCasts\CoordinatesStateCast;
use Filament\Forms\Components\Field;

class MapPicker extends Field
{
    use HasMapState;
    protected string $view = 'filament-leaflet::fields.map-picker';

    public function getDefaultStateCasts(): array
    {
        return [
            app(CoordinatesStateCast::class),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->height(284);
        $this->afterStateHydrated(
            fn($record, $component) => $component->state(
                $record?->getAttribute($component->getName())
            )
        );
    }
}
