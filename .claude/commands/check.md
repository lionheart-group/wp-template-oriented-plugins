---
description: Run composer check (PHPStan + PHPUnit) for a plugin, or all plugins, and fix any failures
argument-hint: "[slug]"
allowed-tools: Bash(composer:*), Bash(vendor/bin/phpstan:*), Bash(vendor/bin/phpunit:*), Read, Edit, Grep, Glob
---

Target: `$ARGUMENTS` — a plugin slug under `plugins/` (e.g. `tonkatsu-seo`). With no argument, run every plugin.

1. Run `composer check` inside `plugins/<slug>/`, or `composer check-all` from the repository root when no slug was given. CI runs the same check on GitHub, but run it locally before pushing.
2. If PHPStan fails: read each reported file/line, understand the real type error and fix the source. Don't silence it with `@phpstan-ignore` unless the report itself says it is a false positive. Each plugin's `phpstan.neon` is level 5 with WordPress stubs (`szepeviktor/phpstan-wordpress`); check its `ignoreErrors` before assuming a new suppression is needed.
3. If PHPUnit fails: read the failing test in the plugin's `tests/Unit/`, decide whether the test or the implementation is wrong, and fix accordingly. Don't weaken assertions just to make them pass.
4. Re-run until it is fully green.
5. Report which files changed and why.
