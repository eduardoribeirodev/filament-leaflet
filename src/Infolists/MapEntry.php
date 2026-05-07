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
        $this->state(fn($record, $component) => $record?->getAttribute($component->getName()));
    }
}
