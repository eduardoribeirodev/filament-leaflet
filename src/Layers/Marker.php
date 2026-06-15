<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Layers;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\Layers\BaseLayer;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\Coordinate;
use Exception;
use EduardoRibeiroDev\FilamentLeaflet\Enums\MarkerAnimation;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class Marker extends BaseLayer
{
    protected bool|Closure $isDraggable = false;

    // Configurações de Ícone
    protected string|Closure|null $iconUrl = null;
    protected array|Closure $iconSize = [24, 36];
    protected Heroicon|string|Closure|null $heroicon = null;
    protected string|int|float|Closure|null $text = null;
    protected string|Closure|null $textColor = null;
    protected string|Closure|null $iconColor = null;
    protected string|Closure|null $outlineColor = null;
    protected int|Closure|null $outlineWidth = null;
    protected bool|Closure|null $showShadow = null;
    protected MarkerAnimation|string|bool|Closure|null $animation = null;
    protected string|array|Closure|null $animationColor = null;
    protected string|float|int|Closure|null $animationDuration = null;


    final public function __construct(float $latitude = 0, float $longitude = 0)
    {
        $this->layerState['lat'] = $latitude;
        $this->layerState['lng'] = $longitude;
    }

    /**
     * Convenience method to create a Marker instance with given latitude and longitude.
     * @param float $latitude The latitude for the marker.
     * @param float $longitude The longitude for the marker.
     * @return static A new Marker instance with the specified coordinates.
     */
    public static function make(float $latitude = 0, float $longitude = 0): static
    {
        return new static($latitude, $longitude);
    }

    /**
     * Create a Marker instance from an Eloquent record.
     * @param Model $record The Eloquent model record to create the marker from.
     * @param string|null $coordsColumn Optional column name for coordinates attribute.
     * @param string|null $titleColumn Optional column name for marker title.
     * @param string|null $descriptionColumn Optional column name for marker description.
     * @param array|null $popupFieldsColumns Optional array of column names to include in popup.
     * @param Closure|string|null $coordsColumn Optional column name or Closure returning the coordinates attribute name.
     * @param Closure|string|null $titleColumn Optional column name or Closure returning the marker title column name.
     * @param Closure|string|null $descriptionColumn Optional column name or Closure returning the marker description column name.
     * @param Closure|array|null $popupFieldsColumns Optional array of popup column names or Closure returning them.
     * @param Closure|string|array|null $color Optional marker color or Closure returning the color.
     * @param Closure|bool|null $syncRecord Whether to sync changes back to the record when the marker is dragged on the map.
     * @param Closure|string|null $iconUrl Optional URL or Closure to determine the marker's icon URL.
     * @param Closure|null $mapRecordCallback Optional Closure to further customize the marker based on the record.
     * @return static A new Marker instance configured based on the provided record.
     */
    public static function fromRecord(
        Model $record,
        null|Closure|string $coordsColumn = null,
        null|Closure|string $titleColumn = null,
        null|Closure|string $descriptionColumn = null,
        null|Closure|array $popupFieldsColumns = null,
        null|Closure|string|array $color = null,
        null|Closure|bool $syncRecord = null,
        null|Closure|string $iconUrl = null,
        ?Closure $mapRecordCallback = null
    ): static {
        if ($coordsColumn instanceof Closure) {
            $coordsColumn = $coordsColumn($record);
        }

        $coordsColumn ??= config('filament-leaflet.columns.coords');
        $coords = $record->getAttribute($coordsColumn) ?? throw new \Exception("The specified coordinates column '{$coordsColumn}' does not exist on the record.");

        if ($popupFieldsColumns instanceof Closure) {
            $popupFieldsColumns = $popupFieldsColumns($record);
        }

        if ($syncRecord instanceof Closure) {
            $syncRecord = (bool) $syncRecord($record);
        }

        return static::makeFromRecord(
            record: $record,
            instanceParameters: $coords->toFlatArray(),
            recordColumns: [
                'coords' => $coordsColumn,
                'title' => $titleColumn,
                'description' => $descriptionColumn,
            ],
            syncAttributes: $syncRecord,
            jsonColumn: $coordsColumn,
            popupFieldsColumns: $popupFieldsColumns,
            color: $color,
            mapRecordCallback: $mapRecordCallback,
        )->when($iconUrl, fn(self $marker) => $marker->iconUrl($iconUrl));
    }

    /*
    |--------------------------------------------------------------------------
    | Métodos abstratos do Layer
    |--------------------------------------------------------------------------
    */

    public function getType(): string
    {
        return 'marker';
    }

    protected function getLayerData(): array
    {
        return [
            'coords' => [
                $this->layerState['lat'] ?? 0,
                $this->layerState['lng'] ?? 0
            ],
            'icon'      => $this->getIconOptions(),
            'draggable' => $this->isDraggable(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Métodos do Marker
    |--------------------------------------------------------------------------
    */

    /**
     * Set the marker's icon URL.
     * @param string|Closure|null $url The URL of the icon or a Closure that returns the URL.
     * @return $this
     */
    public function iconUrl(null|Closure|string $url = null): static
    {
        $this->iconUrl = $url;
        return $this;
    }

    /**
     * Set the marker's icon size.
     * @param Closure|array $size An array with width and height or a Closure that returns such an array.
     * @return $this
     */
    public function iconSize(Closure|array $size = [24, 36]): static
    {
        $this->iconSize = $size;
        return $this;
    }

    public function heroicon(null|string|Heroicon|Closure $icon = null): static
    {
        $this->heroicon = $icon;
        return $this;
    }

    /**
     * Convenience method to set the marker's icon, automatically determining whether it's a Heroicon or a URL.
     * @param null|Closure|string $icon The icon to use, which can be a Heroicon name, a URL, or a Closure that returns either.
     * @param Closure|array $size An array with width and height or a Closure that returns such an array for the icon size.
     * @return $this
     */
    public function icon(null|Closure|Heroicon|string $icon = null, Closure|array $size = [24, 36]): static
    {
        $evaluatedIcon = $this->evaluate($icon);
        if ($evaluatedIcon instanceof Heroicon || (is_string($evaluatedIcon) && str_starts_with($evaluatedIcon, 'heroicon-'))) {
            $this->heroicon($icon);
        } else if (is_string($evaluatedIcon)) {
            $this->iconUrl($icon);
        } else {
            $this->iconUrl(null);
            $this->heroicon(null);
        }

        $this->iconSize($size);
        return $this;
    }

    private function resolveHeroicon(): ?string
    {
        if (($heroicon = $this->getHeroicon())) {
            if (is_string($heroicon)) {
                $iconClass = str_replace('heroicon-', '', $heroicon);
                $heroicon = Heroicon::tryFrom($iconClass) ?? throw new Exception("Invalid heroicon name: {$iconClass}");
            }

            if ($heroicon instanceof Heroicon) {
                $iconClass = $heroicon->getIconForSize(IconSize::Small);
                return svg($iconClass)->toHtml();
            }
        }

        return null;
    }

    public function getIconOptions()
    {
        return array_filter([
            'color'             => $this->getRgbColor(500),
            'url'               => $this->getIconUrl(),
            'iconSize'          => $this->getIconSize(),
            'heroicon'          => $this->resolveHeroicon(),
            'text'              => $this->getText(),
            'textColor'         => $this->getTextColor(),
            'iconColor'         => $this->getIconColor(),
            'outlineColor'      => $this->getOutlineColor(),
            'outlineWidth'      => $this->getOutlineWidth(),
            'showShadow'        => $this->getShowShadow(),
            'animation'         => $this->getAnimation(),
            'animationColor'    => $this->getAnimationColor(),
            'animationDuration' => $this->getAnimationDuration(),
        ], fn ($value) => $value !== null);
    }

    /**
     * Set whether the marker is draggable.
     * @param Closure|bool $condition A boolean or a Closure that returns a boolean to determine if the marker should be draggable.
     * @return $this
     */
    public function draggable(Closure|bool $condition = true): static
    {
        $this->isDraggable = $condition;
        return $this;
    }

    /**
     * Get evaluated icon URL.
     */
    public function getIconUrl(): ?string
    {
        return $this->evaluate($this->iconUrl);
    }

    /**
     * Get evaluated icon size as array [width, height].
     */
    public function getIconSize(): array
    {
        return (array) $this->evaluate($this->iconSize);
    }

    public function text(null|Closure|string|int|float $text = null): static
    {
        $this->text = $text;
        return $this;
    }

    public function textColor(null|Closure|string $textColor = null): static
    {
        $this->textColor = $textColor;
        return $this;
    }

    public function iconColor(null|Closure|string $iconColor = null): static
    {
        $this->iconColor = $iconColor;
        return $this;
    }

    public function outlineColor(null|Closure|string $outlineColor = null): static
    {
        $this->outlineColor = $outlineColor;
        return $this;
    }

    public function outlineWidth(null|Closure|int $outlineWidth = null): static
    {
        $this->outlineWidth = $outlineWidth;
        return $this;
    }

    public function showShadow(null|Closure|bool $showShadow = null): static
    {
        $this->showShadow = $showShadow;
        return $this;
    }

    public function animation(null|Closure|string|bool|MarkerAnimation $animation = null): static
    {
        $this->animation = $animation;
        return $this;
    }

    public function animationColor(null|Closure|string $animationColor = null): static
    {
        $this->animationColor = $animationColor;
        return $this;
    }

    public function animationDuration(null|Closure|int|float $animationDuration = null): static
    {
        $this->animationDuration = $animationDuration;
        return $this;
    }

    public function getText(): string|int|float|null
    {
        return $this->evaluate($this->text);
    }

    public function getTextColor(): ?string
    {
        return $this->evaluate($this->textColor);
    }

    public function getIconColor(): ?string
    {
        return $this->evaluate($this->iconColor);
    }

    public function getOutlineColor(): ?string
    {
        return $this->evaluate($this->outlineColor);
    }

    public function getOutlineWidth(): ?int
    {
        return $this->evaluate($this->outlineWidth);
    }

    public function getShowShadow(): ?bool
    {
        return $this->evaluate($this->showShadow);
    }

    public function getAnimation(): string|bool|null
    {
        $animation = $this->evaluate($this->animation);

        if ($animation instanceof MarkerAnimation) {
            return $animation->value;
        }

        return $animation;
    }

    public function getAnimationColor(): ?string
    {
        $animationColor = $this->evaluate($this->animationColor);

        if (is_array($animationColor) && array_key_exists(500, $animationColor)) {
            return $animationColor[500];
        } else if (is_array($animationColor)) {
            return reset($animationColor);
        } else if (is_string($animationColor)) {
            return $animationColor;
        }

        return null;
    }

    public function getAnimationDuration(): int|float|null
    {
        return $this->evaluate($this->animationDuration);
    }

    /**
     * Get evaluated heroicon (Heroicon instance or string) or null.
     * Note: use `resolveHeroicon()` to get rendered SVG string.
     */
    public function getHeroicon(): Heroicon|string|null
    {
        return $this->evaluate($this->heroicon);
    }

    /**
     * Whether the marker is draggable (evaluated).
     */
    public function isDraggable(): bool
    {
        return (bool) $this->evaluate($this->isDraggable);
    }

    /*
    |--------------------------------------------------------------------------
    | Utilitários
    |--------------------------------------------------------------------------
    */

    protected function getCoordinates(): Coordinate
    {
        return new Coordinate(
            $this->layerState['lat'] ?? 0,
            $this->layerState['lng'] ?? 0
        );
    }

    /**
     * Calculate the distance in kilometers to another marker using the Haversine formula.
     * @param Marker $target The target marker to calculate the distance to.
     * @return float The distance in kilometers.
     */
    public function distanceTo(Marker $target): float
    {
        return $this->getLayerCoordinates()->distanceTo($target->getLayerCoordinates());
    }
}
