<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Shapes;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\Coordinate;
use Illuminate\Database\Eloquent\Model;

class Rectangle extends Shape
{
    /**
     * @param array $corner1 Coordenada [lat, lng] do primeiro canto
     * @param array $corner2 Coordenada [lat, lng] do canto oposto
     */
    final public function __construct(array $corner1, array $corner2)
    {
        $this->layerState['bounds'] = [$corner1, $corner2];
    }

    /**
     * Convenience method to create a Rectangle instance with given corner coordinates.
     * @param array $corner1 Coordenate [lat, lng] of the first corner of the rectangle.
     * @param array $corner2 Coordenate [lat, lng] of the opposite corner of the rectangle.
     * @return static A new Rectangle instance with the specified corner coordinates.
     */
    public static function make(array $corner1 = [[0, 0]], array $corner2 = [[0, 0]]): static
    {
        return new static($corner1, $corner2);
    }


    /**
     * Convenience method to create a Rectangle instance from four separate latitude and longitude values for the two corners.
     * @param float $lat1 Latitude of the first corner of the rectangle.
     * @param float $lng1 Longitude of the first corner of the rectangle.
     * @param float $lat2 Latitude of the opposite corner of the rectangle.
     * @param float $lng2 Longitude of the opposite corner of the rectangle.
     * @return static A new Rectangle instance with the specified corner coordinates.
     */
    public static function makeFromCoordinates(float $lat1, float $lng1, float $lat2, float $lng2): static
    {
        return new static([$lat1, $lng1], [$lat2, $lng2]);
    }

    /**
     * Create a Rectangle instance from an Eloquent record. The method will attempt to extract the rectangle bounds from the specified $boundsColumn, which can be a JSON string or an array. It will also set the title, description, popup fields, and color based on the provided parameters and the record's attributes.
     * @param Model $record The Eloquent model record to create the rectangle from.
     * @param string $boundsColumn The column name for the rectangle bounds.
     * @param string|null $titleColumn Optional column name for rectangle title.
     * @param string|null $descriptionColumn Optional column name for rectangle description.
     * @param array|null $popupFieldsColumns Optional array of column names to include in popup.
     * @param string|array|null $color Optional rectangle color.
     * @param bool|null $syncRecord Whether to sync changes back to the record when the shape is edited on the map.
     * @param Closure|null $mapRecordCallback Optional Closure to further customize the rectangle based on the record.
     * @return static A new Rectangle instance configured based on the provided record.
     */
    public static function fromRecord(
        Model $record,
        ?string $boundsColumn = null,
        ?string $titleColumn = null,
        ?string $descriptionColumn = null,
        ?array $popupFieldsColumns = null,
        string|array|null $color = null,
        ?bool $syncRecord = null,
        ?Closure $mapRecordCallback = null
    ): static {
        $bounds = $record->{$boundsColumn} ?? [];

        return static::makeFromRecord(
            record: $record,
            instanceParameters: is_string($bounds) ? json_decode($bounds, true) : $bounds,
            recordColumns: [
                'bounds' => $boundsColumn,
                'title' => $titleColumn,
                'description' => $descriptionColumn,
            ],
            syncAttributes: $syncRecord,
            jsonColumn: $boundsColumn,
            popupFieldsColumns: $popupFieldsColumns,
            color: $color,
            mapRecordCallback: $mapRecordCallback,
        );
    }

    public function getType(): string
    {
        return 'rectangle';
    }

    protected function getShapeData(): array
    {
        return [
            'bounds' => $this->layerState['bounds'] ?? [[0, 0], [0, 0]],
        ];
    }

    protected function getCoordinates(): Coordinate
    {
        $bounds = $this->layerState['bounds'] ?? [[0, 0], [0, 0]];
        // Calcula o centro do retângulo
        $lat1 = $bounds[0][0];
        $lng1 = $bounds[0][1];
        $lat2 = $bounds[1][0];
        $lng2 = $bounds[1][1];

        return new Coordinate(
            ($lat1 + $lat2) / 2,
            ($lng1 + $lng2) / 2,
        );
    }
}
