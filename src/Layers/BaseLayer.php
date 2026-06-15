<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Layers;

use Closure;
use DateTime;
use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasColor;
use EduardoRibeiroDev\FilamentLeaflet\LayerGroups\BaseLayerGroup;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\Coordinate;
use Filament\Support\Concerns\EvaluatesClosures;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;

abstract class BaseLayer implements Arrayable, Jsonable
{
    use Conditionable;
    use Macroable;
    use EvaluatesClosures;
    use HasColor;

    protected null|Closure|string $id = null;
    protected null|Closure|string|BaseLayerGroup $group = null;
    protected null|Closure|bool $isEditable = false;

    // Configurações de Tooltip
    protected array $tooltipData = [];

    // Configurações de Popup
    protected array $popupData = [];

    // Eventos e Scripts
    protected ?Closure $clickAction = null;
    protected ?string $onMouseOverScript = null;
    protected ?string $onMouseOutScript = null;

    // Record Binding
    protected ?Model $record = null;
    protected bool $syncRecordAttributes = true;
    protected ?string $recordJsonColumn = null;
    /** @var array Mapeia os valores do estado do layer para sincronização ($key => $value) */
    protected array $layerState = [];
    /** @var array Mapeia as colunas do registro para sincronização ($key => $column) */
    protected array $recordColumns = [];

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
     * Método de fábrica para criar uma instância do layer. Os parâmetros podem variar dependendo do tipo de layer (por exemplo, um marker pode precisar de latitude e longitude, enquanto um polígono pode precisar de um array de coordenadas).
     */
    abstract public static function make(): static;

    /**
     * Método de fábrica para criar uma instância do layer a partir de um registro Eloquent.
     */
    abstract static function fromRecord(Model $record): static;

    /**
     * Get the coordinates of the layer for centering the map. This method should return a Coordinate object representing the central point of the layer, which can be used to center the map view when the layer is added or interacted with. The implementation of this method will depend on the specific type of layer (e.g., a marker would return its own coordinates, while a polygon might return the centroid of its vertices).
     * @return Coordinate A Coordinate object representing the central point of the layer.
     * @example For a Marker layer, this method would return the marker's latitude and longitude as a Coordinate object. For a Polygon layer, it might calculate and return the centroid of the polygon's vertices as a Coordinate object.
     * @see Coordinate class for more details on how to represent coordinates in the system.
     */
    abstract protected function getCoordinates(): Coordinate;

    /*
    |--------------------------------------------------------------------------
    | Tooltip Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Set the content of the tooltip. Can be a string, a Closure that returns a string, or null to disable the tooltip.
     * @param Closure|string|null $content The content to display in the tooltip. If a Closure is provided, it will be evaluated to get the content. If null is provided, the tooltip will be disabled.
     * @return $this
     */
    public function tooltipContent(null|Closure|string $content): static
    {
        $this->tooltipOption('content', $content);
        return $this;
    }

    /**
     * Set whether the tooltip should be permanent (always visible) or not. Can be a boolean or a Closure that returns a boolean.
     * @param Closure|bool $permanent A boolean value or a Closure that returns a boolean indicating whether the tooltip should be permanent (always visible) or not.
     * @return $this
     * If true, the tooltip will always be visible. If false, the tooltip will only be visible on hover. If a Closure is provided, it will be evaluated to determine whether the tooltip should be permanent or not.
     */
    public function tooltipPermanent(Closure|bool $permanent = true): static
    {
        $this->tooltipOption('permanent', $permanent);
        return $this;
    }

    /**
     * Set the direction of the tooltip. Can be a string (e.g., 'top', 'bottom', 'left', 'right', 'auto') or a Closure that returns a string.
     * @param Closure|string $direction The direction to display the tooltip. Can be a string (e.g., 'top', 'bottom', 'left', 'right', 'auto') or a Closure that returns a string. If a Closure is provided, it will be evaluated to get the direction.
     * @return $this
     */
    public function tooltipDirection(Closure|string $direction = 'auto'): static
    {
        $this->tooltipOption('direction', $direction);
        return $this;
    }

