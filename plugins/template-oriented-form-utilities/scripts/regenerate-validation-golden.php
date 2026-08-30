<?php

/**
 * Freeze the CURRENT validation engine's behaviour into the golden fixture.
 *
 * This must be run exactly once, while somnambulist/validation is still the
 * installed engine, to capture tests/Unit/Validation/Fixtures/expected.json.
 * ValidationGoldenTest then replays the same corpus and asserts the engine
 * (whatever backs Support\EngineProbe at the time) still matches.
 *
 * DO NOT run this again after Phase 1 swaps the engine — doing so would
 * overwrite the regression contract with the new engine's own output,
 * silently erasing the thing this whole fixture exists to catch.
 *
 * Usage: php scripts/regenerate-validation-golden.php
 */

$projectRoot = dirname(__DIR__);

require $projectRoot . '/vendor/autoload.php';
require $projectRoot . '/tests/Unit/Validation/Fixtures/Corpus.php';
require $projectRoot . '/tests/Unit/Validation/Support/EngineProbe.php';

// Minimal WP shims needed by the engine wiring (mirrors tests/bootstrap.php).
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

use TofuPlugin\Tests\Unit\Validation\Fixtures\Corpus;
use TofuPlugin\Tests\Unit\Validation\Support\EngineProbe;

$outputPath = $projectRoot . '/tests/Unit/Validation/Fixtures/expected.json';

$cases = Corpus::cases();
$results = [];

foreach ($cases as $id => $case) {
    $results[$id] = EngineProbe::run(
        $case['data'],
        $case['rules'],
        $case['aliases'],
        $case['messages'],
        $case['locale'],
    );
}

ksort($results);

$json = json_encode(
    $results,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
);

if ($json === false) {
    fwrite(STDERR, "Failed to encode golden results: " . json_last_error_msg() . "\n");
    exit(1);
}

file_put_contents($outputPath, $json . "\n");

printf("Wrote %d cases to %s\n", count($results), $outputPath);
