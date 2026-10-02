# TONKATSU (Template-Oriented No-database Knowledge-graph & Tag Setup Utility)

> Shared conventions for all plugins in this repository are in [`../../CLAUDE.md`](../../CLAUDE.md).

WordPress plugin (PHP 8.1+, GPLv3+) that outputs SEO metadata — title, meta description,
canonical, robots, OGP, Twitter Card, JSON-LD — and adjusts core's `/wp-sitemap.xml`. Replaces Rank
Math. All configuration lives in theme PHP, registered on `init` into an in-memory static registry:
**nothing is stored in the database and there is no settings UI** (there is a read-only admin
*viewer*, see below). Sibling of TOFU (`../template-oriented-form-utilities/`) and follows its
conventions.

**Namespace:** `TonkatsuPlugin\` (PSR-4, mapped to `src/`)
**Entry point:** `tonkatsu-seo.php`
**Theme guard:** `class_exists('TonkatsuPlugin\Helpers\Seo')`

For the public API (SiteConfig/PageConfig/ArchiveConfig, the resolution order, hooks) **do not
re-derive it here — read `docs/index.md` and the pages it links to.** This file only covers
architecture, conventions, and dev workflow.

---

## Architecture

```
functions.php (on `init`)
    └── TonkatsuPlugin\Helpers\Seo::setSite() / registerPage() / registerArchive() / registerTaxonomy() / registerRedirects()
            (static in-memory registry of Structure/ objects)

request
    └── Models\Context::fromQuery()      — request type, queried object, path, own URL (WP edge)
            └── Models\Resolver::fromContext() — gathers registry + tonkatsu_post_values (WP edge)
                    └── Resolver methods — pure priority logic (title, description, canonical, …)
                            └── Init\Head — core filters + one wp_head block