    /**
     * Set a single option for the tooltip.
     * @param string $option The name of the option to set (e.g., 'content', 'permanent', 'direction').
     * @param mixed $value The value of the option. Can be a direct value or a Closure that returns the value.
     * @return $this
     * This method allows you to set any individual option for the tooltip. The $option parameter specifies which option to set, and the $value parameter is the value to assign to that option. If the $value is a Closure, it will be evaluated to get the actual value before being set.
     * Example usage: $layer->tooltipOption('permanent', true); // Sets the tooltip to be permanent (always visible)
     */
    public function tooltipOption(string $option, mixed $value): static
    {
        $this->tooltipData[$option] = $this->evaluate($value);
        return $this;
    }

    /**
     * Convenience method to set multiple options for the tooltip at once.
     * @param Closure|array $options An array of options to set for the tooltip, or a Closure that returns such an array. The array should be an associative array where the keys are option names (e.g., 'content', 'permanent', 'direction') and the values are the corresponding values for those options. If a Closure is provided, it will be evaluated to get the array of options.
     * @return $this
     * This method allows you to set multiple options for the tooltip in one call. You can pass an associative array of options, or a Closure that returns such an array. The provided options will be merged with any existing tooltip options.
     * Example usage: $layer->tooltipOptions(['permanent' => true, 'direction' => 'top']); // Sets the tooltip to be permanent and appear on top
     */
    public function tooltipOptions(Closure|array $options): static
    {
        $this->tooltipData['options'] = array_merge(
            $this->tooltipData['options'] ?? [],
            (array) $this->evaluate($options)
        );
        return $this;
    }

