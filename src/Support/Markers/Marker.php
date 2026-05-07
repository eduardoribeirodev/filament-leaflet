<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Markers;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\Support\BaseLayer;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\Coordinate;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class Marker extends BaseLayer
{
    protected bool $isDraggable = false;

    // Configurações de Ícone
    protected ?string $iconUrl = null;
    protected array $iconSize = [24, 36];
    protected ?Heroicon $heroicon = null;


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
     * @param string|array|null $color Optional marker color.
     * @param bool|null $syncRecord Whether to sync changes back to the record when the marker is dragged on the map.
     * @param string|Closure|null $iconUrl Optional URL or Closure to determine the marker's icon URL.
     * @param Closure|null $mapRecordCallback Optional Closure to further customize the marker based on the record.
     * @return static A new Marker instance configured based on the provided record.
     */
    public static function fromRecord(
        Model $record,
        ?string $coordsColumn = null,
        ?string $titleColumn = null,
        ?string $descriptionColumn = null,
        ?array $popupFieldsColumns = null,
        string|array|null $color = null,
        ?bool $syncRecord = null,
        ?string $iconUrl = null,
        ?Closure $mapRecordCallback = null
    ): static {
        $coordsColumn ??= config('filament-leaflet.columns.coords');
        $coords = $record->getAttribute($coordsColumn) ?? throw new \Exception("The specified coordinates column '{$coordsColumn}' does not exist on the record.");

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
        )->icon($iconUrl);
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
            'draggable' => $this->isDraggable,
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
        $this->iconUrl = $this->evaluate($url);

        if ($this->iconUrl !== null) {
            $this->heroicon = null;
        }

        return $this;
    }

    /**
     * Set the marker's icon size.
     * @param Closure|array $size An array with width and height or a Closure that returns such an array.
     * @return $this
     */
    public function iconSize(Closure|array $size = [24, 36]): static
    {
        $this->iconSize = (array) $this->evaluate($size);
        return $this;
    }

    public function heroicon(null|string|Heroicon|Closure $icon = null): static
    {
        $evaluatedIcon = $this->evaluate($icon);

        $this->heroicon = ($evaluatedIcon instanceof Heroicon || $evaluatedIcon === null)
            ? $evaluatedIcon
            : Heroicon::tryFrom(str_replace('heroicon-', '', $evaluatedIcon));

        if ($this->heroicon !== null) {
            $this->iconUrl = null;
        }

        return $this;
    }

    /**
     * Convenience method to set both icon URL and size at once.
     * @param Closure|array $size An array with width and height or a Closure that returns such an array.
     * @return $this
     */
    public function icon(null|Closure|Heroicon|string $icon = null, Closure|array $size = [24, 36]): static
    {
        $evaluatedIcon = $this->evaluate($icon);
        if ($evaluatedIcon instanceof Heroicon || str_starts_with($icon, 'heroicon') || Heroicon::tryFrom($evaluatedIcon) !== null) {
            $this->heroicon($icon);
        } else {
            $this->iconUrl($icon);
        }

        $this->iconSize($size);
        return $this;
    }

    private function resolveHeroicon(): ?string
    {
        if ($this->heroicon === null) {
            return null;
        }

        $iconClass = $this->heroicon->getIconForSize(IconSize::Small);
        return svg($iconClass)->toHtml();
    }

    public function getIconOptions()
    {
        return [
            'color'    => $this->getRgbColor(500),
            'url'      => $this->iconUrl,
            'size'     => $this->iconSize,
            'heroicon' => $this->resolveHeroicon(),
        ];
    }

    /**
     * Set whether the marker is draggable.
     * @param Closure|bool $condition A boolean or a Closure that returns a boolean to determine if the marker should be draggable.
     * @return $this
     */
    public function draggable(Closure|bool $condition = true): static
    {
        $this->isDraggable = (bool) $this->evaluate($condition);
        return $this;
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
