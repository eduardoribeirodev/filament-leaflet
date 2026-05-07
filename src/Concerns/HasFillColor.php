<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Concerns;

use Closure;
use Filament\Support\Colors\Color;

trait HasFillColor
{
    protected null|string|array $fillColor = null;
    protected ?float $fillOpacity = null;

    /**
     * Set the fill color of the layer.
     * @param null|string|Closure|array $color The fill color to set for the layer. This can be a string (e.g., "red", "#ff0000"), a Closure that returns a color, or an instance of the Color enum.
     * @return static The current instance with the updated fillColor property.
     * @example $layer->fillColor('red'); // Sets the fill color to red using a string.
     * @example $layer->fillColor(Color::Blue); // Sets the fill color to blue using the Color enum.
     * @example $layer->fillColor(fn() => '#008000'); // Sets the fill color to green using a Closure that returns a string.
     */
    public function fillColor(null|string|Closure|array $color): static
    {
        $this->fillColor = $this->evaluate($color);
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Convenience Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Set the fill color of the layer to blue. This method is a convenience method that allows you to quickly set the fill color of the layer to blue without having to specify the color as a string or using the Color enum directly. It internally calls the fillColor() method with the appropriate value for blue, making it easier and more intuitive to set common fill colors for your map layers.
     * @return static The current instance with the fillColor property set to blue.
     */
    public function fillBlue(): static
    {
        return $this->fillColor(Color::Blue);
    }

    /**
     * Set the fill color of the layer to red. This method is a convenience method that allows you to quickly set the fill color of the layer to red without having to specify the color as a string or using the Color enum directly. It internally calls the fillColor() method with the appropriate value for red, making it easier and more intuitive to set common fill colors for your map layers.
     * @return static The current instance with the fillColor property set to red.
     */
    public function fillRed(): static
    {
        return $this->fillColor(Color::Red);
    }

    /**
     * Set the fill color of the layer to green. This method is a convenience method that allows you to quickly set the fill color of the layer to green without having to specify the color as a string or using the Color enum directly. It internally calls the fillColor() method with the appropriate value for green, making it easier and more intuitive to set common fill colors for your map layers.
     * @return static The current instance with the fillColor property set to green.
     */
    public function fillGreen(): static
    {
        return $this->fillColor(Color::Green);
    }

    /**
     * Set the fill color of the layer to orange. This method is a convenience method that allows you to quickly set the fill color of the layer to orange without having to specify the color as a string or using the Color enum directly. It internally calls the fillColor() method with the appropriate value for orange, making it easier and more intuitive to set common fill colors for your map layers.
     * @return static The current instance with the fillColor property set to orange.
     */
    public function fillOrange(): static
    {
        return $this->fillColor(Color::Orange);
    }

    /**
     * Set the fill color of the layer to yellow. This method is a convenience method that allows you to quickly set the fill color of the layer to yellow without having to specify the color as a string or using the Color enum directly. It internally calls the fillColor() method with the appropriate value for yellow, making it easier and more intuitive to set common fill colors for your map layers.
     * @return static The current instance with the fillColor property set to yellow.
     */
    public function fillYellow(): static
    {
        return $this->fillColor(Color::Yellow);
    }

    /**
     * Set the fill color of the layer to violet. This method is a convenience method that allows you to quickly set the fill color of the layer to violet without having to specify the color as a string or using the Color enum directly. It internally calls the fillColor() method with the appropriate value for violet, making it easier and more intuitive to set common fill colors for your map layers.
     * @return static The current instance with the fillColor property set to violet.
     */
    public function fillViolet(): static
    {
        return $this->fillColor(Color::Violet);
    }

    /**
     * Set the fill color of the layer to gray. This method is a convenience method that allows you to quickly set the fill color of the layer to gray without having to specify the color as a string or using the Color enum directly. It internally calls the fillColor() method with the appropriate value for gray, making it easier and more intuitive to set common fill colors for your map layers.
     * @return static The current instance with the fillColor property set to gray.
     */
    public function fillGray(): static
    {
        return $this->fillColor(Color::Gray);
    }

    /**
     * Set the color of the layer to black. This method is a convenience method that allows you to quickly set the color of the layer to black without having to specify the color as a string or using the Color enum directly. It internally calls the color() method with the appropriate value for black, making it easier and more intuitive to set common colors for your map layers.
     * @return static The current instance with the color property set to black.
     */
    public function black(): static
    {
        return $this->fillColor('#000000');
    }

    /**
     * Set the color of the layer to gold. This method is a convenience method that allows you to quickly set the color of the layer to gold without having to specify the color as a string or using the Color enum directly. It internally calls the color() method with the appropriate value for gold, making it easier and more intuitive to set common colors for your map layers.
     * @return static The current instance with the color property set to gold.
     */
    public function gold(): static
    {
        return $this->fillColor('#FFD700');
    }

    /**
     * Set the fill color of the layer to a random color. This method is a convenience method that allows you to quickly set the fill color of the layer to a random color from the predefined set of colors in the Color enum. It internally calls the fillColor() method with a randomly selected color value, making it easy to assign a random fill color to your map layers for visual differentiation.
     * @return static The current instance with the fillColor property set to a random color.
     */
    public function randomFillColor(): static
    {
        $rgb = [random_int(0, 255), random_int(0, 255), random_int(0, 255)];
        $color = "rgb(" . join(', ', $rgb) . ")";
        return $this->fillColor($color);
    }

    /**
     * Set the fill opacity of the layer. This method allows you to set the opacity of the fill color for the layer. The opacity value should be a float between 0 and 1, where 0 is completely transparent and 1 is completely opaque. If a Closure is passed, it will be evaluated to determine the opacity value.
     * @param Closure|float $opacity The opacity value to set for the layer's fill color.
     * @return static The current instance with the updated fillOpacity property.
     */
    public function fillOpacity(null|Closure|float $opacity)
    {
        $this->fillOpacity = $this->evaluate($opacity);
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Getters
    |--------------------------------------------------------------------------
    */

    public function getFillColor(): string|array
    {
        return $this->fillColor ?? $this->getDefaultFillColor();
    }

    public function getRgbFillColor(?int $tone): string
    {
        $color = $this->getFillColor();

        if (is_array($color)) {
            $color = $tone !== null && isset($color[$tone])
                ? $color[$tone]
                : array_first($color);
        }

        return Color::convertToRgb($color);
    }

    public function getDefaultFillColor(): string|array
    {
        return Color::Blue;
    }

    public function getFillOpacity(): float
    {
        return $this->fillOpacity ?? $this->getDefaultFillOpacity();
    }

    public function getDefaultFillOpacity(): float
    {
        return 1;
    }
}
