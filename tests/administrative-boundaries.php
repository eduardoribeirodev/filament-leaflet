<?php

require __DIR__ . '/../vendor/autoload.php';

use EduardoRibeiroDev\FilamentLeaflet\Enums\AdministrativeLevel;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Boundaries\Boundary;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Boundaries\AdministrativeBoundary;
use EduardoRibeiroDev\FilamentLeaflet\Countries\UnitedStates;
use EduardoRibeiroDev\FilamentLeaflet\Support\LocationIndex;
use EduardoRibeiroDev\FilamentLeaflet\Countries\Brazil;
use EduardoRibeiroDev\FilamentLeaflet\Countries\Italy;
use EduardoRibeiroDev\FilamentLeaflet\Countries\Portugal;
use EduardoRibeiroDev\FilamentLeaflet\Countries\Spain;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;

$app = new Container();
Container::setInstance($app);
$app->instance('url', new UrlGenerator(new RouteCollection(), Request::create('https://maps.test')));

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function invalid(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException($message);
}

$mt = Brazil::state('MT');
$sp = Brazil::state('SP');
$br = Brazil::country();
$cuiaba = Brazil::city('MT', 'Cuiabá');
$varzea = Brazil::city('MT', 'Várzea Grande');
$ca = UnitedStates::state('CA');
$us = UnitedStates::country();

check(is_string($br) && is_string($mt) && is_string($cuiaba), 'Singular country helpers must return shapeIDs.');
check(Brazil::states(['MT' => 20]) === [$mt => 20], 'State helpers must return arrays keyed by real shapeIDs.');
check(Brazil::cities('MT', ['Cuiabá' => 120]) === [$cuiaba => 120], 'City helpers must return arrays keyed by the city IDs.');
check(Brazil::states([]) === [] && Brazil::cities('MT', []) === [], 'Empty helpers must return ordinary empty arrays.');
check(!is_subclass_of(Brazil::class, \EduardoRibeiroDev\FilamentLeaflet\Layers\BaseLayer::class), 'Country helpers must not inherit layer behavior.');
check(!(new ReflectionClass(Brazil::class))->isInstantiable() && !method_exists(Brazil::class, 'make'), 'Country helpers must be static utilities.');
invalid(fn () => Brazil::state('Mato Grosso'), 'State names must not be accepted as codes.');
invalid(fn () => Brazil::state('XX'), 'Unknown state codes must fail.');
invalid(fn () => Brazil::city('MT', 'Cuiaba'), 'City lookup must use the exact catalog spelling.');
invalid(fn () => Brazil::city('SP', 'Cuiabá'), 'City lookup must include its state.');

$fixed = AdministrativeBoundary::make()->level(AdministrativeLevel::Country)
    ->values(Brazil::states(['MT' => 45, 'SP' => 120]))->toArray();
$country = $fixed['administrative']['countries'][0];
check($country['administrative']['levels'][0]['visualization']['values'] === [$br => 165], 'Fixed display levels must aggregate from the actual data level.');
check($country['features']['key'] === 'shapeID', 'All administrative levels must join by shapeID.');

$multiple = AdministrativeBoundary::make()->id('visits')->values([
    Brazil::states(['MT' => 20, 'SP' => 50]),
    UnitedStates::states(['CA' => 80]),
])->toArray();
check($multiple['id'] === 'visits' && count($multiple['administrative']['countries']) === 2, 'Several countries must remain one logical layer.');
[$brazil, $usa] = $multiple['administrative']['countries'];
check($brazil['administrative']['dataLevel'] === 1 && !isset($brazil['administrative']['levels'][2]), 'State values must cap Auto at ADM1.');
check($brazil['administrative']['levels'][0]['visualization']['values'] === [$br => 70], 'Brazil must receive only its own totals.');
check($usa['administrative']['levels'][0]['visualization']['values'] === [$us => 80], 'The US must receive only its own totals.');
check($brazil['administrative']['levels'][1]['visualization']['domain'] === [20, 80], 'Countries must share the same color domain.');
check($usa['administrative']['levels'][1]['visualization']['domain'] === [20, 80], 'The shared domain must reach every country descriptor.');

