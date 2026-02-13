<?php

declare(strict_types=1);

namespace App\Support\Geo;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * KmlExporter - Export application data to KML format.
 *
 * @example
 * $exporter = new KmlExporter();
 * $kml = $exporter
 *     ->fromModel(Location::class)
 *     ->name('My Locations')
 *     ->geometryColumn('location')
 *     ->nameColumn('name')
 *     ->descriptionColumn('description')
 *     ->export();
 */
class KmlExporter
{
    /**
     * Source data.
     */
    protected Collection|EloquentCollection|null $data = null;

    /**
     * Document name.
     */
    protected string $documentName = 'Export';

    /**
     * Document description.
     */
    protected string $documentDescription = '';

    /**
     * Geometry column name.
     */
    protected string $geometryColumn = 'location';

    /**
     * Name column/property.
     */
    protected string $nameColumn = 'name';

    /**
     * Description column/property.
     */
    protected string $descriptionColumn = 'description';

    /**
     * Properties to include in extended data.
     */
    protected array $extendedDataColumns = [];

    /**
     * Custom styles.
     */
    protected array $styles = [];

    /**
     * Default style ID.
     */
    protected ?string $defaultStyleId = null;

    /**
     * Style callback for dynamic styling.
     */
    protected ?\Closure $styleCallback = null;

    /**
     * Load data from an Eloquent model.
     *
     * @param  class-string<Model>  $modelClass
     * @param  \Closure|null  $query  Optional query modifier
     */
    public function fromModel(string $modelClass, ?\Closure $query = null): static
    {
        $builder = $modelClass::query();

        if ($query) {
            $query($builder);
        }

        $this->data = $builder->get();

        return $this;
    }

    /**
     * Load data from a collection.
     */
    public function fromCollection(Collection|EloquentCollection|array $data): static
    {
        $this->data = $data instanceof Collection ? $data : collect($data);

        return $this;
    }

    /**
     * Set document name.
     */
    public function name(string $name): static
    {
        $this->documentName = $name;

        return $this;
    }

    /**
     * Set document description.
     */
    public function description(string $description): static
    {
        $this->documentDescription = $description;

        return $this;
    }

    /**
     * Set geometry column.
     */
    public function geometryColumn(string $column): static
    {
        $this->geometryColumn = $column;

        return $this;
    }

    /**
     * Set name column.
     */
    public function nameColumn(string $column): static
    {
        $this->nameColumn = $column;

        return $this;
    }

    /**
     * Set description column.
     */
    public function descriptionColumn(string $column): static
    {
        $this->descriptionColumn = $column;

        return $this;
    }

    /**
     * Set extended data columns.
     */
    public function extendedData(array $columns): static
    {
        $this->extendedDataColumns = $columns;

        return $this;
    }

    /**
     * Add a point style.
     */
    public function addPointStyle(
        string $id,
        string $iconUrl = 'http://maps.google.com/mapfiles/kml/pushpin/ylw-pushpin.png',
        float $scale = 1.0,
        string $color = 'ff0000ff'
    ): static {
        $this->styles[$id] = [
            'type' => 'point',
            'iconUrl' => $iconUrl,
            'scale' => $scale,
            'color' => $color,
        ];

        return $this;
    }

    /**
     * Add a line style.
     */
    public function addLineStyle(string $id, string $color = 'ff0000ff', float $width = 2.0): static
    {
        $this->styles[$id] = [
            'type' => 'line',
            'color' => $color,
            'width' => $width,
        ];

        return $this;
    }

    /**
     * Add a polygon style.
     */
    public function addPolygonStyle(
        string $id,
        string $fillColor = '7f0000ff',
        string $lineColor = 'ff0000ff',
        float $lineWidth = 1.0,
        bool $fill = true,
        bool $outline = true
    ): static {
        $this->styles[$id] = [
            'type' => 'polygon',
            'fillColor' => $fillColor,
            'lineColor' => $lineColor,
            'lineWidth' => $lineWidth,
            'fill' => $fill,
            'outline' => $outline,
        ];

        return $this;
    }

    /**
     * Set default style ID.
     */
    public function defaultStyle(string $styleId): static
    {
        $this->defaultStyleId = $styleId;

        return $this;
    }

    /**
     * Set dynamic style callback.
     *
     * @param  \Closure(mixed $item): ?string  $callback  Returns style ID or null
     */
    public function styleCallback(\Closure $callback): static
    {
        $this->styleCallback = $callback;

        return $this;
    }

