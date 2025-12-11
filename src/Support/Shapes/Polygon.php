<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Shapes;

use EduardoRibeiroDev\FilamentLeaflet\Support\Layer;

class Polygon extends Layer
{
    protected array $points; // [[lat, lng], [lat, lng], ...]
    protected array $options = [];

    final public function __construct(array $points)
    {
        $this->points = $points;
    }

    public static function make(array $points): static
    {
        return new static($points);
    }

    public function getType(): string
    {
        return 'polygon';
    }

    protected function getLayerData(): array
    {
        return [
            'points' => $this->points,
            'options' => $this->options,
        ];
    }

    public function isValid(): bool
    {
        return count($this->points) >= 3;
    }

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

    /**
     * Cria um triângulo
     */
    public static function triangle(array $point1, array $point2, array $point3): static
    {
        return new static([$point1, $point2, $point3]);
    }

    /**
     * Cria um hexágono regular a partir de um centro e raio
     */
    public static function hexagon(array $center, float $radius): static
    {
        $points = [];
        for ($i = 0; $i < 6; $i++) {
            $angle = deg2rad(60 * $i);
            $lat = $center[0] + ($radius * cos($angle));
            $lng = $center[1] + ($radius * sin($angle));
            $points[] = [$lat, $lng];
        }
        return new static($points);
    }
}