$cityValues = AdministrativeBoundary::make()->values([$cuiaba => 120, $varzea => 40])->toArray();
$levels = $cityValues['administrative']['countries'][0]['administrative']['levels'];
check($levels[2]['visualization']['values'] === [$cuiaba => 120, $varzea => 40], 'Direct shapeID values must remain supported.');
check($levels[1]['visualization']['values'] === [$mt => 160], 'Municipal values must sum into the real state ID.');
check($levels[0]['visualization']['values'] === [$br => 160], 'Municipal values must sum into the real country ID.');

$restricted = AdministrativeBoundary::make()->restrictTo(Brazil::state('MT'))->values([
    Brazil::cities('MT', ['Cuiabá' => 120]),
    Brazil::cities('SP', ['São Paulo' => 90]),
])->toArray()['administrative']['countries'][0]['administrative'];
check(array_keys($restricted['levels']) === [1, 2], 'A state scope must never draw an entire country.');
check($restricted['levels'][1]['source']['featureIds'] === [$mt], 'State scope must filter ADM1 geometry.');
check($restricted['levels'][1]['visualization']['values'] === [$mt => 120], 'Scope must exclude other states from totals.');
check(count($restricted['auto']['states']) === 1 && $restricted['auto']['states'][0]['code'] === 'MT', 'Focus metadata must use only selected states.');
check($restricted['levels'][2]['source']['state'] === 'MT', 'The source must load municipal geometry only within the scope.');

$municipalScope = AdministrativeBoundary::make()->restrictTo(Brazil::city('MT', 'Cuiabá'))->values([$cuiaba => 7, $varzea => 3])
    ->toArray()['administrative']['countries'][0]['administrative'];
check(array_keys($municipalScope['levels']) === [2], 'A city scope must draw only ADM2 at every zoom.');
check($municipalScope['levels'][2]['source']['featureIds'] === [$cuiaba], 'A city scope must filter its exact polygon.');
check($municipalScope['levels'][2]['visualization']['values'] === [$cuiaba => 7], 'City scopes must exclude neighboring city values.');
check($municipalScope['levels'][2]['source']['bounds'] === LocationIndex::regions()[$cuiaba]['bounds'], 'Initial city bounds must fit the city rather than the whole state.');

$overlap = AdministrativeBoundary::make()->restrictTo([Brazil::country(), Brazil::state('MT')])->values([$cuiaba => 5])->toArray();
check(count($overlap['administrative']['countries']) === 1, 'Overlapping scopes must not duplicate polygons.');
$mixedScope = AdministrativeBoundary::make()->restrictTo([Brazil::state('MT'), Brazil::city('SP', 'São Paulo')])
    ->values([$cuiaba => 5])->toArray();
check(count($mixedScope['administrative']['countries']) === 2, 'Disjoint scopes at different levels must retain their own minimum detail.');

$zero = AdministrativeBoundary::make()->values(Brazil::states(['MT' => 5, 'SP' => -5]))->toArray();
check($zero['administrative']['countries'][0]['administrative']['levels'][0]['visualization']['values'] === [$br => 0], 'Zero totals must remain real values.');
$missing = AdministrativeBoundary::make()->values(Brazil::states(['MT' => null]))->toArray();
check($missing['administrative']['countries'][0]['administrative']['levels'][0]['visualization']['values'] === [], 'Missing values must not invent a zero total.');
$duplicate = AdministrativeBoundary::make()->values([Brazil::states(['MT' => 2]), Brazil::states(['MT' => 3])])->toArray();
check($duplicate['administrative']['countries'][0]['administrative']['levels'][1]['visualization']['values'] === [$mt => 5], 'Repeated IDs in association blocks must add their values.');

