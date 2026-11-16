<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Fields;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasMapState;
use EduardoRibeiroDev\FilamentLeaflet\StateCasts\CoordinateStateCast;
use Filament\Forms\Components\Field;
use Illuminate\Database\Eloquent\Model;

class MapPicker extends Field
{
    use HasMapState {
        getMapFieldData as getParentMapFieldData;
    }

    protected string $view = 'filament-leaflet::fields.map-picker';

    protected bool|Closure $followMarker = false;

    public function followMarker(bool|Closure $enabled = true): static
    {
        $this->followMarker = $enabled;
        return $this;
    }

    public function getFollowMarker(): bool
    {
        return $this->evaluate($this->followMarker);
    }

    private function getMapFieldData(): array
    {
        return [
            'followMarker' => $this->getFollowMarker(),
            ...$this->getParentMapFieldData()
        ];
    }

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
