<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Concerns;

use Closure;

trait HasPath
{
    protected null|Closure|int $weight = null;
    protected null|Closure|float $smoothFactor = null;
    protected null|Closure|array $dashArray = null;
    protected null|Closure|string $dashOffset = null;
    protected null|Closure|bool $stroke = true;
    protected null|Closure|string $lineCap = null;
    protected null|Closure|string $lineJoin = null;
    protected null|Closure|bool $fill = true;
    protected null|Closure|string $fillRule = null;
    protected null|Closure|bool $noClip = null;
    protected null|Closure|bool $bubblingMouseEvents = null;

    /**
     * Set the weight (thickness) of the shape's border.
     * @param Closure|null|int $weight
     * @return $this
     */
    public function weight(null|Closure|int $weight): static
    {
        $this->weight = $weight;
        return $this;
    }

    /**
     * Get the weight (thickness) of the shape's border.
     * @return int|null
     */
    public function getWeight(): ?int
    {
        return $this->evaluate($this->weight);
    }

    /**
     * Set the smoothing factor for the shape's path. Higher values mean better performance but worse appearance.
     * @param Closure|null|float $smoothFactor
     * @return $this
     */
    public function smoothFactor(null|Closure|float $smoothFactor): static
    {
        $this->smoothFactor = $smoothFactor;
        return $this;
    }

    /**
     * Get the smoothing factor for the shape's path.
     * @return float|null
     */
    public function getSmoothFactor(): ?float
    {
        return $this->evaluate($this->smoothFactor);
    }

    /**
     * Set the dash array for the shape's border.
     * @param Closure|null|int $dashArray An array of dash and gap lengths in pixels.
     * @return $this
     * @example $shape->dashArray(5, 10); // 5px dash followed by 10px gap.
     * @example $shape->dashArray(5, 10, 2, 10); // 5px dash, 10px gap, 2px dash, 10px gap.
     */
    public function dashArray(null|Closure|int|string ...$dashArray): static
    {
        $this->dashArray = $dashArray;
        return $this;
    }

    /**
     * Get the dash array for the shape's border.
     * @return array|null
     */
    public function getDashArray(): ?array
    {
        $arr = $this->dashArray;
        if ($arr === null) {
            return null;
        }

        return array_map(fn($dash) => $this->evaluate($dash), $arr);
    }

    /**
     * Set the distance into the dash pattern to start the dash. Corresponds to the SVG `stroke-dashoffset` attribute.
     * @param Closure|null|string $dashOffset
     * @return $this
     * @example $shape->dashOffset('5');
     */
    public function dashOffset(null|Closure|string $dashOffset): static
    {
        $this->dashOffset = $dashOffset;
        return $this;
    }

    /**
     * Get the distance into the dash pattern to start the dash.
     * @return string|null
     */
    public function getDashOffset(): ?string
    {
        return $this->evaluate($this->dashOffset);
    }

    /**
     * Set whether to draw the border of the shape. Disable this to disable borders on polygons or circles.
     * @param Closure|null|bool $stroke
     * @return $this
     */
    public function stroke(null|Closure|bool $stroke = true): static
    {
        $this->stroke = $stroke;
        return $this;
    }

    /**
     * Get whether the border of the shape is drawn.
     * @return bool|null
     */
    public function getStroke(): ?bool
    {
        return $this->evaluate($this->stroke);
    }

    /**
     * Set the shape to be used at the end of each sub-path stroke. Corresponds to the SVG `stroke-linecap` attribute.
     * @param Closure|null|string $lineCap Accepted values: 'butt', 'round', 'square'.
     * @return $this
     */
    public function lineCap(null|Closure|string $lineCap): static
    {
        $this->lineCap = $lineCap;
        return $this;
    }

    /**
     * Get the shape used at the end of each sub-path stroke.
     * @return string|null
     */
    public function getLineCap(): ?string
    {
        return $this->evaluate($this->lineCap);
    }

    /**
     * Set the shape to be used at the corners of the path's stroke. Corresponds to the SVG `stroke-linejoin` attribute.
     * @param Closure|null|string $lineJoin Accepted values: 'miter', 'round', 'bevel'.
     * @return $this
     */
    public function lineJoin(null|Closure|string $lineJoin): static
    {
        $this->lineJoin = $lineJoin;
        return $this;
    }

    /**
     * Get the shape used at the corners of the path's stroke.
     * @return string|null
     */
    public function getLineJoin(): ?string
    {
        return $this->evaluate($this->lineJoin);
    }

    /**
     * Set whether to fill the shape with color. Disable this to disable filling on polygons or circles.
     * @param Closure|null|bool $fill
     * @return $this
     */
    public function fill(null|Closure|bool $fill = true): static
    {
        $this->fill = $fill;
        return $this;
    }

    /**
     * Get whether the shape is filled with color.
     * @return bool|null
     */
    public function getFill(): ?bool
    {
        return $this->evaluate($this->fill);
    }

    /**
     * Set the fill rule that determines how the interior of the shape is defined.
     * Corresponds to the SVG `fill-rule` attribute.
     * @param Closure|null|string $fillRule Accepted values: 'nonzero', 'evenodd'.
     * @return $this
     */
    public function fillRule(null|Closure|string $fillRule): static
    {
        $this->fillRule = $fillRule;
        return $this;
    }

    /**
     * Get the fill rule that determines how the interior of the shape is defined.
     * @return string|null
     */
    public function getFillRule(): ?string
    {
        return $this->evaluate($this->fillRule);
    }

    /**
     * Disable or enable Leaflet's path clipping. Can be useful when rendering artifacts appear on edge cases.
     * @param Closure|null|bool $noClip
     * @return $this
     */
    public function noClip(null|Closure|bool $noClip = true): static
    {
        $this->noClip = $noClip;
        return $this;
    }

    /**
     * Get whether Leaflet's path clipping is disabled.
     * @return bool|null
     */
    public function getNoClip(): ?bool
    {
        return $this->evaluate($this->noClip);
    }
}