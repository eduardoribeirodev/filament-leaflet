<?php

namespace EduardoRibeiroDev\FilamentLeaflet\ValueObjects;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

readonly class Coordinate implements Arrayable, Castable
{
    public const LATITUDE_KEY = 'lat';
    public const LONGITUDE_KEY = 'lng';

    public function __construct(
        public float $lat,
        public float $lng,
    ) {}

    public function toArray()
    {
        return [
            static::LATITUDE_KEY => $this->lat,
            static::LONGITUDE_KEY => $this->lng
        ];
    }

    public function toFlatArray(): array
    {
        return [$this->lat, $this->lng];
    }

    public static function fromArray(array $array): ?static
    {
        $lat = $array[static::LATITUDE_KEY] ?? $array[0] ?? null;
        $lng = $array[static::LONGITUDE_KEY] ?? $array[1] ?? null;

        if (!$lat || !$lng) return null;

        return new static($lat, $lng);
    }

    public static function fromObject(object $object): ?static
    {
        $lat = $object->{static::LATITUDE_KEY} ?? null;
        $lng = $object->{static::LONGITUDE_KEY} ?? null;

        if (!$lat || !$lng) return null;

        return new static($lat, $lng);
    }

    public static function from(array|object $coordinates): ?static
    {
        return match (true) {
            $coordinates instanceof static => $coordinates,
            is_array($coordinates) => static::fromArray($coordinates),
            is_object($coordinates) => static::fromObject($coordinates),
            default => null
        };
    }

    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            protected string $latColumn;
            protected string $lngColumn;

            public function __construct(?string $latColumn = null, ?string $lngColumn =  null)
            {
                $this->latColumn = $latColumn ?? config('filament-leaflet.columns.latitude', Coordinate::LATITUDE_KEY);
                $this->lngColumn = $lngColumn ?? config('filament-leaflet.columns.longitude', Coordinate::LONGITUDE_KEY);
            }

            public function get(Model $model, string $key, mixed $value, array $attributes)
            {
                $storeAsJson = array_key_exists($key, $attributes);

                if ($storeAsJson) {
                    $coords = json_decode($attributes[$key], true);
                } else {
                    $coords = $attributes;
                }

                $lat = $coords[$this->latColumn];
                $lng = $coords[$this->lngColumn];

                if (!$lat || !$lng) return null;

                return new Coordinate($lat, $lng);
            }

            public function set(Model $model, string $key, mixed $value, array $attributes)
            {
                if (is_array($value)) {
                    $value = Coordinate::fromArray($value);
                }

                if (!$value instanceof Coordinate) {
                    throw new InvalidArgumentException('Value must be an instance of Coordinate');
                }

                $coords = [
                    $this->latColumn => $value->lat,
                    $this->lngColumn => $value->lng,
                ];

                $storeAsJson = array_key_exists($key, $attributes);

                return $storeAsJson
                    ? [$key => json_encode($coords)]
                    : $coords;
            }
        };
    }

    public function distanceTo(self $other): float
    {
        $earthRadius = 6371; // Earth radius in kilometers

        $latFrom = deg2rad($this->lat);
        $lngFrom = deg2rad($this->lng);
        $latTo = deg2rad($other->lat);
        $lngTo = deg2rad($other->lng);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lngDelta / 2), 2)));

        return $angle * $earthRadius;
    }
}
