<?php

/**
 * Script to generate composer.json for the scoped build directory.
 * This is called as part of the composer build script.
 */

// Get the project root directory (parent of scripts directory)
$projectRoot = dirname(__DIR__);
$buildDir = $projectRoot . '/build';

if (!is_dir($buildDir)) {
    echo "Error: Build directory does not exist at: {$buildDir}\n";
    exit(1);
}

$composerConfig = [
    'name' => 'lionheart-group/template-oriented-form-utilities-scoped',
    'description' => 'Template-Oriented Form Utilities (Scoped Build)',
    'type' => 'wordpress-plugin',
    'license' => 'MIT',
    'autoload' => [
        'psr-4' => [
            // TofuPlugin namespace is not prefixed (excluded in scoper config)
            'TofuPlugin\\' => 'src/',
            // Vendor namespaces are prefixed with TofuVendor
            'TofuVendor\\Monolog\\' => 'vendor/monolog/monolog/src/Monolog/',
            'TofuVendor\\Psr\\Log\\' => 'vendor/psr/log/src/',
            'TofuVendor\\GUMP\\' => 'vendor/wixel/gump/src/',
        ],
        'classmap' => [
            'vendor/wixel/gump/gump.class.php',
        ],
    ],
];

$jsonContent = json_encode($composerConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if ($jsonContent === false) {
    echo "Error: Failed to encode JSON.\n";
    exit(1);
}

$result = file_put_contents($buildDir . '/composer.json', $jsonContent . "\n");

if ($result === false) {
    echo "Error: Failed to write composer.json.\n";
    exit(1);
}

echo "Successfully generated build/composer.json\n";
