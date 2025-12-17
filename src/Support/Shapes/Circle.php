<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Shapes;

class Circle extends Shape
{
    protected array $center; // [lat, lng]
    protected float $radius = 50000;

    final public function __construct(float $latitude, float $longitude)
    {
        $this->center = [$latitude, $longitude];
    }

    public static function make(float $latitude, float $longitude): static
    {
        return new static($latitude, $longitude);
    }

    /*
    |--------------------------------------------------------------------------
    | Radius Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Define o raio diretamente em Metros (padrão do Leaflet).
     */
    public function radius(float $meters): static
    {
        $this->radius = $meters;
        return $this;
    }

    public function radiusInMeters(float $meters): static
    {
        return $this->radius($meters);
    }

    public function radiusInKilometers(float $km): static
    {
        return $this->radius($km * 1000);
    }

    public function radiusInMiles(float $miles): static
    {
        return $this->radius($miles * 1609.344);
    }

    public function radiusInFeet(float $feet): static
    {
        return $this->radius($feet * 0.3048);
    }

    /*
    |--------------------------------------------------------------------------
    | Layer Implementation
    |--------------------------------------------------------------------------
    */

    public function getType(): string
    {
        return 'circle';
    }

    protected function getLayerData(): array
    {
        return [
            'center' => $this->center,
            'radius' => $this->radius,
            'options' => $this->getShapeOptions(),
        ];
    }

    public function isValid(): bool
    {
        return count($this->center) === 2 &&
            $this->center[0] >= -90 && $this->center[0] <= 90 &&
            $this->center[1] >= -180 && $this->center[1] <= 180 &&
            $this->radius > 0;
    }

    public function getCoordinates(): array
    {
        return $this->center;
    }
}