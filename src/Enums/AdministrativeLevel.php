<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Enums;

use InvalidArgumentException;

enum AdministrativeLevel: int
{
    case Auto = -1;
    case Country = 0;
    case State = 1;
    case Municipality = 2;

    public function filename(?string $state = null): string
    {
        return match ($this) {
            self::Auto => throw new InvalidArgumentException('Auto is a display strategy and must be resolved to a concrete administrative level before selecting a file.'),
            self::Country => 'adm0.geojson',
            self::State => 'adm1.geojson',
            self::Municipality => $state !== null
                ? "{$state}/adm2.geojson"
                : throw new InvalidArgumentException('A state is required for municipality boundaries.'),
        };
    }
}