    /**
     * Convenience method to set the tooltip content, permanence, direction, and additional options all at once.
     * @param Closure|string $content The content to display in the tooltip. If a Closure is provided, it will be evaluated to get the content.
     * @param Closure|bool $permanent A boolean value or a Closure that returns a boolean indicating whether the tooltip should be permanent (always visible) or not.
     * @param Closure|string $direction The direction to display the tooltip. Can be a string (e.g., 'top', 'bottom', 'left', 'right', 'auto') or a Closure that returns a string. If a Closure is provided, it will be evaluated to get the direction.
     * @param Closure|array $options An array of additional options to set for the tooltip, or a Closure that returns such an array. The array should be an associative array where the keys are option names and the values are the corresponding values for those options. If a Closure is provided, it will be evaluated to get the array of options.
     * @return $this
     * This method provides a convenient way to set multiple tooltip configurations in a single call. You can specify the content, permanence, direction, and any additional options for the tooltip all at once.
     * Example usage: $layer->tooltip('This is a tooltip', true, 'top', ['className' => 'my-tooltip']); // Sets the tooltip content, makes it permanent, positions it on top, and adds a custom CSS class
     */
    public function tooltip(
        Closure|string $content,
        Closure|bool $permanent = false,
        Closure|string $direction = 'auto',
        Closure|array $options = []
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
     * Set the title of the popup. Can be a string, a Closure that returns a string, or null to disable the title.
     * @param Closure|string|null $title The title to display in the popup. If a Closure is provided, it will be evaluated to get the title. If null is provided, the popup title will be disabled.
     * @return $this
     */
    public function popupTitle(null|Closure|string $title): static
    {
        $this->popupOption('title', $title);
        return $this;
    }

    /**
     * Set the content of the popup. Can be a string, a Closure that returns a string, or null to disable the popup.
     * @param Closure|string|null $content The content to display in the popup. If a Closure is provided, it will be evaluated to get the content. If null is provided, the popup will be disabled.
     * @return $this
     */
    public function popupContent(null|Closure|string $content): static
    {
        $this->popupOption('content', $content);
        return $this;
    }

    /**
     * Set multiple fields to be displayed in the popup, along with an optional date format for any DateTime fields. The $fields parameter can be an array or a Collection of key-value pairs, where the key is the field name and the value is the field value. The $dateFormat parameter specifies how to format any DateTime values in the fields, and can be a string (e.g., 'dd/MM/YYYY') or a Closure that returns such a string.
     * @param array|Collection $fields An array or Collection of key-value pairs representing the fields to display in the popup. The keys are the field names (which will be used as labels), and the values are the corresponding field values.
     * @param Closure|string $dateFormat A string or a Closure that returns a string specifying how to format any DateTime values in the fields. For example, 'dd/MM/YYYY' would format dates as day/month/year. If a Closure is provided, it will be evaluated to get the date format string.
     * @return $this
     */
    public function popupFields(array|Collection $fields, Closure|string $dateFormat = 'd/m/Y'): static
    {
        $collectedFields = is_array($fields) ? collect($fields) : $fields;

        $mappedFields = $collectedFields->mapWithKeys(function ($value, $key) use ($dateFormat) {
            $label = str($key)->camel()->kebab()->replace('-', ' ')->title()->toString();
            $content = $value instanceof DateTime ? $value->format($dateFormat) : $value;

            return [__($label) => __($content) ?: '--'];
        })->toArray();

        $this->popupData['fields'] = array_merge(
            $this->popupData['fields'] ?? [],
            $mappedFields
        );

        return $this;
    }

    /**
     * Set a single option for the popup.
     * @param string $option The name of the option to set (e.g., 'title', 'content', 'fields').
     * @param mixed $value The value of the option. Can be a direct value or a Closure that returns the value.
     * @return $this
     */
    public function popupOption(string $option, mixed $value): static
    {
        $this->popupData[$option] = $this->evaluate($value);
        return $this;
    }

    /**
     * Convenience method to set multiple options for the popup at once.
     * @param Closure|array $options An array of options to set for the popup, or a Closure that returns such an array. The array should be an associative array where the keys are option names (e.g., 'title', 'content', 'fields') and the values are the corresponding values for those options. If a Closure is provided, it will be evaluated to get the array of options.
     * @return $this
     */
    public function popupOptions(Closure|array $options): static
    {
        $this->popupData['options'] = array_merge(
            $this->popupData['options'] ?? [],
            (array) $this->evaluate($options)
        );
        return $this;
    }

    /**
     * Convenience method to set the tooltip content, permanence, direction, and additional options all at once.
     * @param Closure|string $content The content to display in the popup. If a Closure is provided, it will be evaluated to get the content.
     * @param Closure|array $fields An array of fields to display in the popup, or a Closure that returns such an array. The array should be an associative array where the keys are field names (which will be used as labels) and the values are the corresponding field values. If a Closure is provided, it will be evaluated to get the array of fields.
     * @param Closure|array $options An array of additional options to set for the popup, or a Closure that returns such an array. The array should be an associative array where the keys are option names and the values are the corresponding values for those options. If a Closure is provided, it will be evaluated to get the array of options.
     * @param string|Closure $dateFormat A string or a Closure that returns a string specifying how to format any DateTime values in the fields. For example, 'dd/MM/YYYY' would format dates as day/month/year. If a Closure is provided, it will be evaluated to get the date format string.
     * @return $this
     */
    public function popup(
        Closure|string $content,
        Closure|array $fields = [],
        Closure|array $options = [],
        string|Closure $dateFormat = 'dd/MM/YYYY'
    ): static {
        return $this
            ->popupContent($content)
            ->popupFields($fields, $dateFormat)
            ->popupOptions($options);
    }

    /**
     * Convenience method to set the same value for both the tooltip content and popup title.
     * @param Closure|string|null $title The title to display in both the tooltip and popup. If a Closure is provided, it will be evaluated to get the title. If null is provided, both the tooltip content and popup title will be disabled.
     * @return $this
     */
    public function title(null|Closure|string $title)
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

    /**
     * Set a click action for the layer. The $callback parameter can be a Closure that will be executed when the layer is clicked. Inside the Closure, you can access the layer instance and its associated record (if any) to perform actions such as updating the record, changing the layer's appearance, or triggering other side effects.
     * @param Closure|null $callback A Closure to execute when the layer is clicked. The Closure can accept parameters such as the layer instance and its associated record. If null is provided, any existing click action will be removed.
     * @return $this
     */
    public function action(?Closure $callback): static
    {
        $this->clickAction = $callback;
        return $this;
    }

    /**
     * Convenience method to set a click action for the layer. This is an alias for the action() method, providing a more intuitive name for setting click event listeners.
     * @param Closure|null $callback A Closure to execute when the layer is clicked. The Closure can accept parameters such as the layer instance and its associated record. If null is provided, any existing click action will be removed.
     * @return $this
     */
    public function onClick(callable $callback): static
    {
        return $this->action($callback);
    }

    /**
     * Sets the mouse over script for the layer.
     * @param string $script The JavaScript code to execute when the mouse is over the layer.
     * @return $this
     */
    public function onMouseOver(string $script): static
    {
        $this->onMouseOverScript = $script;
        return $this;
    }

    /**
     * Sets the mouse out script for the layer.
     * @param string $script The JavaScript code to execute when the mouse leaves the layer.
     * @return $this
     */
    public function onMouseOut(string $script): static
    {
        $this->onMouseOutScript = $script;
        return $this;
    }

    public function execClickAction()
    {
        if (!isset($this->clickAction)) return;

        $this->evaluate($this->clickAction);
    }

    /*
    |--------------------------------------------------------------------------
    | Record Binding
    |--------------------------------------------------------------------------
    */

    /**
     * Bind an Eloquent model record to the layer. This allows you to associate the layer with a specific database record, enabling features such as displaying record data in popups or tooltips, and synchronizing changes made to the layer back to the database. The $syncAttributes parameter determines whether changes to the layer's attributes should be automatically synchronized back to the associated record when the layer is updated.
     * @param Model $record The Eloquent model record to bind to the layer.
     * @return $this
     */
    public function record(
        Model $record,
    ): static {
        $this->record = $record;
        return $this;
    }

    public function getRecord(): ?Model
    {
        return $this->record;
    }

    /**
     * Maps the record data using a closure.
     * @param Closure|null $callback The closure to use for mapping the record data.
     * @return $this
     */
    public function mapRecordUsing(?Closure $callback): static
    {
        return $this->evaluate($callback) ?? $this;
    }

    protected static function makeFromRecord(
        Model $record,
        array $instanceParameters,
        array $recordColumns,
        ?bool $syncAttributes,
        ?string $jsonColumn,
        ?array $popupFieldsColumns,
        null|Closure|string|array $color,
        ?Closure $mapRecordCallback,
    ): static {
        $recordColumns = collect($recordColumns)
            ->map(fn($column, $key) => $column ?? config("filament-leaflet.columns.{$key}", $key))
            ->toArray();

        $except = array_filter([
            ...array_values($recordColumns),
            $jsonColumn,
            'created_at',
            'updated_at',
            'id',
        ]);

        $popupFieldsColumns ??= array_keys($record->except($except));
        $titleColumn = $recordColumns['title'] ?? null;
        $descriptionColumn = $recordColumns['description'] ?? null;

        $instance = new static(...$instanceParameters);

        $instance->syncRecordAttributes = $syncAttributes ?? config('filament-leaflet.sync_record_attributes', true);
        $instance->recordJsonColumn = $jsonColumn;
        $instance->recordColumns = $recordColumns;

        return $instance
            ->record($record)
            ->title($record->getAttribute($titleColumn) ?? null)
            ->popupContent($record->getAttribute($descriptionColumn) ?? null)
            ->popupFields($record->only($popupFieldsColumns))
            ->color($color)
            ->mapRecordUsing($mapRecordCallback);
    }

    /**
     * @return array<mixed>
     */
    protected function resolveDefaultClosureDependencyForEvaluationByName(string $parameterName): array
    {
        $modelName = class_basename($this->getRecord());

        return match ($parameterName) {
            'record', Str::camel($modelName) => [$this->getRecord()],
            $this->getType(), 'layer' => [$this],
            default => []
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Group & Identity
    |--------------------------------------------------------------------------
    */

    /**
     * Gera ID determinístico baseado nos dados do layer
     */
    private function generateDeterministicId(): string
    {
        return $this->getType() . '-' . md5($this->getDeterministicIdData());
    }

    /**
     * Retorna os dados usados para gerar o ID determinístico
     */
    protected function getDeterministicIdData(): string
    {
        if ($this->record) {
            return $this->record->getTable() . '-' . $this->record->toJson();
        }

        return json_encode($this->getLayerData());
    }

    /**
     * Set the ID of the layer. The $id parameter can be a string or a Closure that returns a string. If a Closure is provided, it will be evaluated to get the ID. If no ID is set, a deterministic ID will be generated based on the layer's data.
     * @param Closure|string $id The ID to assign to the layer. Can be a direct string or a Closure that returns a string. If a Closure is provided, it will be evaluated to get the actual ID value.
     * @return $this
     */
    public function id(Closure|string $id): static
    {
        $this->id = $id;
        return $this;
    }


    /**
     * Set the group of the layer. The $group parameter can be a string, an instance of BaseLayerGroup, or a Closure that returns either of those. If a Closure is provided, it will be evaluated to get the actual group value.
     * @param Closure|null|string|BaseLayerGroup $group The group to assign to the layer. Can be a string, an instance of BaseLayerGroup, or a Closure that returns either of those. If a Closure is provided, it will be evaluated to get the actual group value. If null is provided, any existing group assignment will be removed.
     * @return $this
     */
    public function group(null|Closure|string|BaseLayerGroup $group): static
    {
        $this->group = $group;
        return $this;
    }

    /**
     * Retorna o ID do layer.
     */
    public function getId(): ?string
    {
        return $this->evaluate($this->id) ?: $this->generateDeterministicId();
    }

    /**
     * Retorna o grupo do layer.
     */
    public function getGroup(): null|string|BaseLayerGroup
    {
        return $this->evaluate($this->group);
    }

    public function getEditable(): bool
    {
        return (bool) $this->evaluate($this->isEditable);
    }

    /**
     * Set whether the layer is editable. The $editable parameter can be a boolean or a Closure that returns a boolean. If a Closure is provided, it will be evaluated to determine whether the layer should be editable or not.
     * @param Closure|bool $editable A boolean value or a Closure that returns a boolean indicating whether the layer should be editable or not. If true, the layer will be editable. If false, the layer will not be editable. If a Closure is provided, it will be evaluated to determine whether the layer should be editable or not.
     * @return $this
     */
    public function editable(Closure|bool $editable = true): static
    {
        $this->isEditable = $editable;
        return $this;
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
        $group = $this->getGroup();

        $data = [
            'id'           => $this->getId(),
            'type'         => $this->getType(),
            'group'        => $group instanceof BaseLayerGroup ? $group->getId() : $group,
            'onMouseOver'  => $this->onMouseOverScript,
            'onMouseOut'   => $this->onMouseOutScript,
            'isEditable'   => $this->getEditable(),
            'hasRecord'    => $this->record !== null,
        ];

        if (array_filter($this->tooltipData)) {
            $data['tooltip'] = array_filter($this->tooltipData);
        }

        if (array_filter($this->popupData)) {
            $data['popup'] = array_filter($this->popupData);
        }

        return $data;
    }

    public final function updateLayer(array $data): void
    {
        $this->layerState = $data;

        if ($this->syncRecordAttributes && $this->record) {
            $attributes = collect($this->layerState)
                ->mapWithKeys(fn($value, $key) => [$this->recordColumns[$key] ?? $key => $value])
                ->toArray();

            $this->record->update($this->recordJsonColumn
                ? [$this->recordJsonColumn => $attributes]
                : $attributes);
        }
    }

    /**
     * Retorna os dados do layer para serialização.
     */
    public function toArray(): array
    {
        $data = array_merge(
            $this->getBaseData(),
            $this->getLayerData()
        );

        return array_filter($data);
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
