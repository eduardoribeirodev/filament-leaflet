<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Shapes;

class CircleMarker extends Shape
{
    protected array $center;
    protected int $radius = 10;

    final public function __construct(float $latitude, float $longitude)
    {
        $this->center = [$latitude, $longitude];
    }

    public static function make(float $latitude, float $longitude): static
    {
        return new static($latitude, $longitude);
    }

    /**
     * Define o raio em Pixels
     */
    public function radius(int $pixels): static
    {
        $this->radius = $pixels;
        return $this;
    }

    public function getType(): string
    {
        return 'circleMarker';
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
        return count($this->center) === 2 && $this->radius > 0;
    }

    public function getCoordinates(): array
    {
        return $this->center;
    }
}