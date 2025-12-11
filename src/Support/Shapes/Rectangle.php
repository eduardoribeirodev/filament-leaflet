<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Shapes;

use EduardoRibeiroDev\FilamentLeaflet\Support\Layer;

class Rectangle extends Layer
{
    protected array $bounds; // [[lat1, lng1], [lat2, lng2]]
    protected array $options = [];

    final public function __construct(array $bounds)
    {
        $this->bounds = $bounds;
    }

    public static function make(array $bounds): static
    {
        return new static($bounds);
    }

    public function getType(): string
    {
        return 'rectangle';
    }

    protected function getLayerData(): array
    {
        return [
            'bounds' => $this->bounds,
            'options' => $this->options,
        ];
    }

    public function isValid(): bool
    {
        return count($this->bounds) === 2 &&
            count($this->bounds[0]) === 2 &&
            count($this->bounds[1]) === 2;
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
}