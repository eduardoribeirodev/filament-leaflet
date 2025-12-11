<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Shapes;

use EduardoRibeiroDev\FilamentLeaflet\Support\Layer;

class Polyline extends Layer
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
        return 'polyline';
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
        return count($this->points) >= 2;
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

    public function dashArray(string $pattern): static
    {
        $this->options['dashArray'] = $pattern;
        return $this;
    }

    /**
     * Linha tracejada
     */
    public function dashed(): static
    {
        return $this->dashArray('5, 10');
    }

    /**
     * Linha pontilhada
     */
    public function dotted(): static
    {
        return $this->dashArray('1, 5');
    }
}