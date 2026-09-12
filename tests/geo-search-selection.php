<?php

require __DIR__ . '/../vendor/autoload.php';

use EduardoRibeiroDev\FilamentLeaflet\Fields\GeoSearchInput;
use EduardoRibeiroDev\FilamentLeaflet\Services\GeoSearchService;
use EduardoRibeiroDev\FilamentLeaflet\StateCasts\GeoSearchResultStateCast;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\GeoSearchResult;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\Coordinate;
use Illuminate\Database\Eloquent\Model;

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$results = array_map(fn ($place) => GeoSearchResult::fromArray([
    'coordinate' => ['lat' => $place[1], 'lng' => -1.25],
    'name' => 'Stapleford',
    'display_name' => "Stapleford, {$place[0]}, United Kingdom",
]), [['Broxtowe', 52.93], ['Wiltshire', 51.13], ['Cambridgeshire', 52.14]]);

$service = new class extends GeoSearchService {
    public array $results = [];
    public int $calls = 0;

    public function search(string $query): array
    {
        $this->calls++;

        return $this->results;
    }
};
$service->results = $results;

$field = new class('location') extends GeoSearchInput {
    public GeoSearchService $service;
    public mixed $testState = null;

    public function getRawState(): mixed
    {
        return $this->testState;
    }

    protected function buildService(): GeoSearchService
    {
        return $this->service;
    }
};
$field->service = $service;
$field->minSearchLength(fn () => 5)->useShortLabels(fn () => false);
check($field->getSearchResults('Stap') === [] && $service->calls === 0, 'Short searches must not call the provider.');

$options = $field->getSearchResults('Stapleford');
check(count(array_unique(array_column($options, 'value'))) === 3, 'Results must have distinct scalar values.');
$cast = $field->getDefaultStateCasts()[0];
foreach ($options as $index => $option) {
    check(is_string($option['value']), 'Select values must be strings.');
    check($option['label'] === $results[$index]->displayName, 'Full labels must be preserved.');
    check($cast->get($option['value'])->toArray() === $results[$index]->toArray(), 'Each selection must return its own result.');
    check($cast->set($results[$index]) === $option['value'], 'Hydrated objects must match option values.');
    check($cast->set($results[$index]->toArray()) === $option['value'], 'Stored arrays must match option values.');
    $field->testState = $option['value'];
    check($field->getOptionLabel() === $option['label'], 'Saved selections must display their own labels.');
}

$field->useShortLabels(true);
$shortOptions = $field->getSearchResults('Stapleford');
check(count(array_unique(array_column($shortOptions, 'value'))) === 3, 'Equal short labels must not collide in full-result mode.');

$field->textMode(fn () => true)->useShortLabels(false);
$textOptions = $field->getSearchResults('Stapleford');
$textCast = $field->getDefaultStateCasts()[0];
foreach ($textOptions as $index => $option) {
    check($option['value'] === $results[$index]->displayName, 'Text mode must select the clicked label.');
    check($textCast->get($option['value']) === $option['value'], 'Text mode must return text.');
    check($textCast->set($results[$index]) === $option['value'], 'Text mode must hydrate result objects as labels.');
    $field->testState = $option['value'];
    check($field->getOptionLabel() === $option['label'], 'Saved text selections must display their labels.');
}
$field->testState = null;
check($field->getOptionLabel() === null, 'Empty fields must have no selected label.');
check((new GeoSearchResultStateCast(true, true))->set($results[0]) === 'Stapleford', 'Text mode must support short labels.');
check($cast->get(null) === null && $cast->set(null) === null, 'Clearing must preserve null.');
check($cast->get('Existing location') === 'Existing location', 'Existing text must remain readable.');
check($cast->get($results[0]->toArray())->toArray() === $results[0]->toArray(), 'Legacy array states must remain readable.');

// Exercise the real model cast: only coordinates survive a database round trip.
$model = new class extends Model {};
$modelCast = Coordinate::castUsing(['lat', 'lng']);
$saved = $modelCast->set($model, 'location', $cast->get($options[0]['value']), ['location' => null]);
$coordinate = $modelCast->get($model, 'location', $saved['location'], $saved);
check($coordinate instanceof Coordinate, 'The model cast must reload a Coordinate.');
$field->textMode(false);
$field->testState = $cast->set($coordinate);
check(is_string($field->testState), 'Hydrated coordinates must use a scalar select value.');
check($field->getState() instanceof Coordinate, 'Reloading must preserve the Coordinate type.');
check($field->getOptionLabel() === '52.93, -1.25', 'Coordinates must have a readable fallback label.');
check($modelCast->set($model, 'location', $field->getState(), $saved) === $saved, 'Saving an unchanged field must preserve coordinates.');
check($cast->set($coordinate->toArray()) === $field->testState, 'Coordinate arrays must hydrate identically.');
check($cast->get($cast->set(new Coordinate(0, 0)))->toArray() === ['lat' => 0.0, 'lng' => 0.0], 'Zero coordinates must survive hydration.');

$field->coordinateLabelUsing(function (Coordinate $coordinate): string {
    check($coordinate->lat === 52.93, 'The resolver must receive the saved coordinate.');

    return 'Saved address';
});
check($field->getOptionLabel() === 'Saved address', 'A resolver must be able to supply the saved label.');
$field->coordinateLabelUsing(fn () => null);
check($field->getOptionLabel() === '52.93, -1.25', 'A missing resolved label must fall back to coordinates.');
$field->coordinateLabelUsing(function (): never {
    throw new RuntimeException('The coordinate resolver must only run for coordinates.');
});
$field->testState = $options[0]['value'];
check($field->getOptionLabel() === $options[0]['label'], 'New results must use their own label without resolving coordinates.');
$field->testState = null;
check($field->getOptionLabel() === null, 'Clearing must not invoke the coordinate resolver.');

echo "GeoSearch selection regression checks passed.\n";
