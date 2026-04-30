<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Infolists;

use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasMapState;
use Filament\Infolists\Components\Entry;

class MapEntry extends Entry
{
    use HasMapState;
    protected string $view = 'filament-leaflet::infolists.map-entry';

    protected function setUp(): void
    {
        parent::setUp();
        $this->height(284);
        $this->recenterTimeout(5000);
        $this->state(function ($record) {
            if (!$record) return null;

            if ($this->storeAsJson) {
                return $record->{$this->getName()};
            }

            if (
                ($latitude = $record->{$this->latitudeFieldName} ?? null) &&
                ($longitude = $record->{$this->longitudeFieldName} ?? null)
            ) {
                return [
                    $this->latitudeFieldName => $latitude,
                    $this->longitudeFieldName => $longitude
                ];
            }

            return null;
        });
    }
}
