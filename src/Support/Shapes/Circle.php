<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Shapes;

use EduardoRibeiroDev\FilamentLeaflet\Support\Layer;

class Circle extends Layer
{
    protected array $center; // [lat, lng]
    protected float $radius = 50000;
    protected array $options = [];

    final public function __construct(float $latitude, float $longitude)
    {
        $this->center = [$latitude, $longitude];
    }

    public static function make(float $latitude, float $longitude): static
    {
        return new static($latitude, $longitude);
    }

    /**
     * Define o raio diretamente em Metros (padrão do Leaflet).
     */
    public function radius(float $meters): static
    {
        $this->radius = $meters;
        return $this;
    }

    /**
     * Alias explícito para metros.
     */
    public function radiusInMeters(float $meters): static
    {
        return $this->radius($meters);
    }

    /**
     * Converte Quilômetros para Metros.
     */
    public function radiusInKilometers(float $km): static
    {
        return $this->radius($km * 1000);
    }

    /**
     * Converte Milhas para Metros.
     * 1 Milha ≈ 1609.344 Metros
     */
    public function radiusInMiles(float $miles): static
    {
        return $this->radius($miles * 1609.344);
    }

    /**
     * Converte Pés (Feet) para Metros.
     * 1 Pé = 0.3048 Metros
     */
    public function radiusInFeet(float $feet): static
    {
        return $this->radius($feet * 0.3048);
    }

    public function getType(): string
    {
        return 'circle';
    }

    protected function getLayerData(): array
    {
        return [
            'center' => $this->center,
            'radius' => $this->radius,
            'options' => $this->options,
        ];
    }

    public function isValid(): bool
    {
        return count($this->center) === 2 &&
            $this->center[0] >= -90 && $this->center[0] <= 90 &&
            $this->center[1] >= -180 && $this->center[1] <= 180 &&
            $this->radius > 0;
    }

    /**
     * Define opções de estilo (color, fillColor, weight, etc)
     */
    public function options(array $options): static
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    public function color(string $color): static
    {
        $this->options['color'] = $color;
        return $this;
    }

    public function fillColor(string $color): static
    {
        $this->options['fillColor'] = $color;
        return $this;
    }

    public function weight(int $weight): static
    {
        $this->options['weight'] = $weight;
        return $this;
    }

    public function opacity(float $opacity): static
    {
        $this->options['opacity'] = $opacity;
        return $this;
    }

    public function fillOpacity(float $opacity): static
    {
        $this->options['fillOpacity'] = $opacity;
        return $this;
    }
}