<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Tables;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasMapState;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Marker;
use Filament\Tables\Columns\Column;

class MapColumn extends Column
{
    use HasMapState;

    protected string $view = 'filament-leaflet::tables.map-column';
    protected Closure|bool $isCircular = false;

    public function circular(Closure|bool $value = true): static
    {
        $this->isCircular = $value;
        return $this;
    }

    public function getIsCircular(): bool
    {
        return (bool) $this->evaluate($this->isCircular);
    }

    public function getId()
    {
        $json = json_encode($this->getState() ?? uniqid());
        return md5($json);
    }

    protected function getMapControls(): array
    {
        return [];
    }

    public function getWidth(): ?string
    {
        return parent::getWidth();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->height(86);
        $this->width(86);
        $this->zoom(5);
        $this->recenterTimeout(5000);
        $this->minZoom(0);
        $this->defaultPickMarker(Marker::make()->icon(size: [14, 25]));
    }
}
