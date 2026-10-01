---
description: Assemble a plugin's distributable build and verify the output
argument-hint: "<slug>"
allowed-tools: Bash(composer:*), Bash(php:*), Bash(scripts/plugin-check.sh:*), Read, Glob
---

Target: `$ARGUMENTS` — a plugin slug under `plugins/`. Ask if it is missing.

1. In `plugins/<slug>/`, run `composer build` — it runs `composer check` first and only assembles the build if that passes. Call `php scripts/build-release.php --zip` directly when an archive is wanted.
2. The script copies an **allow-list** into `build/` (the plugin's `src/`, `assets/` and other runtime folders, the main file, `index.php`, `readme.txt`, `composer.json`) and regenerates a classmap autoloader in `build/vendor/`. The plugin files sit **directly in `build/`**; only the zip nests them under a slug-named directory, which is what WordPress expects on upload.
3. Verify the result rather than trusting the exit code:
   - `build/vendor/` contains only `autoload.php` and `composer/`. The plugins have **no runtime dependencies**; anything else means a dev package leaked in.
   - `composer.json` IS present (WordPress.org expects it next to a Composer-generated `vendor/`).
   - No tests, scripts, dev docs, `CLAUDE.md` or hidden files are in the build.
   - The autoloader resolves the plugin's classes (`class_exists()` on a couple of them after loading `build/vendor/autoload.php`).
   - If `--zip` was used: every entry is under `<slug>/`, and the archive does not contain itself.
4. Run `scripts/plugin-check.sh <slug>` from the repository root; it must report 0 errors and 0 warnings for new code.
5. Report what was produced and the archive path if `--zip` was used.
