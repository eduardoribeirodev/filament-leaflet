<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Layers\Shapes;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\Coordinate;
use Illuminate\Database\Eloquent\Model;

class Polyline extends Shape
{
    protected ?string $pointsColumn = null;

    final public function __construct(array ...$points)
    {
        $this->layerState['points'] = $points;
    }

    /**
     * Convenience method to create a Polyline instance with given points.
     * @param array ...$points Variable number of arrays, each representing a point as [latitude, longitude]. Can also accept a single array of points (e.g. make([[-15.0, -50.0], [-15.1, -50.1]])).
     * @return static A new Polyline instance with the specified points.
     */
    public static function make(array ...$points): static
    {
        return new static(...(count($points) == 1 ? $points[0] : $points));
    }

    /**
     * Create a Polyline instance from an Eloquent record. The method will attempt to extract the polyline points from the specified $pointsColumn, which can be a JSON string or an array. It will also set the title, description, popup fields, and color based on the provided parameters and the record's attributes.
     * @param Model $record The Eloquent model record to create the polyline from.
     * @param string|null $pointsColumn The column name for the polyline points.
     * @param string|null $titleColumn Optional column name for polyline title.
     * @param string|null $descriptionColumn Optional column name for polyline description.
     * @param array|null $popupFieldsColumns Optional array of column names to include in popup.
     * @param string|array|null $color Optional polyline color.
     * @param bool|null $syncRecord Whether to sync changes back to the record when the shape is edited on the map.
     * @param Closure|null $mapRecordCallback Optional Closure to further customize the polyline based on the record.
     * @return static A new Polyline instance configured based on the provided record.
     */
    public static function fromRecord(
        Model $record,
        ?string $pointsColumn = null,
        ?string $titleColumn = null,
        ?string $descriptionColumn = null,
        ?array $popupFieldsColumns = null,
        string|array|null $color = null,
        ?bool $syncRecord = null,
        ?Closure $mapRecordCallback = null
    ): static {
        $points = $record->{$pointsColumn} ?? [];

        return static::makeFromRecord(
            record: $record,
            instanceParameters: is_string($points) ? json_decode($points, true) : $points,
            recordColumns: [
                'points' => $pointsColumn,
                'title' => $titleColumn,
                'description' => $descriptionColumn,
            ],
            syncAttributes: $syncRecord,
            jsonColumn: $pointsColumn,
            popupFieldsColumns: $popupFieldsColumns,
            color: $color,
            mapRecordCallback: $mapRecordCallback
        );
    }

    /**
     * Add a point to the polyline. The $latitude and $longitude parameters specify the coordinates of the point to be added. This method appends the new point to the existing list of points that define the polyline.
     * @param float $latitude The latitude of the point to be added to the polyline.
     * @param float $longitude The longitude of the point to be added to the polyline.
     * @return $this The current Polyline instance with the new point added.
     */
    public function addPoint(float $latitude, float $longitude): static
    {
        $this->layerState['points'][] = [$latitude, $longitude];
        return $this;
    }

    public function getType(): string
    {
        return 'polyline';
    }

    protected function getShapeData(): array
    {
        return [
            'points' => $this->layerState['points'] ?? [],
        ];
    }

    protected function getCoordinates(): Coordinate
    {
        if (empty($this->layerState['points'])) {
            return new Coordinate(0, 0);
        }

        // Calcula o ponto médio da linha
        $latSum = 0;
        $lngSum = 0;
        foreach ($this->layerState['points'] as $point) {
            $latSum += $point[0];
            $lngSum += $point[1];
        }

        return new Coordinate(
            $latSum / count($this->layerState['points']),
            $lngSum / count($this->layerState['points']),
        );
    }
}
