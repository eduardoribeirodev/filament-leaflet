<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Shapes;

class Rectangle extends Shape
{
    protected array $bounds;

    /**
     * @param array $corner1 Coordenada [lat, lng] do primeiro canto
     * @param array $corner2 Coordenada [lat, lng] do canto oposto
     */
    final public function __construct(array $corner1, array $corner2)
    {
        $this->bounds = [$corner1, $corner2];
    }

    public static function make(array $corner1, array $corner2): static
    {
        return new static($corner1, $corner2);
    }
    
    /**
     * Construtor alternativo usando coordenadas soltas.
     */
    public static function makeFromCoordinates(float $lat1, float $lng1, float $lat2, float $lng2): static
    {
        return new static([$lat1, $lng1], [$lat2, $lng2]);
    }

    public function getType(): string
    {
        return 'rectangle';
    }

    protected function getLayerData(): array
    {
        return [
            'bounds' => $this->bounds,
            'options' => $this->getShapeOptions(),
        ];
    }

    public function isValid(): bool
    {
        return count($this->bounds) === 2 
            && count($this->bounds[0]) === 2 
            && count($this->bounds[1]) === 2;
    }

    public function getCoordinates(): array
    {
        // Calcula o centro do retângulo
        $lat1 = $this->bounds[0][0];
        $lng1 = $this->bounds[0][1];
        $lat2 = $this->bounds[1][0];
        $lng2 = $this->bounds[1][1];

        return [
            ($lat1 + $lat2) / 2,
            ($lng1 + $lng2) / 2,
        ];
    }
}