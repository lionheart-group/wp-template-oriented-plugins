---
description: Checklist and cross-check for bumping the plugin version before a release
allowed-tools: Read, Edit, Grep, Bash(git log:*), Bash(git diff:*)
---

Prepare a version bump. Given a target version (ask if not provided), verify and update every
place the version is recorded — these commonly drift out of sync:

1. **`template-oriented-form-utilities.php`** — the `Version:` header comment and the
   `TOFU_VERSION` constant definition just below it must match the target version.
2. **`readme.txt`** — the `Stable tag:` field, and add a new entry at the top of the
   `== Changelog ==` section summarizing what changed since the last tag (check
   `git log --oneline <last-tag-or-commit>..HEAD` for the real list of changes — don't invent
   entries).
3. **Breaking changes** — if this release changes public behavior (form registration API,
   REST routes, DB schema/migrations), confirm there's an upgrade note. See `a770530` (v0.0.3
   upgrade notice) as the precedent for how these are written in `readme.txt`.
4. **`docs/`** — check whether any `docs/settings/*.md`, `docs/pages/*.md`, or `docs/ajax/*.md`
   need updating for new/changed config options introduced since the last version. Public API
   docs live there, not in `CLAUDE.md`.
5. **`migrations/`** — if a new migration file was added, confirm `wp_tofu_migrate` naming
   convention is followed and it's referenced correctly by `src/Init/Migrate.php`.

Report a summary of what was checked and what was changed.
