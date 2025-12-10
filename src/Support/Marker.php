<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support;

use EduardoRibeiroDev\FilamentLeaflet\Enums\MarkerColor;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;

class Marker implements Arrayable, Jsonable
{
    use Conditionable;
    use Macroable;

    protected float $latitude;
    protected float $longitude;
    protected string $id;

    protected ?string $label = null;
    protected string $color = 'blue';
    protected ?string $group = null;
    protected bool $isDraggable = false;

    // Configurações de Ícone
    protected ?string $iconUrl = null;
    protected array $iconSize = [25, 41];

    // Configurações de Tooltip
    protected ?string $tooltipContent = null;
    protected bool $isTooltipPermanent = false;
    protected string $tooltipDirection = 'auto';
    protected array $tooltipOptions = [];

    // Configurações de Popup
    protected mixed $popupContent = null;
    protected array $popupData = [];
    protected array $popupOptions = [];

    // Eventos e Scripts
    public mixed $clickAction = null;
    protected ?string $onMouseOverScript = null;
    protected ?string $onMouseOutScript = null;

    final public function __construct(float $latitude, float $longitude)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->id = 'marker_' . md5($latitude . $longitude);
    }

    /**
     * Método estático de criação
     */
    public static function make(float $latitude, float $longitude): static
    {
        return new static($latitude, $longitude);
    }

    /*
    |--------------------------------------------------------------------------
    | Setters Fluentes
    |--------------------------------------------------------------------------
    */

    public function id(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Define a cor do marcador. Aceita string ou Enum.
     */
    public function color(string|MarkerColor $color): static
    {
        $this->color = $color instanceof MarkerColor
            ? $color->value
            : MarkerColor::from($color)->value;

        return $this;
    }

    public function icon(string $url, array $size = [25, 41]): static
    {
        $this->iconUrl = $url;
        $this->iconSize = $size;

        return $this;
    }

    public function group(string $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function draggable(bool $condition = true): static
    {
        $this->isDraggable = $condition;

        return $this;
    }

    /**
     * Configura o conteúdo do tooltip.
     */
    public function tooltipContent(string $content): static
    {
        $this->tooltipContent = $content;

        return $this;
    }

    /**
     * Define se o tooltip será permanente.
     */
    public function tooltipPermanent(bool $permanent = true): static
    {
        $this->isTooltipPermanent = $permanent;

        return $this;
    }

    /**
     * Define a direção do tooltip (ex: 'auto', 'top', 'bottom', 'left', 'right').
     */
    public function tooltipDirection(string $direction = 'auto'): static
    {
        $this->tooltipDirection = $direction;

        return $this;
    }

    /**
     * Define opções adicionais para o tooltip.
     */
    public function tooltipOptions(array $options): static
    {
        $this->tooltipOptions = $options;

        return $this;
    }

    /**
     * Configura o conteúdo do popup.
     */
    public function popupContent(string $content): static
    {
        $this->popupContent = $content;

        return $this;
    }

    /**
     * Define opções adicionais para o popup.
     */
    public function popupOptions(array $options): static
    {
        $this->popupOptions = $options;

        return $this;
    }

    /**
     * Adiciona dados arbitrários para uso no frontend/popup.
     */
    public function popupData(array $data): static
    {
        $this->popupData = array_merge($this->popupData, $data);

        return $this;
    }

    /**
     * Método de conveniência para definir conteúdo e opções ao mesmo tempo.
     */
    public function popup(string $content, array $data = [], array $options = []): static
    {
        return $this->popupContent($content)->popupData($data)->popupOptions($options);
    }

    /*
    |--------------------------------------------------------------------------
    | Event Listeners
    |--------------------------------------------------------------------------
    */

    public function action(callable $callback): static
    {
        $this->clickAction = $callback;

        return $this;
    }

    public function onMouseOver(string $script): static
    {
        $this->onMouseOverScript = $script;

        return $this;
    }

    public function onMouseOut(string $script): static
    {
        $this->onMouseOutScript = $script;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Getters & State
    |--------------------------------------------------------------------------
    */

    public function getId(): string
    {
        return $this->id;
    }

    public function getCoordinates(): array
    {
        return [$this->latitude, $this->longitude];
    }

    /*
    |--------------------------------------------------------------------------
    | Utilities & Validation
    |--------------------------------------------------------------------------
    */

    /**
     * Métodos auxiliares de cores
     */
    public function blue(): static
    {
        return $this->color(MarkerColor::Blue);
    }

    public function red(): static
    {
        return $this->color(MarkerColor::Red);
    }

    public function green(): static
    {
        return $this->color(MarkerColor::Green);
    }

    public function orange(): static
    {
        return $this->color(MarkerColor::Orange);
    }

    public function yellow(): static
    {
        return $this->color(MarkerColor::Yellow);
    }

    public function violet(): static
    {
        return $this->color(MarkerColor::Violet);
    }

    public function grey(): static
    {
        return $this->color(MarkerColor::Grey);
    }

    public function black(): static
    {
        return $this->color(MarkerColor::Black);
    }

    public function gold(): static
    {
        return $this->color(MarkerColor::Gold);
    }

    public function isValid(): bool
    {
        return $this->latitude >= -90 && $this->latitude <= 90 &&
            $this->longitude >= -180 && $this->longitude <= 180;
    }

    /**
     * Lança exceção se coordenadas forem inválidas.
     * @throws InvalidArgumentException
     */
    public function validate(): static
    {
        if (! $this->isValid()) {
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

    /*
    |--------------------------------------------------------------------------
    | Serialization
    |--------------------------------------------------------------------------
    */

    public function toArray(): array
    {
        // Prepara o array base
        $data = [
            'id' => $this->id,
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'color' => $this->color,
            'icon' => $this->iconUrl,
            'iconSize' => $this->iconSize,
            'draggable' => $this->isDraggable,
            'title' => $this->label,
            'group' => $this->group,
            'popupContent' => $this->popupContent,
            'popupData' => $this->popupData,
            'popupOptions' => $this->popupOptions,
            'clickAction' => $this->clickAction,
            'onMouseOver' => $this->onMouseOverScript,
            'onMouseOut' => $this->onMouseOutScript,
        ];

        // Adiciona configurações de tooltip apenas se houver conteúdo
        if ($this->tooltipContent) {
            $data['tooltip'] = [
                'content' => $this->tooltipContent,
                'permanent' => $this->isTooltipPermanent,
                'direction' => $this->tooltipDirection,
                'options' => $this->tooltipOptions,
            ];
        }

        // Filtra valores nulos para manter o payload JSON limpo
        return array_filter($data, fn($value) => ! is_null($value));
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function __toString(): string
    {
        return sprintf(
            '%s (%s, %s)',
            $this->label ?? 'Marker',
            $this->latitude,
            $this->longitude
        );
    }
}
