<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Groups;

use EduardoRibeiroDev\FilamentLeaflet\Support\BaseLayerGroup;
use EduardoRibeiroDev\FilamentLeaflet\Support\Shapes\Polygon;
use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasColor;
use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasFillColor;

class FeatureGroup extends BaseLayerGroup
{
    use HasColor;
    use HasFillColor;

    /*
    |--------------------------------------------------------------------------
    | Métodos abstratos do Layer Group
    |--------------------------------------------------------------------------
    */

    public function getType(): string
    {
        return 'feature';
    }

    public function getLayers(): array
    {
        $layers = parent::getLayers();

        $points = array_map(fn($layer) => $layer->getCoordinates(), $layers);
        $polygon = Polygon::make($points)
            ->color($this->getColor())
            ->fillColor($this->getFillColor())
            ->options($this->getOptions('polygon'));

        $layers[] = $this->modifyLayerUsing($polygon);

        return $layers;
    }

    /*
    |--------------------------------------------------------------------------
    | Métodos para customização de estilo do Feature Group
    |--------------------------------------------------------------------------
    */

    /**
     * Define a espessura da borda em pixels.
     */
    public function weight(int $weight): static
    {
        return $this->option('weight', $weight, 'polygon');
    }

    /**
     * Define a opacidade da borda (0.0 a 1.0).
     */
    public function opacity(float $opacity): static
    {
        return $this->option('opacity', $opacity, 'polygon');
    }

    /**
     * Define a opacidade do preenchimento (0.0 a 1.0).
     */
    public function fillOpacity(float $opacity): static
    {
        return $this->option('fillOpacity', $opacity, 'polygon');
    }

    /**
     * Define o estilo do traço (tracejado).
     * Ex: '5, 10' (5px linha, 10px espaço).
     */
    public function dashArray(string $dashArray): static
    {
        return $this->option('dashArray', $dashArray, 'polygon');
    }
}