    /**
     * Export to KML string.
     */
    public function export(): string
    {
        if ($this->data === null) {
            throw new \RuntimeException('No data loaded. Call fromModel() or fromCollection() first.');
        }

        $xml = new \XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');

        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('kml');
        $xml->writeAttribute('xmlns', 'http://www.opengis.net/kml/2.2');

        $xml->startElement('Document');
        $xml->writeElement('name', $this->documentName);

        if ($this->documentDescription) {
            $xml->writeElement('description', $this->documentDescription);
        }

        // Write styles
        $this->writeStyles($xml);

        // Write placemarks
        foreach ($this->data as $item) {
            $this->writePlacemark($xml, $item);
        }

        $xml->endElement(); // Document
        $xml->endElement(); // kml

        return $xml->outputMemory();
    }

    /**
     * Write style definitions.
     */
    protected function writeStyles(\XMLWriter $xml): void
    {
        foreach ($this->styles as $id => $style) {
            $xml->startElement('Style');
            $xml->writeAttribute('id', $id);

            if ($style['type'] === 'point') {
                $xml->startElement('IconStyle');
                $xml->writeElement('scale', (string) $style['scale']);
                $xml->writeElement('color', $style['color']);
                $xml->startElement('Icon');
                $xml->writeElement('href', $style['iconUrl']);
                $xml->endElement(); // Icon
                $xml->endElement(); // IconStyle
            } elseif ($style['type'] === 'line') {
                $xml->startElement('LineStyle');
                $xml->writeElement('color', $style['color']);
                $xml->writeElement('width', (string) $style['width']);
                $xml->endElement(); // LineStyle
            } elseif ($style['type'] === 'polygon') {
                $xml->startElement('LineStyle');
                $xml->writeElement('color', $style['lineColor']);
                $xml->writeElement('width', (string) $style['lineWidth']);
                $xml->endElement(); // LineStyle
                $xml->startElement('PolyStyle');
                $xml->writeElement('color', $style['fillColor']);
                $xml->writeElement('fill', $style['fill'] ? '1' : '0');
                $xml->writeElement('outline', $style['outline'] ? '1' : '0');
                $xml->endElement(); // PolyStyle
            }

            $xml->endElement(); // Style
        }
    }

    /**
     * Write a single placemark.
     */
    protected function writePlacemark(\XMLWriter $xml, mixed $item): void
    {
        $geometry = $this->extractGeometry($item);

        if ($geometry === null) {
            return;
        }

        $xml->startElement('Placemark');

        // Name
        $name = $this->getValue($item, $this->nameColumn);
        if ($name) {
            $xml->writeElement('name', (string) $name);
        }

        // Description
        $description = $this->getValue($item, $this->descriptionColumn);
        if ($description) {
            $xml->startElement('description');
            $xml->writeCdata((string) $description);
            $xml->endElement();
        }

        // Style reference
        $styleId = $this->getStyleId($item);
        if ($styleId) {
            $xml->writeElement('styleUrl', '#'.$styleId);
        }

        // Extended data
        if (! empty($this->extendedDataColumns)) {
            $this->writeExtendedData($xml, $item);
        }

        // Geometry
        $this->writeGeometry($xml, $geometry);

        $xml->endElement(); // Placemark
    }

    /**
     * Get style ID for an item.
     */
    protected function getStyleId(mixed $item): ?string
    {
        if ($this->styleCallback) {
            $styleId = ($this->styleCallback)($item);
            if ($styleId) {
                return $styleId;
            }
        }

        return $this->defaultStyleId;
    }

    /**
     * Write extended data.
     */
    protected function writeExtendedData(\XMLWriter $xml, mixed $item): void
    {
        $xml->startElement('ExtendedData');

        foreach ($this->extendedDataColumns as $column) {
            $value = $this->getValue($item, $column);

            if ($value !== null) {
                $xml->startElement('Data');
                $xml->writeAttribute('name', $column);
                $xml->writeElement('value', (string) $value);
                $xml->endElement(); // Data
            }
        }

        $xml->endElement(); // ExtendedData
    }

    /**
     * Write geometry element.
     */
    protected function writeGeometry(\XMLWriter $xml, array $geometry): void
    {
        $type = $geometry['type'] ?? null;

        match ($type) {
            'Point' => $this->writePoint($xml, $geometry['coordinates']),
            'LineString' => $this->writeLineString($xml, $geometry['coordinates']),
            'Polygon' => $this->writePolygon($xml, $geometry['coordinates']),
            'MultiPoint', 'MultiLineString', 'MultiPolygon', 'GeometryCollection' => $this->writeMultiGeometry($xml, $geometry),
            default => null,
        };
    }

