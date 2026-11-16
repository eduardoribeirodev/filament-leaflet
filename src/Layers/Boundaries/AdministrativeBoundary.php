<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Layers\Boundaries;

use Closure;
use EduardoRibeiroDev\FilamentLeaflet\Enums\AdministrativeLevel;
use EduardoRibeiroDev\FilamentLeaflet\Support\LocationIndex;
use InvalidArgumentException;

class AdministrativeBoundary extends Boundary
{
    protected AdministrativeLevel|Closure $level = AdministrativeLevel::Auto;
    protected string|array|Closure|null $scope = null;
    private ?array $prepared = null;
    private bool $preparing = false;

    public function __construct(?string $id = null)
    {
        parent::__construct($id);
        $this->featureLabel('shapeName');
    }

    /** @param string|list<string>|Closure|null $regions ShapeIDs delimiting the map. */
    public function restrictTo(string|array|Closure|null $regions): static
    {
        $this->scope = $regions;
        $this->invalidate();

        return $this;
    }

    /** @param array<string, int|float|null>|list<array<string, int|float|null>>|Closure $values */
    public function values(array|Closure $values): static
    {
        $this->invalidate();

        return parent::values($values);
    }

    public function level(AdministrativeLevel|Closure $level): static
    {
        $this->level = $level;
        $this->invalidate();

        return $this;
    }

    public function toArray(): array
    {
        $this->invalidate();

        return parent::toArray();
    }

    protected function invalidate(): void
    {
        $this->prepared = null;
    }

    private function prepare(): array
    {
        if ($this->prepared === null) {
            if ($this->preparing) {
                throw new InvalidArgumentException('Administrative boundary callbacks cannot recursively prepare the same layer.');
            }
            $this->preparing = true;
            try {
                $data = $this->aggregateValues($this->normalizeValueData());
                $data['countries'] = $this->countryDescriptors($data);
                $this->prepared = $data;
            } finally {
                $this->preparing = false;
            }
        }

        return $this->prepared;
    }

    private function aggregateValues(array $data): array
    {
        $regions = LocationIndex::regions();
        $totals = [0 => [], 1 => [], 2 => []];
        foreach ($data['values'] as $id => $value) {
            $totals[$data['dataLevel']][$id] = $value;
            if ($value === null) {
                continue;
            }
            $parent = $regions[$id]['parent'];
            while ($parent !== null) {
                $this->addValue($totals[$regions[$parent]['level']], $parent, $value);
                $parent = $regions[$parent]['parent'];
            }
        }

        return array_replace($data, ['values' => $totals]);
    }

