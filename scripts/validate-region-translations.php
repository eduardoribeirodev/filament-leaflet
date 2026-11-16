<?php

$geometryDirectory = $argv[1] ?? __DIR__ . '/../dist/geojson';
$languageDirectory = $argv[2] ?? __DIR__ . '/../resources/lang';

try {
    $index = json_decode(file_get_contents($geometryDirectory . '/administrative-geometry.json'), true, 512, JSON_THROW_ON_ERROR);
    $knownIds = [];
    foreach ($index as $features) {
        foreach ($features as [$id]) {
            $knownIds[$id] = true;
        }
    }

    $count = 0;
    $files = glob($languageDirectory . '/*/regions.php');
    foreach ($files as $file) {
        $translations = require $file;
        if (!is_array($translations)) {
            throw new RuntimeException("{$file} must return a translation array.");
        }
        foreach ($translations as $id => $name) {
            if (!isset($knownIds[$id])) {
                throw new RuntimeException("Unknown region shapeID {$id} in {$file}. Update the translation after rebuilding the maps.");
            }
            if (!is_string($name) || trim($name) === '' || !preg_match('//u', $name)) {
                throw new RuntimeException("Invalid region label for {$id} in {$file}.");
            }
            $count++;
        }
    }

    echo 'Validated ' . $count . ' region labels across ' . count($files) . " locales.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
