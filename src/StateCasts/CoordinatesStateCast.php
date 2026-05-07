<?php

namespace EduardoRibeiroDev\FilamentLeaflet\StateCasts;

use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\Coordinate;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

class CoordinatesStateCast implements StateCast
{
    public function get(mixed $state): mixed
    {
        return is_array($state) ? Coordinate::fromArray($state) : $state;
    }

    public function set(mixed $state): mixed
    {
        return $state instanceof Coordinate ? $state->toArray() : $state;
    }
}