<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Shapes;

class Polyline extends Shape
{
    protected array $points = [];

    final public function __construct(array $points = [])
    {
        parent::__construct();
        $this->points = $points;
        
        $this->option('fill', false);
    }

    public static function make(array $points = []): static
    {
        return new static($points);
    }

    public function addPoint(float $latitude, float $longitude): static
    {
        $this->points[] = [$latitude, $longitude];
        return $this;
    }

    /**
     * Define a suavização da linha (smoothFactor).
     */
    public function smoothFactor(float $factor): static
    {
        return $this->option('smoothFactor', $factor);
    }

    public function getType(): string
    {
        return 'polyline';
    }

    protected function getLayerData(): array
    {
        return [
            'points' => $this->points,
            'options' => $this->getShapeOptions(),
        ];
    }

    public function isValid(): bool
    {
        // Uma linha precisa de pelo menos 2 pontos
        return count($this->points) >= 2;
    }
}