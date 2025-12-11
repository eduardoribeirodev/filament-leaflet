<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;

/**
 * Classe base abstrata para todos os layers do Leaflet
 * Centraliza funcionalidades comuns: popup, tooltip, eventos, grupos
 */
abstract class Layer implements Arrayable, Jsonable
{
    use Conditionable;
    use Macroable;

    protected ?string $id = null;
    protected ?string $group = null;

    // Configurações de Tooltip
    protected array $tooltipData = [];

    // Configurações de Popup
    protected array $popupData = [];

    // Eventos e Scripts
    protected mixed $clickAction = null;
    protected ?string $onMouseOverScript = null;
    protected ?string $onMouseOutScript = null;

    public function __construct(?string $id = null)
    {
        $this->id = $id;
    }

    /*
    |--------------------------------------------------------------------------
    | Abstract Methods - Devem ser implementados nas subclasses
    |--------------------------------------------------------------------------
    */

    /**
     * Retorna o tipo de layer para o frontend (marker, circle, polygon, etc)
     */
    abstract public function getType(): string;

    /**
     * Retorna os dados específicos do layer para serialização
     */
    abstract protected function getLayerData(): array;

    /**
     * Valida se o layer está configurado corretamente
     */
    abstract public function isValid(): bool;

    /*
    |--------------------------------------------------------------------------
    | Tooltip Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Configura o conteúdo do tooltip.
     */
    public function tooltipContent(string $content): static
    {
        $this->tooltipData['content'] = $content;
        return $this;
    }

    /**
     * Define se o tooltip será permanente.
     */
    public function tooltipPermanent(bool $permanent = true): static
    {
        $this->tooltipOption('permanent', $permanent);
        return $this;
    }

    /**
     * Define a direção do tooltip (ex: 'auto', 'top', 'bottom', 'left', 'right').
     */
    public function tooltipDirection(string $direction = 'auto'): static
    {
        $this->tooltipOption('direction', $direction);
        return $this;
    }

    /**
     * Define uma opção adicional para o tooltip.
     */
    public function tooltipOption(string $option, mixed $value): static
    {
        $this->tooltipOptions([$option => $value]);
        return $this;
    }

    /**
     * Define opções adicionais para o tooltip.
     */
    public function tooltipOptions(array $options): static
    {
        $this->tooltipData['options'] = array_merge(
            $this->tooltipData['options'] ?? [],
            $options
        );
        return $this;
    }

    /**
     * Método de conveniência para configurar tooltip completo
     */
    public function tooltip(
        string $content,
        bool $permanent = false,
        string $direction = 'auto',
        array $options = []
    ): static {
        return $this
            ->tooltipContent($content)
            ->tooltipPermanent($permanent)
            ->tooltipDirection($direction)
            ->tooltipOptions($options);
    }

    /*
    |--------------------------------------------------------------------------
    | Popup Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Configura o título do popup.
     */
    public function popupTitle(string $title): static
    {
        $this->popupData['title'] = $title;
        return $this;
    }

    /**
     * Configura o conteúdo do popup.
     */
    public function popupContent(string $content): static
    {
        $this->popupData['content'] = $content;
        return $this;
    }

    /**
     * Define opções adicionais para o popup.
     */
    public function popupFields(array $fields): static
    {
        $this->popupData['fields'] = array_merge(
            $this->popupData['fields'] ?? [],
            $fields
        );
        return $this;
    }

    /**
     * Define uma opção adicional para o popup.
     */
    public function popupOption(string $option, mixed $value): static
    {
        $this->popupOptions([$option => $value]);
        return $this;
    }

    /**
     * Define opções adicionais para o popup.
     */
    public function popupOptions(array $options): static
    {
        $this->popupData['options'] = array_merge(
            $this->popupData['options'] ?? [],
            $options
        );
        return $this;
    }

    /**
     * Método de conveniência para definir popup completo
     */
    public function popup(
        string $content,
        array $fields = [],
        array $options = []
    ): static {
        return $this
            ->popupContent($content)
            ->popupFields($fields)
            ->popupOptions($options);
    }

    /**
     * Método de conveniência para definir popupTitle e tooltipContent
     */
    public function title(string $title)
    {
        return $this
            ->tooltipContent($title)
            ->popupTitle($title);
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

    public function onClick(callable $callback): static
    {
        return $this->action($callback);
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

    public function execClickAction()
    {
        if (!isset($this->clickAction)) return;

        call_user_func($this->clickAction);
    }

    /*
    |--------------------------------------------------------------------------
    | Group & Identity
    |--------------------------------------------------------------------------
    */

    public function id(string $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function group(string $group): static
    {
        $this->group = $group;
        return $this;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    /*
    |--------------------------------------------------------------------------
    | Serialization
    |--------------------------------------------------------------------------
    */

    /**
     * Retorna os dados comuns a todos os layers
     */
    protected function getBaseData(): array
    {
        $data = [
            'id' => $this->id,
            'type' => $this->getType(),
            'group' => $this->group,
            'clickAction' => isset($this->clickAction),
            'onMouseOver' => $this->onMouseOverScript,
            'onMouseOut' => $this->onMouseOutScript,
        ];

        if ($this->tooltipData) {
            $data['tooltip'] = $this->tooltipData;
        }

        if ($this->popupData) {
            $data['popup'] = $this->popupData;
        }

        return $data;
    }

    public function toArray(): array
    {
        // Mescla dados base com dados específicos do layer
        $data = array_merge(
            $this->getBaseData(),
            $this->getLayerData()
        );

        // Filtra valores nulos para manter o payload JSON limpo
        return array_filter($data, fn($value) => !is_null($value));
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function __toString(): string
    {
        return sprintf(
            '%s [%s]',
            class_basename($this),
            $this->id
        );
    }
}
