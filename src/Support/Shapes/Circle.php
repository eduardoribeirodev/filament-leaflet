<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Shapes;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\Enums\Color;
use Illuminate\Database\Eloquent\Model;

class Circle extends Shape
{
    protected array $center; // [lat, lng]
    protected float $radius = 50000;

    final public function __construct(float $latitude, float $longitude)
    {
        $this->center = [$latitude, $longitude];
    }

    public static function make(float $latitude, float $longitude): static
    {
        return new static($latitude, $longitude);
    }

    public static function fromRecord(
        Model $record,
        string $latColumn = 'latitude',
        string $lngColumn = 'longitude',
        ?string $jsonColumn = null,
        ?string $titleColumn = 'title',
        ?string $descriptionColumn = 'description',
        ?array $popupFieldsColumns = null,
        null|string|Color $color = null,
        ?Closure $mapRecordCallback = null
    ): static {
        $lat = 0;
        $lng = 0;

        if ($jsonColumn) {
            $coords = $record->{$jsonColumn};
            $coords = is_string($coords) ? json_decode($coords, true) : $coords;

            $lat = $coords[$latColumn] ?? 0;
            $lng = $coords[$lngColumn] ?? 0;
        } else {
            $lat = $record->{$latColumn} ?? 0;
            $lng = $record->{$lngColumn} ?? 0;
        }

        return (new static($lat, $lng))
            ->record($record)
            ->title($record->{$titleColumn} ?? null)
            ->popupContent($record->{$descriptionColumn} ?? null)
            ->popupFields(is_array($popupFieldsColumns) ? $record->only($popupFieldsColumns) : $record->except([
                'id',
                $latColumn,
                $lngColumn,
                $jsonColumn,
                $titleColumn,
                $descriptionColumn,
                'created_at',
                'updated_at',
            ]))
            ->color($color)
            ->mapRecordUsing($mapRecordCallback);
    }

    /*
    |--------------------------------------------------------------------------
    | Radius Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Define o raio diretamente em Metros (padrão do Leaflet).
     */
    public function radius(float $meters): static
    {
        $this->radius = $meters;
        return $this;
    }

    public function radiusInMeters(float $meters): static
    {
        return $this->radius($meters);
    }

    public function radiusInKilometers(float $km): static
    {
        return $this->radius($km * 1000);
    }

    public function radiusInMiles(float $miles): static
    {
        return $this->radius($miles * 1609.344);
    }

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
            'center' => $this->center,
        ];
    }

    protected function getShapeOptions(): array
    {
        return array_merge(
            parent::getShapeOptions(),
            ['radius' => $this->radius]
        );
    }

    public function isValid(): bool
    {
        return count($this->center) === 2 &&
            $this->center[0] >= -90 && $this->center[0] <= 90 &&
            $this->center[1] >= -180 && $this->center[1] <= 180 &&
            $this->radius > 0;
    }

    public function getCoordinates(): array
    {
        return $this->center;
    }
}