    private function normalizeValueData(): array
    {
        $regions = LocationIndex::regions();
        $input = $this->values === null ? [] : $this->evaluate($this->values);
        if (!is_array($input)) {
            throw new InvalidArgumentException('values() must return a shapeID-to-value array or a list of those arrays.');
        }
        $blocks = $input !== [] && array_is_list($input) ? $input : [$input];
        $values = [];
        $levels = [];
        $countries = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                throw new InvalidArgumentException('values() expects shapeID-to-value arrays.');
            }
            foreach ($block as $id => $value) {
                $region = is_string($id) ? ($regions[$id] ?? null) : null;
                if ($region === null) {
                    throw new InvalidArgumentException("Unknown administrative shapeID: {$id}.");
                }
                $this->addValue($values, $id, $value);
                $levels[$region['level']] = true;
                $countries[$region['country']] = true;
            }
        }
        if (count($levels) > 1) {
            throw new InvalidArgumentException('All values in an AdministrativeBoundary must belong to the same administrative level.');
        }
        $dataLevel = $levels === [] ? 2 : array_key_first($levels);
        $ids = $this->selectedRegionIds(array_keys($countries));

        $groups = [];
        foreach ($ids as $id) {
            $region = $regions[$id];
            if ($region['level'] > $dataLevel) {
                throw new InvalidArgumentException('Values must be at the same administrative level as the scope or a more detailed level.');
            }
            $key = $region['country'] . ':' . $region['level'];
            $groups[$key] ??= ['country' => $region['country'], 'minLevel' => $region['level'], 'ids' => []];
            $groups[$key]['ids'][] = $id;
        }

        $scopedValues = array_filter($values, fn (string $id): bool => LocationIndex::containsAny($ids, $id), ARRAY_FILTER_USE_KEY);

        return ['groups' => $groups, 'values' => $scopedValues, 'dataLevel' => $dataLevel, 'hasValues' => $values !== []];
    }

    private function selectedRegionIds(array $countries): array
    {
        $scope = $this->evaluate($this->scope);
        if ($scope === null) {
            $catalog = LocationIndex::countries();
            $ids = array_map(
                fn (string $country): string => $catalog[$country]['id'],
                $countries === [] ? array_keys($catalog) : $countries,
            );

            return LocationIndex::restrictRegions($ids);
        }
        $ids = is_string($scope) ? [$scope] : $scope;
        if (!is_array($ids) || !array_is_list($ids) || $ids === []) {
            throw new InvalidArgumentException('restrictTo() must return a shapeID or a nonempty list of shapeIDs.');
        }
        $regions = LocationIndex::regions();
        foreach ($ids as $id) {
            if (!is_string($id) || !isset($regions[$id])) {
                throw new InvalidArgumentException('restrictTo() expects existing administrative shapeIDs.');
            }
        }

        return LocationIndex::restrictRegions($ids);
    }

    private function validateValue(mixed $value): void
    {
        if ($value !== null && ((!is_int($value) && !is_float($value)) || !is_finite((float) $value))) {
            throw new InvalidArgumentException('Administrative values must be finite numbers or null.');
        }
    }

    private function addValue(array &$values, string $id, mixed $value): void
    {
        $this->validateValue($value);
        $sum = $value === null ? ($values[$id] ?? null) : ($values[$id] ?? 0) + $value;
        $this->validateValue($sum);
        $values[$id] = $sum;
    }

    private function countryDescriptors(array $prepared): array
    {
        $configured = $this->evaluate($this->level);
        if (!$configured instanceof AdministrativeLevel) {
            throw new InvalidArgumentException('level() must return an AdministrativeLevel enum.');
        }
        $automatic = $configured === AdministrativeLevel::Auto;
        $maximum = $automatic ? $prepared['dataLevel'] : $configured->value;
        if ($maximum > $prepared['dataLevel']) {
            throw new InvalidArgumentException('The display level cannot be more detailed than the supplied values.');
        }

        $descriptors = [];
        foreach ($prepared['groups'] as $key => $group) {
            if ($maximum < $group['minLevel']) {
                throw new InvalidArgumentException('The display level cannot exceed the geographical scope.');
            }
            $states = $this->selectedStates($group);
            $levels = [];
            foreach ($automatic ? range($group['minLevel'], $maximum) : [$maximum] as $level) {
                $values = array_filter($prepared['values'][$level], fn (string $id): bool => LocationIndex::containsAny($group['ids'], $id), ARRAY_FILTER_USE_KEY);
                $levels[$level] = [
                    'source' => $this->sourceForLevel($group, $states, $level),
                    'features' => ['key' => 'shapeID', 'label' => $this->getFeatureLabel()],
                    'visualization' => $prepared['hasValues'] ? $this->getVisualization($values) : null,
                ];
            }
            $administrative = ['dataLevel' => $maximum, 'minLevel' => $group['minLevel'],
                'zoomLevels' => $automatic ? [] : [['minZoom' => 0, 'level' => $maximum]], 'levels' => $levels];
            if ($automatic) {
                $administrative['auto'] = $this->autoMetadata($group, $states, $maximum);
            }
            $descriptors[$key] = ['administrative' => $administrative] + $levels[$maximum];
        }
        // Countries share the same color domain for each administrative level.
        foreach ([0, 1, 2] as $level) {
            $numeric = [];
            foreach ($descriptors as $descriptor) {
                foreach ($descriptor['administrative']['levels'][$level]['visualization']['values'] ?? [] as $value) {
                    if ($value !== null) {
                        $numeric[] = $value;
                    }
                }
            }
            $domain = $numeric === [] ? null : [min($numeric), max($numeric)];
            foreach ($descriptors as &$descriptor) {
                if (isset($descriptor['administrative']['levels'][$level]['visualization'])) {
                    $descriptor['administrative']['levels'][$level]['visualization']['domain'] = $domain;
                }
            }
            unset($descriptor);
        }

        return array_values($descriptors);
    }

    private function selectedStates(array $group): array
    {
        $ids = $group['ids'];
        if ($group['minLevel'] === 2) {
            $ids = array_map(fn (string $id): string => LocationIndex::regions()[$id]['parent'], $ids);
        }

        return array_filter(LocationIndex::states($group['country']), fn (array $state): bool =>
            $state['featureId'] !== null && LocationIndex::containsAny($ids, $state['featureId']));
    }

    private function sourceForLevel(array $group, array $states, int $level): array
    {
        $country = $group['country'];
        if ($level < 2) {
            $source = $this->sourceForPath("{$country}/adm{$level}.geojson");
            $source['featureIds'] = $level === 0 ? [LocationIndex::countries()[$country]['id']] : array_column($states, 'featureId');
            if ($level === 1) {
                $source['bounds'] = LocationIndex::mergeBounds(array_column($states, 'focusBounds'));
            }

            return $source;
        }
        $sources = [];
        foreach ($states as $code => $entry) {
            if ($entry['path'] === null) {
                continue;
            }
            $source = $this->sourceForPath($entry['path']);
            $source['state'] = (string) $code;
            $source['bounds'] = $entry['bounds'];
            if ($group['minLevel'] === 2) {
                $source['featureIds'] = array_values(array_intersect(array_column($entry['cities'], 0), $group['ids']));
                $source['bounds'] = LocationIndex::mergeBounds(array_map(fn (string $id): ?array => LocationIndex::regions()[$id]['bounds'], $source['featureIds']));
            } elseif ($entry['path'] === "{$country}/adm2.geojson") {
                $source['featureIds'] = array_column($entry['cities'], 0);
            }
            $sources[] = $source;
        }

        return $this->collection($sources);
    }

    private function sourceForPath(string $path): array
    {
        return ['type' => 'url', 'value' => asset('vendor/filament-leaflet/geojson/' . $path),
            'bounds' => LocationIndex::geometry($path)['bounds'] ?? null, 'cullFeatures' => !$this->getEditable()];
    }

    private function collection(array $sources): array
    {
        if ($sources === []) {
            return ['type' => 'inline', 'value' => ['type' => 'FeatureCollection', 'features' => []]];
        }

        return count($sources) === 1 ? $sources[0] : ['type' => 'collection', 'value' => $sources];
    }

    private function autoMetadata(array $group, array $states, int $maximum): array
    {
        $metadata = [];
        foreach ($states as $code => $entry) {
            $metadata[] = ['code' => (string) $code, 'key' => $entry['featureId'], 'featureId' => $entry['featureId'],
                'bounds' => $entry['focusBounds'] ?? $entry['bounds'], 'geometry' => $entry['focusGeometry'],
                'cityCount' => count($entry['cities']), 'citySpan' => $entry['citySpan'],
                'available' => $maximum === 2 && $entry['path'] !== null];
        }

        return ['contextual' => true, 'countryCode' => $group['country'], 'states' => $metadata,
            'countryBounds' => LocationIndex::mergeBounds(array_column($metadata, 'bounds'))
                ?? LocationIndex::geometry($group['country'] . '/adm0.geojson')['bounds'] ?? null];
    }

    public function getSource(): array
    {
        if ($this->source !== []) {
            throw new InvalidArgumentException('Use Boundary for custom GeoJSON sources.');
        }

        return $this->collection(array_column($this->prepare()['countries'], 'source'));
    }

    protected function getValues(): array
    {
        $prepared = $this->prepare();

        return $prepared['values'][$prepared['dataLevel']];
    }

    protected function getFeatureKey(): ?string
    {
        $key = $this->evaluate($this->featureKey);
        if ($key !== null && $key !== 'shapeID') {
            throw new InvalidArgumentException('AdministrativeBoundary values use the shapeID feature key.');
        }

        return 'shapeID';
    }

    protected function getLayerData(): array
    {
        $data = parent::getLayerData();
        $data['administrative'] = ['countries' => $this->prepare()['countries']];

        return $data;
    }
}
