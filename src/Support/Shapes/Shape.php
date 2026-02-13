<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Shapes;

use EduardoRibeiroDev\FilamentLeaflet\Support\BaseLayer;
use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasColor;
use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasFillColor;
use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasOptions;

abstract class Shape extends BaseLayer
{
    use HasColor;
    use HasFillColor;
    use HasOptions;

    /**
     * Retorna os dados específicos da forma.
     */
    abstract protected function getShapeData(): array;

    /**
     * Define a espessura da borda em pixels.
     */
    public function weight(int $weight): static
    {
        return $this->option('weight', $weight);
    }

    /**
     * Define a opacidade da borda (0.0 a 1.0).
     */
    public function opacity(float $opacity): static
    {
        return $this->option('opacity', $opacity);
    }

    /**
     * Define a opacidade do preenchimento (0.0 a 1.0).
     */
    public function fillOpacity(float $opacity): static
    {
        return $this->option('fillOpacity', $opacity);
    }

    /**
     * Define o estilo do traço (tracejado).
     * Ex: '5, 10' (5px linha, 10px espaço).
     */
    public function dashArray(string $dashArray): static
    {
        return $this->option('dashArray', $dashArray);
    }

    /**
     * Retorna o array de opções visuais mesclado com as cores definidas.
     */
    protected function getShapeOptions(): array
    {
        return array_merge(
            $this->getOptions(),
            array_filter([
                'color' => $this->getHexColor(),
                'fillColor' => $this->getHexFillColor(),
            ])
        );
    }

    protected function getLayerData(): array
    {
        return array_merge(
            $this->getShapeData(),
            ['options' => $this->getShapeOptions()]
        );
    }
}
