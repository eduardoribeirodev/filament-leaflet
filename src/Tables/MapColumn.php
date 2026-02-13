<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Tables;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasMapState;
use EduardoRibeiroDev\FilamentLeaflet\Support\Markers\Marker;
use Filament\Tables\Columns\Column;

class MapColumn extends Column
{
    use HasMapState {
        getMapHeight as getParentMapHeight;
        getCustomStyles as getParentCustomStyles;
    }

    protected string $view = 'filament-leaflet::tables.map-column';
    protected bool $isCircular = false;

    public function circular(Closure|bool $value = true): static
    {
        $this->isCircular = (bool) $this->evaluate($value);
        return $this;
    }

    public function getIsCircular(): bool
    {
        return $this->isCircular;
    }

    public function getId()
    {
        $json = json_encode($this->getState());
        return md5($json);
    }

    protected function getMapControls(): array
    {
        return [];
    }

    public function getState(): mixed
    {
        $record = $this->getRecord();
        if (!$record) return null;

        if ($this->storeAsJson) {
            return $record->{$this->getName()};
        }

        return [
            $this->latitudeFieldName => $record->{$this->latitudeFieldName},
            $this->longitudeFieldName => $record->{$this->longitudeFieldName}
        ];
    }

    public function getWidth(): ?string
    {
        $parentWidth = $this->evaluate($this->width);
        $parentHeight = $this->getParentMapHeight() + 10; // por algum motivo para a proporção 1:1 a largura precisa ser 10px maior que a altura

        if ($this->isCircular && $parentHeight < $parentWidth) {
            $this->width($parentHeight);
        }

        return parent::getWidth();
    }

    protected function getMapHeight(): int
    {
        $parentWidth = $this->evaluate($this->width) - 10; // por algum motivo para a proporção 1:1 a largura precisa ser 10px maior que a altura
        $parentHeight = $this->getParentMapHeight();

        if ($this->isCircular && $parentWidth < $parentHeight) {
            $this->height($parentWidth);
        }

        return $this->getParentMapHeight();
    }

    public function getCustomStyles(): string
    {
        $styles = $this->getParentCustomStyles();

        if ($this->isCircular) {
            $styles .= ".fi-ta-col .leaflet-container { border-radius: 50%; }";
        }

        return $styles;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->height(72);
        $this->width(108);
        $this->zoom(5);
        $this->recenterTimeout(3000);
        $this->minZoom(0);
        $this->pickMarker(fn(Marker $marker) => $marker->icon(size: [14, 25]));
    }
}
