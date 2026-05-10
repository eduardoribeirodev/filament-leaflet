<?php

namespace EduardoRibeiroDev\FilamentLeaflet\StateCasts;

use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\GeoSearchResult;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

class GeoSearchResultStateCast implements StateCast
{
    public function get(mixed $state): mixed
    {
        return is_array($state) ? GeoSearchResult::fromArray($state) : $state;
    }

    public function set(mixed $state): mixed
    {
        return $state instanceof GeoSearchResult ? $state->toArray() : $state;
    }
}
