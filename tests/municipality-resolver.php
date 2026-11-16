<?php

require __DIR__ . '/administrative-boundaries.php';

use EduardoRibeiroDev\FilamentLeaflet\Enums\AdministrativeLevel;
use EduardoRibeiroDev\FilamentLeaflet\Support\LocationIndex;
use EduardoRibeiroDev\FilamentLeaflet\Support\MunicipalityResolver;
use EduardoRibeiroDev\FilamentLeaflet\ValueObjects\Coordinate;

$resolver = new MunicipalityResolver;
foreach ([
    [-15.6014, -56.0979, 'BRA', 'MT', '56859067B53289335762080'], // Cuiabá
    [47.6062, -122.3321, 'USA', 'WA', '52423323B35530413742416'], // King County
    [38.9072, -77.0369, 'USA', 'DC', '52423323B55530972166869'], // District of Columbia
    [43.5446, -96.7311, 'USA', 'SD', '52423323B45663566058886'], // Sioux Falls
    [40.4168, -3.7038, 'ESP', 'MD', '93216281B77569616952792'], // Shared national ADM2 source
] as [$lat, $lng, $country, $state, $expected]) {
    check($resolver->resolve(new Coordinate($lat, $lng), country: $country) === $expected, "Expected {$expected} at {$lat}, {$lng}.");
    check(LocationIndex::municipalParents($country)[$expected] === $state, 'Resolved IDs must be accepted by the layer index.');
}

check($resolver->resolve(new Coordinate(-15.6014, -56.0979)) === '56859067B53289335762080', 'Country hints must be optional.');
$border = new Coordinate(-14.977478026999961, -56.24200439499998);
check($resolver->resolve($border, AdministrativeLevel::Municipality, country: 'BRA') === null, 'A shared boundary must not select an arbitrary municipality.');
check($resolver->resolve($border, AdministrativeLevel::State) === '14911670B2082355341666', 'A municipal ambiguity must still resolve to the state.');
check($resolver->resolve($border, AdministrativeLevel::Country) === '88855331B58508533874502', 'Country lookup must return the ADM0 shapeID.');
check($resolver->resolve($border, AdministrativeLevel::State, country: 'ESP') === null, 'Country filters must also apply to ADM1.');
invalid(fn () => $resolver->resolve($border, AdministrativeLevel::Auto), 'Coordinate lookups must require a concrete level.');
check($resolver->resolve(new Coordinate(0, 0)) === null, 'Points outside the bundled ADM2 coverage must remain unresolved.');
check($resolver->resolve(new Coordinate(91, 0)) === null, 'Invalid coordinates must remain unresolved.');

echo "Municipality coordinate checks passed.\n";