$empty = AdministrativeBoundary::make()->restrictTo(Brazil::state('MT'))->values([])->toArray();
check(count($empty['administrative']['countries']) === 1 && $empty['administrative']['countries'][0]['administrative']['minLevel'] === 1, 'An explicit scope must survive empty filters.');
check(($empty['visualization'] ?? null) === null, 'Geometry-only layers must preserve their ordinary style.');
$emptyStates = AdministrativeBoundary::make()->restrictTo(Brazil::country())->values(Brazil::states([]))->level(AdministrativeLevel::State)->toArray();
check(count($emptyStates['administrative']['countries']) === 1 && $emptyStates['administrative']['countries'][0]['administrative']['dataLevel'] === 1, 'Empty data must use the explicitly configured country and display level.');
$emptyBlocks = AdministrativeBoundary::make()->values([Brazil::states([]), Brazil::cities('MT', [])])->toArray();
check(count($emptyBlocks['administrative']['countries']) === 7, 'Empty arrays cannot imply a country or source level.');

foreach ([Italy::states(['NORD-EST' => 11]), Spain::states(['MD' => 12]), Portugal::states(['03' => 2]), UnitedStates::states(['SD' => 8])] as $block) {
    $id = array_key_first($block);
    $row = AdministrativeBoundary::make()->values($block)->toArray()['administrative']['countries'][0];
    check($row['administrative']['levels'][1]['visualization']['values'] === $block, 'Countries with missing or nonstandard ISO fields must join by real shapeIDs.');
    check(isset(LocationIndex::regions()[$id]), 'Every helper must return a known polygon ID.');
}

$variable = [$cuiaba => 8];
$reactive = AdministrativeBoundary::make()->restrictTo(fn () => Brazil::state('MT'))->values(function () use (&$variable) { return $variable; });
check($reactive->toArray()['administrative']['countries'][0]['administrative']['levels'][1]['visualization']['values'] === [$mt => 8], 'Closures must work through normal serialization.');
$variable = [$varzea => 4];
check($reactive->toArray()['administrative']['countries'][0]['administrative']['levels'][1]['visualization']['values'] === [$mt => 4], 'Changes between renders must invalidate cached totals.');
$reactive->values(fn () => Brazil::states(['MT' => 3]));
check($reactive->toArray()['administrative']['countries'][0]['administrative']['dataLevel'] === 1, 'Closures may return helper arrays.');

invalid(fn () => AdministrativeBoundary::make()->values([$mt => 1, $cuiaba => 2])->toArray(), 'Mixed source levels must fail.');
invalid(fn () => AdministrativeBoundary::make()->values([Brazil::states(['MT' => 1]), Brazil::cities('MT', ['Cuiabá' => 2])])->toArray(), 'Nonempty blocks must declare a consistent source level.');
invalid(fn () => AdministrativeBoundary::make()->values(['MT' => 1])->toArray(), 'Raw values must require shapeIDs.');
invalid(fn () => AdministrativeBoundary::make()->values(fn () => Brazil::country())->toArray(), 'An ID alone does not assign a value.');
invalid(fn () => AdministrativeBoundary::make()->values([$mt => 1])->level(AdministrativeLevel::Municipality)->toArray(), 'A fixed level cannot fabricate more detailed values.');
invalid(fn () => AdministrativeBoundary::make()->restrictTo(Brazil::state('MT'))->level(AdministrativeLevel::Country)->toArray(), 'A fixed level cannot expand outside the selected territory.');
invalid(fn () => AdministrativeBoundary::make()->restrictTo([])->toArray(), 'Empty explicit scopes must fail clearly.');
invalid(fn () => AdministrativeBoundary::make()->restrictTo(['MT'])->toArray(), 'Scope must use known shapeIDs.');
invalid(fn () => AdministrativeBoundary::make()->values([$mt => INF])->toArray(), 'Nonfinite values must fail before serialization.');
invalid(fn () => AdministrativeBoundary::make()->values(Brazil::states(['MT' => PHP_FLOAT_MAX, 'SP' => PHP_FLOAT_MAX]))->toArray(), 'Overflowing sums must fail before serialization.');
invalid(fn () => AdministrativeBoundary::make()->fromUrl('/custom.geojson')->toArray(), 'Custom GeoJSON must use the generic Boundary.');
check(Boundary::make()->fromGeoJson(['type' => 'FeatureCollection', 'features' => []])->getSource()['type'] === 'inline', 'Generic GeoJSON boundaries must retain their behavior.');

