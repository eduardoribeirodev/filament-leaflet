<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Markers;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\Enums\Color;
use EduardoRibeiroDev\FilamentLeaflet\Support\BaseLayer;
use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasColor;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class Marker extends BaseLayer
{
    use HasColor;

    protected float $latitude;
    protected float $longitude;
    protected bool $isDraggable = false;

    // Configurações de Ícone
    protected ?string $iconUrl = null;
    protected array $iconSize = [25, 41];


    final public function __construct(float $latitude = 0, float $longitude = 0)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
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
        ?string $iconUrl = null,
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
            ->icon($iconUrl)
            ->mapRecordUsing($mapRecordCallback);
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
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'color' => $this->color,
            'icon' => $this->iconUrl,
            'iconSize' => $this->iconSize,
            'draggable' => $this->isDraggable,
        ];
    }

    public function isValid(): bool
    {
        return $this->latitude >= -90 && $this->latitude <= 90 &&
            $this->longitude >= -180 && $this->longitude <= 180;
    }

    protected function getDeterministicIdData(): string
    {
        if ($this->record) {
            return $this->record->getTable() . '-' . ($this->record->getKey() ?? $this->latitude . ',' . $this->longitude);
        }

        return parent::getDeterministicIdData();
    }

    /*
    |--------------------------------------------------------------------------
    | Métodos do Marker
    |--------------------------------------------------------------------------
    */

    public function iconUrl(null|Closure|string $url = null): static
    {
        $this->iconUrl = $this->evaluate($url);
        return $this;
    }

    public function iconSize(Closure|array $size = [25, 41]): static
    {
        $this->iconSize = (array) $this->evaluate($size);
        return $this;
    }

    public function icon(null|Closure|string $url = null, Closure|array $size = [25, 41]): static
    {
        $this->iconUrl($url);
        $this->iconSize($size);
        return $this;
    }

    public function getIconUrl()
    {
        return $this->iconUrl;
    }

    public function getIconSize()
    {
        return $this->iconSize;
    }

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

    public function getCoordinates(): array
    {
        return [$this->latitude, $this->longitude];
    }

    /**
     * Lança exceção se coordenadas forem inválidas.
     */
    public function validate(): static
    {
        if (!$this->isValid()) {
            throw new InvalidArgumentException(
                "Invalid coordinates: lat={$this->latitude}, lng={$this->longitude}"
            );
        }

        return $this;
    }

    /**
     * Calcula a distância Haversine até outro marcador (em KM).
     */
    public function distanceTo(Marker $target): float
    {
        $earthRadius = 6371;

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($target->latitude);
        $lonTo = deg2rad($target->longitude);

        $latDiff = $latTo - $latFrom;
        $lonDiff = $lonTo - $lonFrom;

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
            cos($latFrom) * cos($latTo) *
            sin($lonDiff / 2) * sin($lonDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
