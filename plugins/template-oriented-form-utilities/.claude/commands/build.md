---
description: Assemble the distributable plugin build and verify the output
allowed-tools: Bash(composer:*), Bash(php:*), Read, Glob
---

Produce a distributable copy of the plugin in `build/`.

1. Run `composer build` — this runs `composer check` (PHPStan + PHPUnit) first and only
   assembles the build if it passes. Add `--zip` by calling
   `php scripts/build-release.php --zip` directly when an archive is wanted.
2. The script copies an **allow-list** (`src/`, `assets/`, `languages/`, `migrations/`, plus
   `template-oriented-form-utilities.php`, `index.php`, `readme.txt`) and regenerates a
   classmap autoloader in `build/<slug>/vendor/`. Nothing else is shipped — `composer.json`
   is used to generate the autoloader and then deleted from the build.
3. Verify the result rather than trusting the exit code:
   - `build/<slug>/vendor/` contains only `autoload.php` and `composer/`. The plugin has **zero
     runtime dependencies**; anything else in there means a dev package leaked in.
   - `composer.json` is NOT present in the build.
   - The autoloader resolves the plugin's classes. Loading
     `build/<slug>/vendor/autoload.php` and checking `class_exists()` on a couple of
     `TofuPlugin\` classes is enough.
4. Report what was produced and the archive path if `--zip` was used.

Note: PHPStan needs `--memory-limit=1024M` (the composer script already sets this); the old
512M limit crashes the process partway through.