```

The plugin bootstrap hooks `plugins_loaded`: `Init\Conflict::detect()` first (another SEO plugin
active → only an admin notice), otherwise `Init\Head::register()`, `Init\Redirects::register()` and
`Init\Sitemap::register()`.
It cannot run in the main file's body because Yoast (`wordpress-seo`) loads after TONKATSU
alphabetically. `plugins_loaded` still precedes `init`, which `Sitemap::deferCoreServer()` needs.

### `src/` layout

| Dir | Responsibility |
|---|---|
| `Init/` | WordPress integration: `Head` (title/robots/canonical filters + `wp_head` output), `Sitemap` (core `wp_sitemaps_*` filters; moves core's sitemap bootstrap to `init` 20), `Redirects` (`template_redirect` 0; the pure `match()` decides, `redirect()` only reads the request and responds), `Conflict` (Rank Math/Yoast/AIOSEO/SEOPress detection + notice), `AdminPage` (read-only Tools page) |
| `Helpers/` | `Seo` (the class themes call — the registry and `normalizePath()`), `Url` (pure URL validation/absolutizing/per-segment encoding shared by `Structure/`, `Resolver`, `Context` and `Redirects`) |
| `Models/` | `Context` (readonly value; WP-dependent static factories `fromQuery()`/`forPost()`/`forPath()`/`findPost()`), `Resolver` (all priority logic; constructor takes every input so it is unit-testable) |
| `Structure/` | Immutable config objects, PHP 8.1 promoted `readonly` properties + named args, validated in the constructor (`InvalidArgumentException`): `SiteConfig`, `OrganizationConfig`, `SitemapConfig`, `PageConfig` (+ `fromArray()` rejecting unknown keys), `ArchiveConfig`, `RedirectConfig` (+ `fromArray()`) |
| `Consts.php` | Plugin-wide constants (admin slug, priorities, JSON-LD flags, conflicting plugin constants, locale map) |

### Resolution order

`tonkatsu_post_values` > `PageConfig` by path > `ArchiveConfig` > what WordPress holds (excerpt, term
description, featured image, permalink) > `SiteConfig` defaults. First **non-empty** value wins, so
`false` never overrides — noindex/nofollow can only be added. Search and 404 are always noindex and
never look up a `PageConfig`. Documented in full in `docs/index.md`; keep the two in sync.

### Keeping WP calls at the edges

`Resolver`'s methods and `Head::ogTags()` / `Head::jsonLd()` touch no WordPress function (except
`apply_filters`, which the test bootstrap implements). Anything that needs WP state belongs in a
`Context` factory or in `Resolver::fromContext()`, so the logic stays testable with a hand-built
`Context`. `Context::fromQuery()` is not unit-tested; smoke-test it against a real install.

`Init\Redirects` follows the same split: `match()` takes the registered redirects, the request path
(`Context::relativePath()` form), the query string and the home URL, and returns
`{status, location}` without touching WordPress; `redirect()` and the 410 path (`set_404()` +
`status_header(410)`) are smoke-tested on a real install. The configured hosts are added to
`allowed_redirect_hosts` only while TONKATSU's own `wp_safe_redirect()` runs (`$redirecting`), so the
site's other redirects (`redirect_to` on the login screen) are not widened.

---

## Development

```bash
composer install   # dev tooling only — there are no runtime dependencies
composer phpstan    # PHPStan level 5 (src/, bootstrap: tests/bootstrap-phpstan.php)
composer test       # PHPUnit (tests/Unit, bootstrap: tests/bootstrap.php)
composer check       # phpstan + test
composer build        # check, then assemble build/ via scripts/build-release.php
php scripts/build-release.php --zip   # build and also produce build/<slug>-<version>.zip
```

`build-release.php` copies an **allow-list** (`src/`, `assets/` plus the root files, including
`composer.json`) into `build/` and regenerates a classmap autoloader. Adapted from TOFU's.

Translations: none are bundled. WordPress.org serves them from translate.wordpress.org by slug
(`tonkatsu-seo`), so there is no `languages/` directory and no `load_plugin_textdomain()`.
`TextDomainTest` fails if a translation call in `src/` does not pass the literal `'tonkatsu-seo'`.

Admin notices are limited to the screens they concern (WordPress.org guideline 11): the
other-SEO-plugin notice to Plugins and the TONKATSU page, the search-visibility warning to the
TONKATSU page and the list screens with SEO columns. Admin CSS lives in `assets/css/admin.css`,
enqueued — never printed inline.

## Coding conventions

- PSR-12-ish style, 4-space indent, `namespace` + `use` at top, docblocks on public methods
- `Structure/` value objects use PHP 8.1 constructor property promotion + named arguments, validate
  in the constructor and throw `InvalidArgumentException`. Registry-level errors (duplicate
  registration) use `wp_die()`, as TOFU's `Form::register()` does
- `Init/` classes start with the `if (!defined('WPINC')) die;` guard (the test bootstrap defines
  `WPINC` for that reason)
- No phpcs/ESLint/Prettier are configured — PHPStan (level 5, `phpstan.neon`) is the only enforced
  static check; match the surrounding file's style since nothing else will catch drift

## Security conventions

- Escape at output: `esc_attr()` for attribute content, `esc_url()` for URL-valued tags
  (`Head::isUrlProperty()`), `esc_html()` in the admin page
- JSON-LD uses `Consts::JSON_LD_FLAGS`. `JSON_HEX_TAG` is required: with `JSON_UNESCAPED_SLASHES` a
  `</script>` inside any value would otherwise close the script element
- URLs from config and from `tonkatsu_post_values` must pass `Helpers\Url::isValid()` (http/https or
  root-relative only — no `javascript:`)

## Hook conventions

The plugin applies five filters (`docs/hooks/index.md`). When adding to them:

- **Hook names are never renamed or removed** — a site's callback silently stops running after an
  update.
- **Config describes what the site *is*; a hook describes what the site *does*.** Fixed per
  site/path/archive and set by whoever writes the theme → a `Structure/` property. Depends on
  editor-maintained content, or wanted by an unrelated plugin → a hook.
- **No filter on config objects.** They are `readonly` and live in the theme's own code.
- **Filters pass only scalars and arrays** (plus the read-only `Context` / `WP_Post` as context
  arguments). `apply_filters()` returns `mixed`, which PHPStan level 5 flags at every call site.
- **Every filter needs a type guard that falls back to the unfiltered value** — a faulty callback
  must never take a live `<head>` down. Where the value is an array, sanitize entry by entry.
- `tests/bootstrap.php` implements a real mini hook registry (priorities + `accepted_args`, plus
  `has_action`/`remove_action`); `BaseTestCase` resets it and every static registry between tests.
  New hooks are expected to come with tests.