// Country helpers supply only IDs and values; all rendering belongs to the layer.
$rawCountry = AdministrativeBoundary::make()->restrictTo(Brazil::country())->values([$cuiaba => 120, $varzea => 40])->toArray();
check($rawCountry['administrative'] === $cityValues['administrative'], 'An explicit country scope must preserve direct shapeID values.');
$countryOnly = AdministrativeBoundary::make()->restrictTo(Brazil::country())->values([$cuiaba => 4, $ca => 12]);
invalid(fn () => $countryOnly->toArray(), 'Layers must reject mixed source levels before filtering.');
$countryOnly->values([$mt => 4, $ca => 12]);
$countryOnlyPayload = $countryOnly->toArray()['administrative']['countries'];
check(count($countryOnlyPayload) === 1 && $countryOnlyPayload[0]['administrative']['levels'][0]['visualization']['values'] === [$br => 4], 'An explicit country scope must exclude foreign values.');

$styled = AdministrativeBoundary::make()->values(Brazil::states(['MT' => 3]))->id('brazil')->weight(2)->title('Brazil')
    ->colorScale(['low', 'high'])->level(AdministrativeLevel::Country)->toArray();
check($styled['id'] === 'brazil' && $styled['style']['weight'] === 2 && $styled['popup']['title'] === 'Brazil', 'Administrative layers must own IDs, path styles and popups.');
check($styled['administrative']['countries'][0]['administrative']['levels'][0]['visualization']['colors'] === ['low', 'high'], 'Color scales must belong to the administrative layer.');
$choropleth = AdministrativeBoundary::make()->choropleth(Brazil::states(['MT' => 3]), ['low', 'high'])->toArray();
check($choropleth['administrative']['countries'][0]['administrative']['levels'][1]['visualization']['colors'] === ['low', 'high'], 'The inherited choropleth helper must accept ordinary value arrays.');

$large = Brazil::states(['MT' => PHP_FLOAT_MAX, 'SP' => PHP_FLOAT_MAX]);
$largePayload = AdministrativeBoundary::make()->restrictTo(Brazil::state('MT'))->values($large)->toArray();
check($largePayload['administrative']['countries'][0]['administrative']['levels'][1]['visualization']['values'] === [$mt => PHP_FLOAT_MAX], 'Filtering must precede aggregation of unused parent totals.');
$changingValues = [$mt => 2];
$aggregate = AdministrativeBoundary::make()->values(function () use (&$changingValues) { return $changingValues; });
check($aggregate->toArray()['administrative']['countries'][0]['administrative']['levels'][0]['visualization']['values'] === [$br => 2], 'Layer closures must supply the current values.');
$changingValues = [$sp => 9];
check($aggregate->toArray()['administrative']['countries'][0]['administrative']['levels'][0]['visualization']['values'] === [$br => 9], 'Layer closures must refresh on each render.');
$aggregate->values(Brazil::cities('MT', ['Cuiabá' => 9]));
check($aggregate->toArray()['administrative']['countries'][0]['administrative']['dataLevel'] === 2, 'Replacing arrays must recompute their source level.');

