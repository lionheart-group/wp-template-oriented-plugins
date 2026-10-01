# CLAUDE.md

Monorepo of three WordPress plugins built on the same idea: **configuration is registered from theme PHP on `init`, kept in memory, and never stored in the database; there is no settings UI** (only read-only admin viewers).

| Folder | Plugin | Namespace | Hooks | Constants |
|---|---|---|---|---|
| `plugins/template-oriented-form-utilities/` | TOFU — forms | `TofuPlugin\` | `tofu_*` | `TOFU_*` |
| `plugins/tonkatsu-seo/` | TONKATSU — SEO | `TonkatsuPlugin\` | `tonkatsu_*` | `TONKATSU_*` |
| `plugins/tobiuo-content-structure/` | TOBIUO — post types, taxonomies, permalinks | `TobiuoPlugin\` | `tobiuo_*` | `TOBIUO_*` |

Each plugin has its own `CLAUDE.md` for its architecture and decisions, and its own `docs/` for the public API. This file holds what all three share. When a rule here and a plugin's `CLAUDE.md` disagree, the plugin's file wins for that plugin.

## Layout and boundaries

- One plugin per folder, named after its WordPress.org slug (folder, main file and text domain are all the slug).
- **Each plugin is self-contained**: its own `composer.json`, `vendor/`, tests, docs and `scripts/build-release.php`. Run Composer inside the plugin folder.
- **No runtime code is shared between plugins.** They are distributed separately and may run on the same site at different versions. Copy a pattern; never `require` another plugin's code.
- `scripts/` holds tooling shared by all plugins (`each.sh`, `plugin-check.sh`, `svn-release.sh`).
- The WordPress.org SVN working copies live outside this repo, in `../svn/<slug>/`.
- `.claude/` (repo root) is shared by all plugins:
  - commands `/check [slug]`, `/build <slug>`, `/release <slug> <version>`
  - a PostToolUse hook that runs the edited plugin's PHPStan on files under `plugins/<slug>/src/`
  - the skills `wp-phpstan`, `wp-plugin-development` and `wp-plugin-directory-guidelines`, vendored from `WordPress/agent-skills` and pinned in `skills-lock.json` (repo root); `create-issue` writes to `plugins/<slug>/issues/`
  - If the skills lockfile tooling is re-run it may recreate `.github/skills/`; move it back with `git mv .github/skills .claude/skills`.

## Design principles (all plugins)

- **Config describes what the site *is*; a hook describes what the site *does*.** Fixed per site and written by whoever writes the theme → a `Structure/` property. Depends on editor content or wanted by another plugin → a hook.
- **No filter on config objects.** They are `readonly` and live in the theme's code.
- **Filters pass scalars and arrays** (plus read-only context objects). Every filter has a type guard that falls back to the unfiltered value; arrays are sanitized entry by entry.
- **Hook names are never renamed or removed** once released.
- `Structure/` value objects use PHP 8.1 constructor promotion and named arguments, validate in the constructor and throw `InvalidArgumentException`. Registry errors (duplicate registration) use `wp_die()`.
- Keep WordPress calls at the edges; decisions are pure functions so they can be unit-tested.
- The theme guards with `class_exists()` so the site keeps working when a plugin is inactive.

## WordPress.org rules learned from reviews (apply from the start)

- **Escape everything at output**, late: `esc_html()`, `esc_attr()`, `esc_url()`; HTML-returning helpers go through `wp_kses()` right before `echo`.
- **Escape values in exception messages** with `esc_html()` (`sprintf` + `esc_html` per value); no `var_export()`.
- **No inline `<style>` / `<script>`.** CSS in `assets/css/*.css`, enqueued on the plugin's own screens only. Structured data via `wp_print_inline_script_tag()`.
- **Admin notices only on the screens they concern** (guideline 11): the Plugins screen and the plugin's own page.
- Use `wp_parse_url()`, sanitize `$_SERVER` values, prefer core APIs to string surgery.
- **No bundled translations** and no `load_plugin_textdomain()`: translate.wordpress.org serves them by slug. Every translation call uses the literal slug as text domain (`TextDomainTest`).
- Plugin names must not use others' trademarks (TORO was rejected); the slug is fixed after approval.
- `readme.txt` and the plugin header declare `Requires at least` (6.0) and `Requires PHP` (8.1). Without `Requires at least`, translate.wordpress.org does not import the code strings.
- The release zip contains only an allow-list (`src/`, `assets/`, other runtime folders, the main file, `index.php`, `readme.txt`, `composer.json` and the classmap autoloader) — never tests, scripts or dev docs.

## Commands

```sh
composer install-all | check-all | build-all   # from the repo root
cd plugins/<slug> && composer check             # one plugin: PHPStan level 5 + PHPUnit
php scripts/build-release.php --zip             # inside a plugin
scripts/plugin-check.sh <slug>                  # Plugin Check rulesets on build/ — must be 0 errors, 0 warnings
scripts/svn-release.sh <slug>                   # WordPress.org release (asks before committing)
```

## Git

- Each plugin has its own development branch, `<short name>/develop`: `tofu/develop`, `tonkatsu/develop`, `tobiuo/develop`. Work and commit on the branch of the plugin you are changing; create it from `master` when it doesn't exist yet.
- `master` holds released code only and is updated by merging a plugin's development branch. Changes shared by all plugins (root files, `scripts/`, `.claude/`, CI) go through whichever development branch needs them first.
- Commit messages in English, past tense, one topic per commit (`Added …`, `Fixed …`).
- Keep each commit within one plugin when possible.
- Release tags: `<slug>/<version>` (e.g. `tonkatsu-seo/0.0.1`). Tag on `master`, after the merge.
