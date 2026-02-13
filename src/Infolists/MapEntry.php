<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Infolists;

use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasMapState;
use Filament\Infolists\Components\Entry;

class MapEntry extends Entry
{
    use HasMapState;
    protected string $view = 'filament-leaflet::infolists.map-entry';

    public function getState(): mixed
    {
        $record = $this->getRecord();
        if (!$record) return null;

        if ($this->storeAsJson) {
            return $record->{$this->getName()};
        }

        return [
            $this->latitudeFieldName => $record->{$this->latitudeFieldName},
            $this->longitudeFieldName => $record->{$this->longitudeFieldName}
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->height(284);
        $this->recenterTimeout(3000);
    }
}
