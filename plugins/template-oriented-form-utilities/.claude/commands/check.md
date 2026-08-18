---
description: Run composer check (PHPStan + PHPUnit) and fix any failures
allowed-tools: Bash(composer:*), Bash(vendor/bin/phpstan:*), Bash(vendor/bin/phpunit:*), Read, Edit, Grep, Glob
---

Run `composer check` (this runs `composer phpstan` then `composer test` — it does **not** run
`test:scoped`, see the known issue noted in `CLAUDE.md`).

1. Run `composer check`.
2. If PHPStan fails: read each reported file/line, understand the real type error (don't just
   silence it with `@phpstan-ignore` unless the report itself says the check is a false positive),
   and fix the source. `phpstan.neon` is level 5 with WordPress stubs from
   `szepeviktor/phpstan-wordpress` — check `phpstan.neon`'s `ignoreErrors` before assuming a new
   suppression is needed.
3. If PHPUnit fails: read the failing test in `tests/Unit/`, determine whether the test or the
   implementation is wrong, and fix accordingly. Don't weaken assertions just to make them pass.
4. Re-run `composer check` after each fix until it's fully green.
5. Report which files changed and why.
