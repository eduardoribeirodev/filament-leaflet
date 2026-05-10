<?php

namespace EduardoRibeiroDev\FilamentLeaflet\LayerGroups;

use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasFillColor;
use EduardoRibeiroDev\FilamentLeaflet\LayerGroups\BaseLayerGroup;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Shapes\Polygon;
use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasPath;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\Coordinate;

class FeatureGroup extends BaseLayerGroup
{
    use HasPath;
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

        $boundaryPointsObjects = count($points) > 3
            ? $this->getConvexHull($points)
            : $points;

        $boundaryPointsArray = array_map(fn($coord) => $coord->toArray(), $boundaryPointsObjects);

        $polygon = Polygon::make($boundaryPointsArray)
            ->color($this->getColor())
            ->fillColor($this->getFillColor())
            ->opacity($this->getOpacity())
            ->fillOpacity($this->getFillOpacity())
            ->weight($this->getWeight())
            ->smoothFactor($this->getSmoothFactor())
            ->dashArray(...$this->getDashArray())
            ->dashOffset($this->getDashOffset())
            ->stroke($this->getStroke())
            ->lineCap($this->getLineCap())
            ->lineJoin($this->getLineJoin())
            ->fill($this->getFill())
            ->fillRule($this->getFillRule())
            ->noClip($this->getNoClip());

        $layers[] = $this->modifyLayerUsing($polygon);

        return $layers;
    }

    /**
     * @param Coordinate[] $points
     * @return Coordinate[]
     */
    private function getConvexHull(array $points): array
    {
        usort($points, function (Coordinate $a, Coordinate $b) {
            return $a->lat <=> $b->lat ?: $a->lng <=> $b->lng;
        });

        $lower = [];
        foreach ($points as $p) {
            while (count($lower) >= 2 && $this->crossProduct($lower[count($lower) - 2], $lower[count($lower) - 1], $p) <= 0) {
                array_pop($lower);
            }
            $lower[] = $p;
        }

        $upper = [];
        for ($i = count($points) - 1; $i >= 0; $i--) {
            $p = $points[$i];
            while (count($upper) >= 2 && $this->crossProduct($upper[count($upper) - 2], $upper[count($upper) - 1], $p) <= 0) {
                array_pop($upper);
            }
            $upper[] = $p;
        }

        array_pop($upper);
        array_pop($lower);

        return array_merge($lower, $upper);
    }

    private function crossProduct(Coordinate $o, Coordinate $a, Coordinate $b): float
    {
        return ($a->lat - $o->lat) * ($b->lng - $o->lng) - ($a->lng - $o->lng) * ($b->lat - $o->lat);
    }

    /*
    |--------------------------------------------------------------------------
    | Métodos para customização de estilo do Feature Group
    |--------------------------------------------------------------------------
    */

    public function getDefaultFillOpacity(): float
    {
        return 0.35;
    }
}
