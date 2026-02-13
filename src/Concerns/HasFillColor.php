<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Concerns;

use EduardoRibeiroDev\FilamentLeaflet\Enums\Color;

trait HasFillColor
{
    protected ?string $fillColor = null; 

    public function fillColor(null|string|Color $color): static
    {
        if (is_null($color)) {
            $this->fillColor = null;
            return $this;
        }
        
        $this->fillColor = $color instanceof Color
            ? $color->value
            : Color::from($color)->value;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Convenience Methods
    |--------------------------------------------------------------------------
    */

    public function fillBlue(): static
    {
        return $this->fillColor(Color::Blue);
    }

    public function fillRed(): static
    {
        return $this->fillColor(Color::Red);
    }

    public function fillGreen(): static
    {
        return $this->fillColor(Color::Green);
    }

    public function fillOrange(): static
    {
        return $this->fillColor(Color::Orange);
    }

    public function fillYellow(): static
    {
        return $this->fillColor(Color::Yellow);
    }

    public function fillViolet(): static
    {
        return $this->fillColor(Color::Violet);
    }

    public function fillGrey(): static
    {
        return $this->fillColor(Color::Grey);
    }

    public function fillBlack(): static
    {
        return $this->fillColor(Color::Black);
    }

    public function fillGold(): static
    {
        return $this->fillColor(Color::Gold);
    }

    public function fillRandomColor(): static
    {
        $colors = Color::cases();
        $randomColor = $colors[array_rand($colors)];
        return $this->fillColor($randomColor);
    }

    /*
    |--------------------------------------------------------------------------
    | Getters
    |--------------------------------------------------------------------------
    */

    public function getFillColor(): ?string
    {
        return $this->fillColor;
    }

    public function getHexFillColor(): ?string
    {
        if (!$this->fillColor) return null;
        return Color::from($this->fillColor)->hex();
    }
}