<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Markers;

use EduardoRibeiroDev\FilamentLeaflet\Enums\Color;
use EduardoRibeiroDev\FilamentLeaflet\Support\Layer;
use InvalidArgumentException;

class Marker extends Layer
{
    protected float $latitude;
    protected float $longitude;
    protected string $color = 'blue';
    protected bool $isDraggable = false;

    // Configurações de Ícone
    protected ?string $iconUrl = null;
    protected array $iconSize = [25, 41];

    final public function __construct(float $latitude, float $longitude)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    public static function make(float $latitude, float $longitude): static
    {
        return new static($latitude, $longitude);
    }

    /*
    |--------------------------------------------------------------------------
    | Layer Abstract Methods Implementation
    |--------------------------------------------------------------------------
    */

    public function getType(): string
    {
        return 'marker';
    }

    protected function getLayerData(): array
    {
        return [
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'color' => $this->color,
            'icon' => $this->iconUrl,
            'iconSize' => $this->iconSize,
            'draggable' => $this->isDraggable,
        ];
    }

    public function isValid(): bool
    {
        return $this->latitude >= -90 && $this->latitude <= 90 &&
            $this->longitude >= -180 && $this->longitude <= 180;
    }

    /*
    |--------------------------------------------------------------------------
    | Marker Specific Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Define a cor do marcador. Aceita string ou Enum.
     */
    public function color(string|Color $color): static
    {
        $this->color = $color instanceof Color
            ? $color->value
            : Color::from($color)->value;

        return $this;
    }

    public function icon(string $url, array $size = [25, 41]): static
    {
        $this->iconUrl = $url;
        $this->iconSize = $size;
        return $this;
    }

    public function draggable(bool $condition = true): static
    {
        $this->isDraggable = $condition;
        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Color Shortcuts
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

    /*
    |--------------------------------------------------------------------------
    | Utilities
    |--------------------------------------------------------------------------
    */

    public function getCoordinates(): array
    {
        return [$this->latitude, $this->longitude];
    }

    /**
     * Lança exceção se coordenadas forem inválidas.
     */
    public function validate(): static
    {
        if (!$this->isValid()) {
            throw new InvalidArgumentException(
                "Invalid coordinates: lat={$this->latitude}, lng={$this->longitude}"
            );
        }

        return $this;
    }

    /**
     * Calcula a distância Haversine até outro marcador (em KM).
     */
    public function distanceTo(Marker $target): float
    {
        $earthRadius = 6371;

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($target->latitude);
        $lonTo = deg2rad($target->longitude);

        $latDiff = $latTo - $latFrom;
        $lonDiff = $lonTo - $lonFrom;

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
            cos($latFrom) * cos($latTo) *
            sin($lonDiff / 2) * sin($lonDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}