<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Layers\Boundaries;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasFillColor;
use EduardoRibeiroDev\FilamentLeaflet\Concerns\HasPath;
use EduardoRibeiroDev\FilamentLeaflet\Layers\BaseLayer;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\Coordinate;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\GeoJson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class Boundary extends BaseLayer
{
    use HasFillColor;
    use HasPath;

    protected array $source = [];
    protected null|Closure|string $featureKey = null;
    protected null|Closure|string $featureLabel = null;
    protected Closure|array|null $values = null;
    protected Closure|array $colors = ['#FED976', '#FEB24C', '#FD8D3C', '#FC4E2A', '#E31A1C', '#BD0026', '#800026'];
    protected null|Closure|string $missingColor = '#CCCCCC';

    public function getType(): string
    {
        return 'geojson';
    }

    public function fromUrl(string|Closure $url): static
    {
        $this->source = ['type' => 'url', 'value' => $url];
        return $this;
    }

    public function fromStorage(string|Closure $path, string|Closure|null $disk = null): static
    {
        $this->source = ['type' => 'storage', 'value' => $path, 'disk' => $disk];
        return $this;
    }

    public function fromGeoJson(array|Closure $geoJson): static
    {
        $this->source = ['type' => 'inline', 'value' => $geoJson];
        return $this;
    }

    public function features(null|string|Closure $key = null, null|string|Closure $label = 'name'): static
    {
        return $this->featureKey($key)->featureLabel($label);
    }

    public function featureKey(null|string|Closure $key): static
    {
        $this->featureKey = $key;
        return $this;
    }

    public function featureLabel(null|string|Closure $label): static
    {
        $this->featureLabel = $label;
        return $this;
    }

    public function values(array|Closure $values): static
    {
        $this->values = $values;
        return $this;
    }

    public function colorScale(array|Closure $colors, string|Closure|null $missingColor = null): static
    {
        $this->colors = $colors;
        if ($missingColor !== null) {
            $this->missingColor = $missingColor;
        }
        return $this;
    }

    public function choropleth(array|Closure $values, array|Closure $colors, string|Closure|null $missingColor = null): static
    {
        return $this->values($values)->colorScale($colors, $missingColor);
    }

    public function getSource(): array
    {
        if ($this->source === []) {
            throw new InvalidArgumentException('A Boundary requires a GeoJSON source. Call fromUrl(), fromStorage(), or fromGeoJson().');
        }

        $type = $this->source['type'];
        $value = $this->evaluate($this->source['value']);

        if ($type === 'storage') {
            /** @var \Illuminate\Filesystem\FilesystemAdapter */
            $storage = Storage::disk($this->evaluate($this->source['disk']));

            $value = $storage->exists($value)
                ? $storage->url($value)
                : asset('storage/' . $value);

            $type = 'url';
        }

        return ['type' => $type, 'value' => $value];
    }

    protected function getLayerData(): array
    {
        $values = $this->getValues();

        return [
            'source' => $this->getSource(),
            'features' => [
                'key' => $this->getFeatureKey(),
                'label' => $this->getFeatureLabel(),
            ],
            'visualization' => $values === [] ? null : $this->getVisualization($values),
            'style' => [
                'color' => $this->getColor(),
                'weight' => $this->getWeight(),
                'opacity' => $this->getOpacity(),
                'fillColor' => $this->getFillColor(),
                'fillOpacity' => $this->getFillOpacity(),
                'dashArray' => $this->getDashArray(),
                'lineCap' => $this->getLineCap(),
                'lineJoin' => $this->getLineJoin(),
                'stroke' => $this->getStroke(),
                'fill' => $this->getFill(),
                'fillRule' => $this->getFillRule(),
            ],
        ];
    }

    protected function getVisualization(array $values): array
    {
        return [
            'type' => 'choropleth',
            'values' => $values,
            'colors' => $this->getColors(),
            'missingColor' => $this->getMissingColor(),
        ];
    }

    protected function getValues(): array
    {
        return (array) $this->evaluate($this->values);
    }

    protected function getFeatureKey(): null|string
    {
        return $this->evaluate($this->featureKey);
    }

    protected function getFeatureLabel(): null|string
    {
        return $this->evaluate($this->featureLabel);
    }

    protected function getColors(): array
    {
        return array_values((array) $this->evaluate($this->colors));
    }

    protected function getMissingColor(): null|string
    {
        return $this->evaluate($this->missingColor);
    }

    protected function getCoordinates(): Coordinate
    {
        return new Coordinate(0, 0);
    }

    public static function fromRecord(Model $record, ?string $geoJsonColumn = null, ?string $titleColumn = null, ?string $descriptionColumn = null, ?array $popupFieldsColumns = null, null|string|array $color = null, ?bool $syncRecord = null, ?Closure $mapRecordCallback = null): static
    {
        $geoJsonColumn ??= config('filament-leaflet.columns.geojson', 'geojson');
        $geoJson = $record->getAttribute($geoJsonColumn);

        if (!$geoJson instanceof GeoJson) {
            throw new InvalidArgumentException("The '{$geoJsonColumn}' attribute must be cast to " . GeoJson::class . '.');
        }

        $instance = static::makeFromRecord(
            record: $record,
            instanceParameters: [],
            recordColumns: ['geojson' => $geoJsonColumn, 'title' => $titleColumn, 'description' => $descriptionColumn],
            syncAttributes: $syncRecord,
            jsonColumn: $geoJsonColumn,
            popupFieldsColumns: $popupFieldsColumns,
            color: $color,
            mapRecordCallback: $mapRecordCallback,
        );

        return filter_var($geoJson->getFilePath(), FILTER_VALIDATE_URL)
            ? $instance->fromUrl($geoJson->getFilePath())
            : $instance->fromStorage($geoJson->getFilePath(), $geoJson->getDisk());
    }

    public function getDefaultColor(): string|array
    {
        return '#FFFFFF';
    }
    public function getDefaultFillColor(): string|array
    {
        return '#CCCCCC';
    }
    public function getDefaultFillOpacity(): float
    {
        return 0.8;
    }
}
