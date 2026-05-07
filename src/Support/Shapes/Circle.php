<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Shapes;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\Coordinate;
use Illuminate\Database\Eloquent\Model;

class Circle extends Shape
{
    protected ?string $radiusColumn = null;

    final public function __construct(float $latitude, float $longitude, float $radius = 50000)
    {
        $this->layerState['lat'] = $latitude;
        $this->layerState['lng'] = $longitude;
        $this->layerState['radius'] = $radius;
    }

    /**
     * Convenience method to create a Circle instance with given latitude and longitude.
     * @param float $latitude The latitude for the circle's center.
     * @param float $longitude The longitude for the circle's center.
     * @return static A new Circle instance with the specified center coordinates.
     */
    public static function make(float $latitude = 0, float $longitude = 0): static
    {
        return new static($latitude, $longitude);
    }

    /**
     * Create a Circle instance from an Eloquent record.
     * @param Model $record The Eloquent model record to create the circle from.
     * @param string|null $coordsColumn The attribute name for coordinates.
     * @param string|null $radiusColumn The column name for radius.
     * @param string|null $titleColumn Optional column name for circle title.
     * @param string|null $descriptionColumn Optional column name for circle description.
     * @param array|null $popupFieldsColumns Optional array of column names to include in popup.
     * @param string|array|null $color Optional circle color.
     * @param bool|null $syncRecord Whether to sync changes back to the record when the shape is edited on the map.
     * @param Closure|null $mapRecordCallback Optional Closure to further customize the circle based on the record.
     * @return static A new Circle instance configured based on the provided record.
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
            mapRecordCallback: $mapRecordCallback,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Radius Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Set the radius of the circle in meters.
     * @param float $meters The radius in meters.
     * @return $this
     */
    public function radius(float $meters): static
    {
        $this->layerState['radius'] = $meters;
        return $this;
    }

    /**
     * Set the radius of the circle in kilometers.
     * @param float $km The radius in kilometers.
     * @return $this
     */
    public function radiusInKilometers(float $km): static
    {
        return $this->radius($km * 1000);
    }

    /**
     * Set the radius of the circle in miles.
     * @param float $miles The radius in miles.
     * @return $this
     */
    public function radiusInMiles(float $miles): static
    {
        return $this->radius($miles * 1609.344);
    }

    /**
     * Set the radius of the circle in feet.
     * @param float $feet The radius in feet.
     * @return $this
     */
    public function radiusInFeet(float $feet): static
    {
        return $this->radius($feet * 0.3048);
    }

    /*
    |--------------------------------------------------------------------------
    | Layer Implementation
    |--------------------------------------------------------------------------
    */

    public function getType(): string
    {
        return 'circle';
    }

    protected function getShapeData(): array
    {
        return [
            'center'  => [
                $this->layerState['lat'] ?? 0,
                $this->layerState['lng'] ?? 0
            ],
        ];
    }

    protected function getShapeOptions(): array
    {
        return [
            'radius' => $this->layerState['radius'] ?? 50000,
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
