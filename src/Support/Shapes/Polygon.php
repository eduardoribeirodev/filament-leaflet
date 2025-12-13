<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Shapes;

class Polygon extends Shape
{
    protected array $points = [];

    /**
     * @param array $points Array de coordenadas ex: [[-15.0, -50.0], [-15.1, -50.1], ...]
     */
    final public function __construct(array $points = [])
    {
        parent::__construct();
        $this->points = $points;
    }

    public static function make(array $points = []): static
    {
        return new static($points);
    }

    /**
     * Adiciona um ponto (vértice) ao polígono.
     */
    public function addPoint(float $latitude, float $longitude): static
    {
        $this->points[] = [$latitude, $longitude];
        return $this;
    }

    public function getType(): string
    {
        return 'polygon';
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
        // Um polígono precisa de pelo menos 3 pontos para fechar uma área
        return count($this->points) >= 3;
    }
}