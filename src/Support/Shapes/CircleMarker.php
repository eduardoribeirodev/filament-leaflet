<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Shapes;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\Coordinate;
use Illuminate\Database\Eloquent\Model;

class CircleMarker extends Shape
{
    final public function __construct(float $latitude, float $longitude, float $radius = 10)
    {
        $this->layerState['lat'] = $latitude;
        $this->layerState['lng'] = $longitude;
        $this->layerState['radius'] = $radius;
    }

    /**
     * Convenience method to create a CircleMarker instance with given latitude and longitude.
     * @param float $latitude The latitude for the circle marker's center.
     * @param float $longitude The longitude for the circle marker's center.
     * @return static A new CircleMarker instance with the specified center coordinates.
     */
    public static function make(float $latitude = 0, float $longitude = 0): static
    {
        return new static($latitude, $longitude);
    }

    /**
     * Create a CircleMarker instance from an Eloquent record.
     * @param Model $record The Eloquent model record to create the circle marker from.
     * @param string|null $coordsColumn The attribute name for coordinates.
     * @param string|null $radiusColumn The column name for radius.
     * @param string|null $titleColumn Optional column name for circle marker title.
     * @param string|null $descriptionColumn Optional column name for circle marker description.
     * @param array|null $popupFieldsColumns Optional array of column names to include in popup.
     * @param bool|null $syncRecord Whether to sync changes back to the record when the shape is edited on the map.
     * @param string|array|null $color Optional circle marker color.
     * @param Closure|null $mapRecordCallback Optional Closure to further customize the circle marker based on the record.
     * @return static A new CircleMarker instance configured based on the provided record.
     */
    public static function fromRecord(
        Model $record,
        ?string $coordsColumn = null,
        ?string $radiusColumn = null,
        ?string $titleColumn = null,
        ?string $descriptionColumn = null,
        ?array $popupFieldsColumns = null,
        string|array|null $color = null,
        ?bool $syncRecord = null,
        ?Closure $mapRecordCallback = null
    ): static {
        $coordsColumn ??= config('filament-leaflet.columns.coords');
        $coords = $record->getAttribute($coordsColumn) ?? throw new \Exception("The specified coordinates column '{$coordsColumn}' does not exist on the record.");

        return static::makeFromRecord(
            record: $record,
            instanceParameters: $coords->toFlatArray(),
            recordColumns: [
                'coords' => $coordsColumn,
                'radius' => $radiusColumn,
                'title' => $titleColumn,
                'description' => $descriptionColumn,
            ],
            syncAttributes: $syncRecord,
            jsonColumn: $coordsColumn,
            popupFieldsColumns: $popupFieldsColumns,
            color: $color,
            mapRecordCallback: $mapRecordCallback
        );
    }


    /**
     * Set the radius of the circle marker in pixels.
     * @param int $pixels The radius of the circle marker in pixels.
     * @return $this
     */
    public function radius(int $pixels): static
    {
        $this->layerState['radius'] = $pixels;
        return $this;
    }

    public function getType(): string
    {
        return 'circleMarker';
    }

    protected function getShapeData(): array
    {
        return [
            'center' => [
                $this->layerState['lat'] ?? 0,
                $this->layerState['lng'] ?? 0
            ],
        ];
    }

    protected function getShapeOptions(): array
    {
        return [
            'radius' => $this->layerState['radius'] ?? 10,
            ...parent::getShapeOptions()
        ];
    }

    protected function getCoordinates(): Coordinate
    {
        return new Coordinate(
            $this->layerState['lat'] ?? 0,
            $this->layerState['lng'] ?? 0
        );
    }
}
