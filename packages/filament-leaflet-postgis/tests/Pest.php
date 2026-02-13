<?php

declare(strict_types=1);

use Pest\TestCase;

uses(TestCase::class)->in('Unit');
uses(TestCase::class)->in('Feature');

expect()->extend('toBeCloseTo', function (float $expected, int $precision = 2) {
    $actual = $this->value;
    $multiplier = pow(10, $precision);

    expect(round($actual * $multiplier))->toBe(round($expected * $multiplier));

    return $this;
});
