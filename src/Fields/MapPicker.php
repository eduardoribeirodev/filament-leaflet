<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Fields;

use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasMapState;
use Filament\Forms\Components\Field;

class MapPicker extends Field
{
    use HasMapState;
    protected string $view = 'filament-leaflet::fields.map-picker';

    public function isDehydrated(): bool
    {
        return false;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->height(284);
        $this->saveRelationshipsUsing(function($record, $state) {
            if ($this->storeAsJson) {
                $record->{$this->getName()} = [
                    $this->latitudeFieldName => $state[$this->latitudeFieldName],
                    $this->longitudeFieldName => $state[$this->longitudeFieldName]
                ];
            } else {
                $record->{$this->latitudeFieldName} = $state[$this->latitudeFieldName];
                $record->{$this->longitudeFieldName} = $state[$this->longitudeFieldName];
            }

            $record->save();
        });
        $this->afterStateHydrated(function ($record) {
            if (!$record) return;

            if ($this->storeAsJson) {
                $this->state($record->{$this->getName()});
            } else {
                $this->state([
                    $this->latitudeFieldName => $record->{$this->latitudeFieldName},
                    $this->longitudeFieldName => $record->{$this->longitudeFieldName}
                ]);
            }
        });
    }
}
