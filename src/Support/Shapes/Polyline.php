<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Shapes;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\Enums\Color;
use Illuminate\Database\Eloquent\Model;

class Polyline extends Shape
{
    protected array $points = [];

    final public function __construct(array $points = [])
    {
        $this->points = $points;
        
        $this->option('fill', false);
    }

    public static function make(array $points = []): static
    {
        return new static($points);
    }

    public static function fromRecord(
        Model $record,
        string $pointsColumn = 'points',
        ?string $titleColumn = 'title',
        ?string $descriptionColumn = 'description',
        ?array $popupFieldsColumns = null,
        null|string|Color $color = null,
        ?Closure $mapRecordCallback = null
    ): static {
        $points = [];

        if ($record->hasAttribute($pointsColumn)) {
            $value = $record->{$pointsColumn};
            $points = is_string($value) ? json_decode($value, true) : $value;
            $points = is_array($points) ? $points : [];
        }

        return (new static($points))
            ->record($record)
            ->title($record->{$titleColumn} ?? null)
            ->popupContent($record->{$descriptionColumn} ?? null)
            ->popupFields(is_array($popupFieldsColumns) ? $record->only($popupFieldsColumns) : $record->except([
                'id',
                $pointsColumn,
                $titleColumn,
                $descriptionColumn,
                'created_at',
                'updated_at',
            ]))
            ->color($color)
            ->mapRecordUsing($mapRecordCallback);
    }

    public function addPoint(float $latitude, float $longitude): static
    {
        $this->points[] = [$latitude, $longitude];
        return $this;
    }

    /**
     * Define a suavização da linha (smoothFactor).
     */
    public function smoothFactor(float $factor): static
    {
        return $this->option('smoothFactor', $factor);
    }

    public function getType(): string
    {
        return 'polyline';
    }

    protected function getShapeData(): array
    {
        return [
            'points' => $this->points,
        ];
    }

    public function isValid(): bool
    {
        // Uma linha precisa de pelo menos 2 pontos
        return count($this->points) >= 2;
    }

    public function getCoordinates(): array
    {
        if (empty($this->points)) {
            return [0, 0];
        }

        // Calcula o ponto médio da linha
        $latSum = 0;
        $lngSum = 0;
        foreach ($this->points as $point) {
            $latSum += $point[0];
            $lngSum += $point[1];
        }

        return [
            $latSum / count($this->points),
            $lngSum / count($this->points),
        ];
    }
}