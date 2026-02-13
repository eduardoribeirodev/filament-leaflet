<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Shapes;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\Enums\Color;
use Illuminate\Database\Eloquent\Model;

class Rectangle extends Shape
{
    protected array $bounds;

    /**
     * @param array $corner1 Coordenada [lat, lng] do primeiro canto
     * @param array $corner2 Coordenada [lat, lng] do canto oposto
     */
    final public function __construct(array $corner1, array $corner2)
    {
        $this->bounds = [$corner1, $corner2];
    }

    public static function make(array $corner1, array $corner2): static
    {
        return new static($corner1, $corner2);
    }
    
    /**
     * Construtor alternativo usando coordenadas soltas.
     */
    public static function makeFromCoordinates(float $lat1, float $lng1, float $lat2, float $lng2): static
    {
        return new static([$lat1, $lng1], [$lat2, $lng2]);
    }

    public static function fromRecord(
        Model $record,
        string $boundsColumn = 'bounds',
        ?string $titleColumn = 'title',
        ?string $descriptionColumn = 'description',
        ?array $popupFieldsColumns = null,
        null|string|Color $color = null,
        ?Closure $mapRecordCallback = null
    ): static {
        $bounds = [[0, 0], [0, 0]];

        if ($record->hasAttribute($boundsColumn)) {
            $value = $record->{$boundsColumn};
            $parsed = is_string($value) ? json_decode($value, true) : $value;
            if (is_array($parsed) && count($parsed) === 2) {
                $bounds = $parsed;
            }
        }

        return (new static($bounds[0], $bounds[1]))
            ->record($record)
            ->title($record->{$titleColumn} ?? null)
            ->popupContent($record->{$descriptionColumn} ?? null)
            ->popupFields(is_array($popupFieldsColumns) ? $record->only($popupFieldsColumns) : $record->except([
                'id',
                $boundsColumn,
                $titleColumn,
                $descriptionColumn,
                'created_at',
                'updated_at',
            ]))
            ->color($color)
            ->mapRecordUsing($mapRecordCallback);
    }

    public function getType(): string
    {
        return 'rectangle';
    }

    protected function getShapeData(): array
    {
        return [
            'bounds' => $this->bounds,
        ];
    }

    public function isValid(): bool
    {
        return count($this->bounds) === 2 
            && count($this->bounds[0]) === 2 
            && count($this->bounds[1]) === 2;
    }

    public function getCoordinates(): array
    {
        // Calcula o centro do retângulo
        $lat1 = $this->bounds[0][0];
        $lng1 = $this->bounds[0][1];
        $lat2 = $this->bounds[1][0];
        $lng2 = $this->bounds[1][1];

        return [
            ($lat1 + $lat2) / 2,
            ($lng1 + $lng2) / 2,
        ];
    }
}