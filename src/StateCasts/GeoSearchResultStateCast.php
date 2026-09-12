<?php

namespace EduardoRibeiroDev\FilamentLeaflet\StateCasts;

use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\GeoSearchResult;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\Coordinate;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

class GeoSearchResultStateCast implements StateCast
{
    public function __construct(
        protected bool $textMode = false,
        protected bool $useShortLabels = false,
    ) {}

    public function get(mixed $state): mixed
    {
        if (! $this->textMode && is_string($state)) {
            $decoded = json_decode($state, true);

            if (is_array($decoded) && (array_key_exists('coordinate', $decoded) || array_key_exists('lat', $decoded))) {
                $state = $decoded;
            }
        }

        if (is_array($state)) {
            return array_key_exists('lat', $state) || array_is_list($state)
                ? Coordinate::fromArray($state)
                : GeoSearchResult::fromArray($state);
        }

        return $state;
    }

    public function set(mixed $state): mixed
    {
        if (is_array($state)) {
            $state = $this->get($state);
        }

        if ($state instanceof Coordinate) {
            return json_encode($state->toArray(), JSON_THROW_ON_ERROR);
        }

        if (! $state instanceof GeoSearchResult) {
            return $state;
        }

        return $this->textMode
            ? $state->{$this->useShortLabels ? 'name' : 'displayName'}
            : json_encode($state->toArray(), JSON_THROW_ON_ERROR);
    }
}
