<?php

/**
 * Assemble a distributable copy of the plugin in build/.
 *
 * This replaced a PHP-Scoper pipeline. Scoping existed to rename the
 * namespaces of bundled libraries so they could not collide with another
 * plugin's copy of the same package; with no runtime dependencies left
 * there is nothing to rename, and `TofuPlugin\` was never at risk.
 *
 * What ships is chosen by an allow-list, never by excluding what we happen
 * to remember: a deny-list is how node_modules and .env files end up inside
 * release archives.
 *
 * Usage: php scripts/build-release.php [--zip]
 */

$root  = dirname(__DIR__);
$build = $root . '/build';
$slug  = 'template-oriented-form-utilities';

/** Directories copied wholesale. */
$directories = ['src', 'assets', 'languages', 'migrations'];

/** Individual files copied to the archive root. */
$files = [
    'template-oriented-form-utilities.php',
    'index.php',
    'readme.txt',
    // Carried so the autoloader can be regenerated from it, below.
    'composer.json',
];

// ---------------------------------------------------------------------

function rrmdir(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($entries as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }

    rmdir($path);
}

function rcopy(string $from, string $to): int
{
    $copied = 0;
    mkdir($to, 0755, true);

    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($entries as $entry) {
        $target = $to . DIRECTORY_SEPARATOR . $entries->getSubPathName();

        if ($entry->isDir()) {
            mkdir($target, 0755, true);
            continue;
        }

        copy($entry->getPathname(), $target);
        $copied++;
    }

    return $copied;
}

// ---------------------------------------------------------------------

$target = $build . '/' . $slug;

rrmdir($build);
mkdir($target, 0755, true);

$total = 0;
foreach ($directories as $directory) {
    $source = $root . '/' . $directory;
    if (!is_dir($source)) {
        fwrite(STDERR, "Missing directory: {$directory}\n");
        exit(1);
    }
    $count = rcopy($source, $target . '/' . $directory);
    printf("  %-12s %4d files\n", $directory . '/', $count);
    $total += $count;
}

foreach ($files as $file) {
    if (!is_file($root . '/' . $file)) {
        fwrite(STDERR, "Missing file: {$file}\n");
        exit(1);
    }
    copy($root . '/' . $file, $target . '/' . $file);
    $total++;
}
printf("  %-12s %4d files\n", 'root files', count($files));

// The plugin's entry point requires vendor/autoload.php. With no runtime
// dependencies this is Composer's PSR-4 autoloader and nothing else;
// --classmap-authoritative turns it into a flat map so no filesystem probe
// happens per class.
echo "\nGenerating the production autoloader...\n";
exec(
    sprintf(
        'composer dump-autoload --working-dir=%s --classmap-authoritative --no-dev --quiet 2>&1',
        escapeshellarg($target)
    ),
    $output,
    $status
);
if ($status !== 0) {
    fwrite(STDERR, "composer dump-autoload failed:\n" . implode("\n", $output) . "\n");
    exit(1);
}

// composer.json has served its purpose; shipping it would only invite
// someone to run `composer install` inside a live plugin directory.
unlink($target . '/composer.json');

if (in_array('--zip', $argv, true)) {
    $version = '0.0.0';
    if (preg_match('/^\s*\*\s*Version:\s*(\S+)/mi', (string) file_get_contents($root . '/' . $files[0]), $m) === 1) {
        $version = $m[1];
    }

    $archive = sprintf('%s/%s-%s.zip', $build, $slug, $version);
    $zip = new ZipArchive();
    if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, "Could not create {$archive}\n");
        exit(1);
    }

    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($entries as $entry) {
        $zip->addFile($entry->getPathname(), $slug . '/' . $entries->getSubPathName());
    }
    $zip->close();

    printf("\nArchive: %s\n", $archive);
}

printf("\nBuilt %s (%d files).\n", $target, $total);