invalid(fn () => AdministrativeBoundary::make()->values([new stdClass])->toArray(), 'Value blocks cannot be objects.');
invalid(fn () => AdministrativeBoundary::make()->values([[$mt => 1], 2])->toArray(), 'Every grouped value block must be an array.');
invalid(fn () => AdministrativeBoundary::make()->values([$mt => [1]])->toArray(), 'Associative values must be scalar metrics.');
invalid(fn () => AdministrativeBoundary::make()->restrictTo([$mt => 1])->toArray(), 'Restrictions must be lists of IDs, not value maps.');
invalid(fn () => AdministrativeBoundary::make()->restrictTo(fn () => new stdClass)->toArray(), 'Restriction closures must resolve to IDs.');
invalid(fn () => Brazil::cities('XX', []), 'City helpers must validate state codes even for empty arrays.');

$widget = new class extends \EduardoRibeiroDev\FilamentLeaflet\Widgets\MapWidget {
    protected function getBoundaries(): array
    {
        return [
            AdministrativeBoundary::make()->values(Brazil::states(['MT' => 20]))->id('brazil'),
            AdministrativeBoundary::make()->values(UnitedStates::states(['CA' => 80]))->id('usa'),
        ];
    }
};
$serializeLayers = new ReflectionMethod(\EduardoRibeiroDev\FilamentLeaflet\Widgets\MapWidget::class, 'preparedLayers');
$widgetLayers = $serializeLayers->invoke($widget);
check(array_column($widgetLayers, 'id') === ['brazil', 'usa'], 'Widgets must accept independent administrative layers for separate countries.');
check(array_column($widgetLayers, 'type') === ['geojson', 'geojson'], 'Helpers must feed the existing JavaScript renderer through administrative layers.');

$ordered = AdministrativeBoundary::make()->values([
    UnitedStates::states(['CA' => 8]), Brazil::states(['MT' => 3]),
])->toArray()['administrative']['countries'];
check(array_map(fn (array $country): string => $country['administrative']['auto']['countryCode'], $ordered) === ['USA', 'BRA'], 'Inferred countries must retain the supplied order.');

$reentrant = AdministrativeBoundary::make();
$reentrant->values(fn () => $reentrant->getSource());
invalid(fn () => $reentrant->toArray(), 'Callbacks cannot recursively prepare their own layer.');
$reentrant->values([$mt => 2]);
check($reentrant->toArray()['administrative']['countries'][0]['administrative']['levels'][1]['visualization']['values'] === [$mt => 2], 'A recursive callback failure must allow later valid values.');
$shared = Brazil::states(['MT' => 2]);
$sharedPayload = AdministrativeBoundary::make()->values([$shared, $shared])->toArray();
check($sharedPayload['administrative']['countries'][0]['administrative']['levels'][0]['visualization']['values'] === [$br => 4], 'Repeated value arrays must add their counts.');

$valueReads = 0;
$scopeReads = 0;
$once = AdministrativeBoundary::make()
    ->values(function () use (&$valueReads, $mt) { $valueReads++; return [$mt => $valueReads]; })
    ->restrictTo(function () use (&$scopeReads) { $scopeReads++; return Brazil::state('MT'); });
$firstRender = $once->toArray();
check($valueReads === 1 && $scopeReads === 1, 'Internal layer serialization must evaluate values and restrictions once.');
$secondRender = $once->toArray();
check($valueReads === 2 && $scopeReads === 2, 'Each new serialization must refresh values and restrictions.');
check($secondRender['administrative']['countries'][0]['administrative']['levels'][1]['visualization']['values'] === [$mt => 2], 'The refreshed render must use the latest values.');

$plainBoundary = Boundary::make()->fromGeoJson(['type' => 'FeatureCollection', 'features' => []]);
check(!isset($plainBoundary->toArray()['visualization']), 'Generic boundaries without values must preserve ordinary rendering.');
$plainBoundary->values(['feature' => 12])->colorScale(['low', 'high'])->weight(3);
$plainPayload = $plainBoundary->toArray();
check($plainPayload['visualization']['values'] === ['feature' => 12] && $plainPayload['visualization']['colors'] === ['low', 'high'] && $plainPayload['style']['weight'] === 3, 'Shared appearance helpers must preserve generic boundary values and styles.');

echo "Administrative boundary checks passed.\n";