    /**
     * Write Point geometry.
     */
    protected function writePoint(\XMLWriter $xml, array $coordinates): void
    {
        $xml->startElement('Point');
        $xml->writeElement('coordinates', $this->formatCoordinate($coordinates));
        $xml->endElement();
    }

    /**
     * Write LineString geometry.
     */
    protected function writeLineString(\XMLWriter $xml, array $coordinates): void
    {
        $xml->startElement('LineString');
        $xml->writeElement('coordinates', $this->formatCoordinates($coordinates));
        $xml->endElement();
    }

    /**
     * Write Polygon geometry.
     */
    protected function writePolygon(\XMLWriter $xml, array $coordinates): void
    {
        $xml->startElement('Polygon');

        // Outer boundary
        if (isset($coordinates[0])) {
            $xml->startElement('outerBoundaryIs');
            $xml->startElement('LinearRing');
            $xml->writeElement('coordinates', $this->formatCoordinates($coordinates[0]));
            $xml->endElement(); // LinearRing
            $xml->endElement(); // outerBoundaryIs
        }

        // Inner boundaries (holes)
        for ($i = 1; $i < count($coordinates); $i++) {
            $xml->startElement('innerBoundaryIs');
            $xml->startElement('LinearRing');
            $xml->writeElement('coordinates', $this->formatCoordinates($coordinates[$i]));
            $xml->endElement(); // LinearRing
            $xml->endElement(); // innerBoundaryIs
        }

        $xml->endElement(); // Polygon
    }

    /**
     * Write MultiGeometry.
     */
    protected function writeMultiGeometry(\XMLWriter $xml, array $geometry): void
    {
        $xml->startElement('MultiGeometry');

        $geometries = $geometry['geometries'] ?? $geometry['coordinates'] ?? [];

        foreach ($geometries as $geom) {
            if (isset($geom['type'])) {
                $this->writeGeometry($xml, $geom);
            }
        }

        $xml->endElement();
    }

    /**
     * Format a single coordinate.
     */
    protected function formatCoordinate(array $coordinate): string
    {
        $lng = $coordinate[0];
        $lat = $coordinate[1];
        $alt = $coordinate[2] ?? 0;

        return "{$lng},{$lat},{$alt}";
    }

    /**
     * Format multiple coordinates.
     */
    protected function formatCoordinates(array $coordinates): string
    {
        return implode(' ', array_map(
            fn ($c) => $this->formatCoordinate($c),
            $coordinates
        ));
    }

    /**
     * Extract geometry from item.
     */
    protected function extractGeometry(mixed $item): ?array
    {
        $geometry = $this->getValue($item, $this->geometryColumn);

        if ($geometry === null) {
            return null;
        }

        return $this->normalizeGeometry($geometry);
    }

    /**
     * Normalize geometry to standard format.
     */
    protected function normalizeGeometry(mixed $geometry): ?array
    {
        if (is_string($geometry)) {
            $decoded = json_decode($geometry, true);
            if ($decoded) {
                $geometry = $decoded;
            } else {
                return null;
            }
        }

        if (! is_array($geometry)) {
            return null;
        }

        // Already GeoJSON format
        if (isset($geometry['type'], $geometry['coordinates'])) {
            return $geometry;
        }

        // lat/lng format
        if (isset($geometry['lat'], $geometry['lng'])) {
            return [
                'type' => 'Point',
                'coordinates' => [(float) $geometry['lng'], (float) $geometry['lat'], 0],
            ];
        }

        // latitude/longitude format
        if (isset($geometry['latitude'], $geometry['longitude'])) {
            return [
                'type' => 'Point',
                'coordinates' => [(float) $geometry['longitude'], (float) $geometry['latitude'], 0],
            ];
        }

        return null;
    }

    /**
     * Get a value from item.
     */
    protected function getValue(mixed $item, string $key): mixed
    {
        if ($item instanceof Model) {
            return $item->getAttribute($key);
        }

        if (is_array($item)) {
            return $item[$key] ?? null;
        }

        return $item->{$key} ?? null;
    }

    /**
     * Save to file.
     */
    public function toFile(string $path): bool
    {
        $content = $this->export();

        return file_put_contents($path, $content) !== false;
    }

    /**
     * Download as response (for Laravel controllers).
     */
    public function download(string $filename = 'export.kml'): \Illuminate\Http\Response
    {
        $content = $this->export();

        return response($content)
            ->header('Content-Type', 'application/vnd.google-earth.kml+xml')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
}
