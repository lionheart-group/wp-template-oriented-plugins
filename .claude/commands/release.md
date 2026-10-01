---
description: Checklist and cross-check for bumping a plugin's version before a release
argument-hint: "<slug> <version>"
allowed-tools: Read, Edit, Grep, Bash(git log:*), Bash(git diff:*), Bash(git tag:*)
---

Target: `$ARGUMENTS` — a plugin slug under `plugins/` and the new version. Ask for anything missing.

Verify and update every place the version is recorded — these commonly drift out of sync:

1. **The main file** (`plugins/<slug>/<slug>.php`): the `Version:` header and the `*_VERSION` constant just below it must match.
2. **`readme.txt`**: `Stable tag:`, and a new entry at the top of `== Changelog ==` summarizing what changed since the last release. Get the real list with `git log --oneline <slug>/<last version>..HEAD -- plugins/<slug>` — don't invent entries.
3. **Breaking changes**: if the release changes public behaviour (config objects, hooks, REST routes, DB schema), confirm there is an upgrade note in `readme.txt`.
4. **`docs/`**: check whether any page needs updating for new or changed options. The public API is documented there, not in `CLAUDE.md`.
5. **Plugin-specific**: for TOFU, a new file in `migrations/` must follow the naming convention and be registered in `src/Init/Migrate.php`.
6. After the release is committed, it is published with `scripts/svn-release.sh <slug>` and tagged in git as `<slug>/<version>`.

Report a summary of what was checked and what was changed.
