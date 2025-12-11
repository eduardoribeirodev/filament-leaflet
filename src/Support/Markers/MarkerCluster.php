<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Markers;

use EduardoRibeiroDev\FilamentLeaflet\Support\Layer;

class MarkerCluster extends Layer
{
    /** @var Marker|array[] */
    protected array $markers = [];
    protected ?string $group = null;

    /**
     * Opções de configuração para o L.markerClusterGroup
     * Veja: https://github.com/Leaflet/Leaflet.markercluster
     */
    protected array $options = [
        'showCoverageOnHover' => true,
        'zoomToBoundsOnClick' => true,
        'spiderfyOnMaxZoom' => true,
        'removeOutsideVisibleBounds' => true,
    ];

    final public function __construct(array $markers = [])
    {
        $this->markers($markers);
    }

    /**
     * Método estático de criação
     */
    public static function make(array $markers = []): static
    {
        return new static($markers);
    }

    /*
    |--------------------------------------------------------------------------
    | Gerenciamento de Marcadores
    |--------------------------------------------------------------------------
    */

    public function getType(): string
    {
        return 'cluster';
    }

    protected function getLayerData(): array
    {
        return [
            'config' => $this->options,
            'markers' => collect($this->markers)->map(function ($marker) {
                if ($marker instanceof Marker) {
                    return $marker->toArray();
                };

                return $marker;
            })->toArray(),
        ];
    }

    public function isValid(): bool
    {
        return true;
    }

    /**
     * Adiciona um único marcador ao cluster.
     */
    public function marker(Marker|array $marker): static
    {
        $this->markers[] = $marker;

        return $this;
    }

    /**
     * Adiciona múltiplos marcadores ao cluster.
     * @param Marker|array[] $markers
     */
    public function markers(array $markers): static
    {
        foreach ($markers as $marker) {
            $this->marker($marker);
        }

        return $this;
    }

    /**
     * Remove todos os marcadores atuais.
     */
    public function clearMarkers(): static
    {
        $this->markers = [];

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Configurações do Cluster (Leaflet.markercluster options)
    |--------------------------------------------------------------------------
    */

    /**
     * O raio máximo (em pixels) que um cluster cobrirá.
     */
    public function maxClusterRadius(int $radius): static
    {
        $this->options['maxClusterRadius'] = $radius;

        return $this;
    }

    /**
     * Se true, mostra os limites do polígono do cluster ao passar o mouse.
     */
    public function showCoverageOnHover(bool $show = true): static
    {
        $this->options['showCoverageOnHover'] = $show;

        return $this;
    }

    /**
     * Se true, ao clicar no cluster, o mapa fará zoom até os limites.
     */
    public function zoomToBoundsOnClick(bool $zoom = true): static
    {
        $this->options['zoomToBoundsOnClick'] = $zoom;

        return $this;
    }

    /**
     * Se true, ao atingir o zoom máximo, os marcadores se espalharão (spiderfy).
     */
    public function spiderfyOnMaxZoom(bool $spiderfy = true): static
    {
        $this->options['spiderfyOnMaxZoom'] = $spiderfy;

        return $this;
    }

    /**
     * Se true, remove clusters/marcadores que estão fora da view atual para performance.
     */
    public function removeOutsideVisibleBounds(bool $remove = true): static
    {
        $this->options['removeOutsideVisibleBounds'] = $remove;

        return $this;
    }

    /**
     * Define o nível de zoom onde o cluster é desativado (todos os marcadores aparecem).
     */
    public function disableClusteringAtZoom(int $zoomLevel): static
    {
        $this->options['disableClusteringAtZoom'] = $zoomLevel;

        return $this;
    }

    /**
     * Permite definir qualquer opção arbitrária do plugin JS.
     */
    public function option(string $key, mixed $value): static
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Substitui ou mescla todas as opções.
     */
    public function options(array $options, bool $merge = true): static
    {
        if ($merge) {
            $this->options = array_merge($this->options, $options);
        } else {
            $this->options = $options;
        }

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Getters
    |--------------------------------------------------------------------------
    */

    public function getMarkers(): array
    {
        return $this->markers;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function count(): int
    {
        return count($this->markers);
    }
}
