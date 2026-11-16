<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Countries;

use EduardoRibeiroDev\FilamentLeaflet\Support\LocationIndex;
use InvalidArgumentException;

abstract class Country
{
    protected const ISO_CODE = '';

    final private function __construct() {}

    public static function isoCode(): string
    {
        return static::ISO_CODE;
    }

    public static function country(): string
    {
        $entry = LocationIndex::countries()[static::ISO_CODE] ?? null;
        if ($entry === null) {
            throw new InvalidArgumentException('Unknown country: ' . static::ISO_CODE);
        }

        return $entry['id'];
    }

    public static function state(string $code): string
    {
        return static::stateId($code);
    }

    private static function stateId(string $code): string
    {
        $entry = LocationIndex::states(static::ISO_CODE)[$code] ?? null;
        if (($entry['featureId'] ?? null) === null) {
            throw new InvalidArgumentException('Unknown state for ' . static::ISO_CODE . ': ' . $code);
        }

        return $entry['featureId'];
    }

    public static function city(string $state, string $name): string
    {
        static::stateId($state);

        return LocationIndex::cityId(static::ISO_CODE, $state, $name);
    }

    /**
     * @param array<string, int|float|null> $values
     * @return array<string, int|float|null>
     */
    public static function states(array $values): array
    {
        $resolved = [];
        foreach ($values as $code => $value) {
            $resolved[static::stateId((string) $code)] = $value;
        }

        return $resolved;
    }

    /**
     * @param array<string, int|float|null> $values
     * @return array<string, int|float|null>
     */
    public static function cities(string $state, array $values): array
    {
        static::stateId($state);
        $resolved = [];
        foreach ($values as $name => $value) {
            $resolved[LocationIndex::cityId(static::ISO_CODE, $state, (string) $name)] = $value;
        }

        return $resolved;
    }
}
