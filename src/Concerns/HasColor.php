<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Concerns;

use EduardoRibeiroDev\FilamentLeaflet\Enums\Color;

trait HasColor
{
    protected ?string $color = null;

    public function color(null|string|Color $color): static
    {
        if ($color === null) {
            $this->color = null;
        } else {
            $this->color = $color instanceof Color
                ? $color->value
                : Color::from($color)->value;
        }

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Convenience Methods
    |--------------------------------------------------------------------------
    */

    public function blue(): static
    {
        return $this->color(Color::Blue);
    }

    public function red(): static
    {
        return $this->color(Color::Red);
    }

    public function green(): static
    {
        return $this->color(Color::Green);
    }

    public function orange(): static
    {
        return $this->color(Color::Orange);
    }

    public function yellow(): static
    {
        return $this->color(Color::Yellow);
    }

    public function violet(): static
    {
        return $this->color(Color::Violet);
    }

    public function grey(): static
    {
        return $this->color(Color::Grey);
    }

    public function black(): static
    {
        return $this->color(Color::Black);
    }

    public function gold(): static
    {
        return $this->color(Color::Gold);
    }

    public function randomColor(): static
    {
        $colors = Color::cases();
        $randomColor = $colors[array_rand($colors)];
        return $this->color($randomColor);
    }

    /*
    |--------------------------------------------------------------------------
    | Getters
    |--------------------------------------------------------------------------
    */

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function getHexColor(): ?string
    {
        if (!$this->color) return null;
        return Color::from($this->color)->hex();
    }
}
