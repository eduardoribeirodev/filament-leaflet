<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Concerns;

use Closure;
use Filament\Support\Colors\Color;

trait HasColor
{
    protected null|string|array $color = null;
    protected ?float $opacity = null;

    /**
     * Set the color of the layer.
     * @param null|string|Closure|array $color The color to set for the layer. This can be a string (e.g., "red", "#ff0000"), a Closure that returns a color, or an instance of the Color enum.
     * @return static The current instance with the updated color property.
     * @example $layer->color('red'); // Sets the color to red using a string.
     * @example $layer->color(Color::Blue); // Sets the color to blue using the Color enum.
     * @example $layer->color(fn() => '#008000'); // Sets the color to green using a Closure that returns a string.
     */
    public function color(null|string|Closure|array $color): static
    {
        $this->color = $this->evaluate($color);
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Convenience Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Set the color of the layer to blue. This method is a convenience method that allows you to quickly set the color of the layer to blue without having to specify the color as a string or using the Color enum directly. It internally calls the color() method with the appropriate value for blue, making it easier and more intuitive to set common colors for your map layers.
     * @return static The current instance with the color property set to blue.
     */
    public function blue(): static
    {
        return $this->color(Color::Blue);
    }

    /**
     * Set the color of the layer to red. This method is a convenience method that allows you to quickly set the color of the layer to red without having to specify the color as a string or using the Color enum directly. It internally calls the color() method with the appropriate value for red, making it easier and more intuitive to set common colors for your map layers.
     * @return static The current instance with the color property set to red.
     */
    public function red(): static
    {
        return $this->color(Color::Red);
    }

    /**
     * Set the color of the layer to green. This method is a convenience method that allows you to quickly set the color of the layer to green without having to specify the color as a string or using the Color enum directly. It internally calls the color() method with the appropriate value for green, making it easier and more intuitive to set common colors for your map layers.
     * @return static The current instance with the color property set to green.
     */
    public function green(): static
    {
        return $this->color(Color::Green);
    }

    /**
     * Set the color of the layer to orange. This method is a convenience method that allows you to quickly set the color of the layer to orange without having to specify the color as a string or using the Color enum directly. It internally calls the color() method with the appropriate value for orange, making it easier and more intuitive to set common colors for your map layers.
     * @return static The current instance with the color property set to orange.
     */
    public function orange(): static
    {
        return $this->color(Color::Orange);
    }

    /**
     * Set the color of the layer to yellow. This method is a convenience method that allows you to quickly set the color of the layer to yellow without having to specify the color as a string or using the Color enum directly. It internally calls the color() method with the appropriate value for yellow, making it easier and more intuitive to set common colors for your map layers.
     * @return static The current instance with the color property set to yellow.
     */
    public function yellow(): static
    {
        return $this->color(Color::Yellow);
    }

    /**
     * Set the color of the layer to violet. This method is a convenience method that allows you to quickly set the color of the layer to violet without having to specify the color as a string or using the Color enum directly. It internally calls the color() method with the appropriate value for violet, making it easier and more intuitive to set common colors for your map layers.
     * @return static The current instance with the color property set to violet.
     */
    public function violet(): static
    {
        return $this->color(Color::Violet);
    }

    /**
     * Set the color of the layer to gray. This method is a convenience method that allows you to quickly set the color of the layer to gray without having to specify the color as a string or using the Color enum directly. It internally calls the color() method with the appropriate value for gray, making it easier and more intuitive to set common colors for your map layers.
     * @return static The current instance with the color property set to gray.
     */
    public function gray(): static
    {
        return $this->color(Color::Gray);
    }

    /**
     * Set the color of the layer to black. This method is a convenience method that allows you to quickly set the color of the layer to black without having to specify the color as a string or using the Color enum directly. It internally calls the color() method with the appropriate value for black, making it easier and more intuitive to set common colors for your map layers.
     * @return static The current instance with the color property set to black.
     */
    public function black(): static
    {
        return $this->color('#000000');
    }

    /**
     * Set the color of the layer to gold. This method is a convenience method that allows you to quickly set the color of the layer to gold without having to specify the color as a string or using the Color enum directly. It internally calls the color() method with the appropriate value for gold, making it easier and more intuitive to set common colors for your map layers.
     * @return static The current instance with the color property set to gold.
     */
    public function gold(): static
    {
        return $this->color('#FFD700');
    }

    /**
     * Set the color of the layer to a random color. This method is a convenience method that allows you to quickly set the color of the layer to a random color from the predefined set of colors in the Color enum. It internally calls the color() method with a randomly selected color value, making it easy to assign a random color to your map layers for visual differentiation.
     * @return static The current instance with the color property set to a random color.
     */
    public function randomColor(): static
    {
        $rgb = [random_int(0, 255), random_int(0, 255), random_int(0, 255)];
        $color = "rgb(" . join(', ', $rgb) . ")";
        return $this->color($color);
    }

    /**
     * Set the opacity of the layer. The $opacity parameter can be a float value between 0 and 1, or a Closure that returns a float value. This method allows you to specify the opacity of the layer, which determines how transparent or opaque the layer appears on the map. An opacity of 0 means the layer is fully transparent, while an opacity of 1 means it is fully opaque. By accepting a Closure, this method also provides flexibility in dynamically calculating the opacity based on certain conditions or data.
     * @param Closure|float $opacity The opacity to set for the layer. This can be a float value between 0 and 1, or a Closure that returns a float value.
     * @return static The current instance with the updated opacity property.
     * @example $layer->opacity(0.5); // Sets the opacity to 50% using a float value.
     * @example $layer->opacity(fn() => 0.75); // Sets the opacity to 75% using a Closure that returns a float value.
     */
    public function opacity(null|Closure|float $opacity)
    {
        $this->opacity = $this->evaluate($opacity);
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Getters
    |--------------------------------------------------------------------------
    */

    public function getColor(): string|array
    {
        return $this->color ?? $this->getDefaultColor();
    }

    public function getRgbColor(?int $tone): string
    {
        $color = $this->getColor();

        if (is_array($color)) {
            $color = $tone !== null && isset($color[$tone])
                ? $color[$tone]
                : array_first($color);
        }

        return Color::convertToRgb($color);
    }

    public function getDefaultColor(): string|array
    {
        return Color::Blue;
    }

    public function getOpacity(): float
    {
        return $this->opacity ?? $this->getDefaultOpacity();
    }

    public function getDefaultOpacity(): float
    {
        return 1;
    }
}
