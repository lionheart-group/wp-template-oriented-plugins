---
description: Run the PHP Scoper production build and verify the output
allowed-tools: Bash(composer:*), Bash(./install-tools.sh:*), Bash(ls:*), Bash(test:*), Read, Glob
---

Produce a production build (vendor namespaced under `TofuVendor\` via PHP Scoper) in `build/`.

1. Check whether `tools/php-scoper.phar` exists. If not, run `./install-tools.sh` to fetch it.
2. Run `composer build` (runs, in order: `scoper` → `scoper:composer` → `scoper:dump` →
   `test:scoped`; see `composer.json`'s `scripts` section).
3. If the scoper step fails, read `scoper.inc.php` for the prefix/exclude configuration before
   changing anything — most failures are missing exclude entries for a class that must stay
   unprefixed (e.g. `WP_REST_Server`, see commit `8c8d858`).
4. If `test:scoped` fails, note that `phpunit.scoped.xml` currently points at the *unscoped*
   bootstrap (`tests/bootstrap.php`, not `tests/bootstrap-scoped.php`) — this is a known,
   unresolved discrepancy (see `CLAUDE.md`). Don't silently "fix" it as part of an unrelated build
   task; flag it to the user instead.
5. Verify `build/` was produced and report its contents (`ls build/`) plus whether all steps
   passed.
