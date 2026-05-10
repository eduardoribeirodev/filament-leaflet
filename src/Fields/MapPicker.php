<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Fields;

use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasMapState;
use EduardoRibeiroDev\FilamentLeaflet\StateCasts\CoordinateStateCast;
use Filament\Forms\Components\Field;
use Illuminate\Database\Eloquent\Model;

class MapPicker extends Field
{
    use HasMapState;
    protected string $view = 'filament-leaflet::fields.map-picker';

    public function getDefaultStateCasts(): array
    {
        return [
            app(CoordinateStateCast::class),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->height(284);
        $this->afterStateHydrated(function (?Model $record, self $component) {
            if ($record) {
                $component->state(
                    $record->getAttribute($component->getName())
                );
            }
        });
    }
}
