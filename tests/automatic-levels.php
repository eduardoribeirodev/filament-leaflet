<?php

require __DIR__ . '/administrative-boundaries.php';

use EduardoRibeiroDev\FilamentLeaflet\Enums\AdministrativeLevel;
use EduardoRibeiroDev\FilamentLeaflet\Layers\Boundaries\AdministrativeBoundary;
use EduardoRibeiroDev\FilamentLeaflet\Countries\Brazil;

invalid(fn () => AdministrativeLevel::Auto->filename(), 'Auto must never produce an ADM filename.');
$auto = AdministrativeBoundary::make()->restrictTo(Brazil::country());
$payload = $auto->toArray()['administrative']['countries'][0]['administrative'];
check(array_keys($payload['levels']) === [0, 1, 2], 'Geometry-only Auto must offer every administrative level.');
check($payload['zoomLevels'] === [] && isset($payload['auto']), 'Auto must use geometry rather than zoom breakpoints.');
check(count($payload['auto']['states']) === 27, 'Auto must discover every state in its scope.');
foreach ($payload['auto']['states'] as $state) {
    check($state['cityCount'] > 0 && $state['citySpan'] > 0 && isset($state['geometry']['coordinates']), 'Auto must retain focus geometry and municipal density metadata.');
}
$auto->values(Brazil::states(['MT' => 7]));
$states = $auto->toArray()['administrative']['countries'][0]['administrative'];
check($states['dataLevel'] === 1 && !isset($states['levels'][2]), 'New state data must cap automatic detail.');
$auto->values(Brazil::cities('MT', ['Cuiabá' => 9]));
$municipal = $auto->toArray()['administrative']['countries'][0]['administrative'];
check($municipal['dataLevel'] === 2 && count($municipal['levels'][2]['source']['value']) === 27, 'Municipal data must permit exploration of other states in the country.');
$auto->level(AdministrativeLevel::State);
$fixed = $auto->toArray()['administrative']['countries'][0]['administrative'];
check(!isset($fixed['auto']) && array_keys($fixed['levels']) === [1], 'A fixed enum must replace Auto.');

echo "Automatic level checks passed.\n";
