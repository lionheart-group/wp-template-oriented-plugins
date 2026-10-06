# TOBIUO (Template-Oriented Builder of Items, URLs & Organization)

> Shared conventions for all plugins in this repository are in [`../../CLAUDE.md`](../../CLAUDE.md).

WordPress plugin (PHP 8.1+, GPLv3+) that registers custom post types and taxonomies and builds
per-post-type permalink structures (`/case/%case_category%/%postname%/`) with optional date and
author archives. Replaces Custom Post Type Permalinks (CPTP). All configuration lives in theme PHP,
registered on `init` into an in-memory static registry: **nothing is stored in the database and there
is no settings UI** (there is a read-only admin *viewer*). Sibling of TONKATSU (`../tonkatsu-seo/`)
and TOFU (`../template-oriented-form-utilities/`) and follows their conventions.

**Namespace:** `TobiuoPlugin\` (PSR-4, mapped to `src/`)
**Entry point:** `tobiuo-content-structure.php` (also loads `src/functions.php`, the global template functions)
**Theme guard:** `class_exists('TobiuoPlugin\Helpers\Registry')`

For the public API (the config objects, which term a link uses, the archives, hooks) **do not
re-derive it here — read `docs/index.md` and the pages it links to.** This file covers architecture,
the decisions behind the permalink behaviour, conventions, and dev workflow.

---

## Architecture

```
functions.php (on `init`, any priority < 99, any order)
    └── TobiuoPlugin\Helpers\Registry::registerTaxonomy() / registerPostType() / registerPosts()
            (static in-memory registry of Structure/ objects)

init 99 — Init\Registration::handOver()
    ├── Init\Posts::apply() — `post` archive (has_archive on the object) + rules
    ├── refuse post types and taxonomies core reports as `_builtin`
    ├── register_taxonomy() for every TaxonomyConfig, then register_post_type() for every PostTypeConfig
    ├── Registration::permalinkErrors() against what core now has → wp_die() on a bad config
    └── do_action('tobiuo_registered')
            └── Init\Rewrite::apply() — private rewrite tags, replaced permastruct, date/author rules

request
    ├── post_type_archive_link → Init\Posts
    ├── post_type_link        → Init\Permalink::filterLink()
    ├── template_redirect (9) → Init\Redirect::maybeRedirect()
    └── get_archives_link     → Init\ArchiveLinks::filterArchivesLink()
```

The bootstrap registers `Registration`, `AdminPage` and `Activation` unconditionally, and on
`plugins_loaded` checks `Init\Conflict::detect()`: with CPTP active only the notice is registered;
otherwise `Posts`, `Rewrite`, `Permalink`, `Redirect` and `ArchiveLinks`. The post types are the site's
content model and are registered either way.

### `src/` layout

| Dir | Responsibility |
|---|---|
| `Init/` | WordPress integration: `Registration` (hand-over on `init` 99 + config checks), `Posts` (core `post`: archive, expected permalink structure), `Rewrite` (permastruct + archive rules, pure rule builders, missing-rules check), `Permalink` (`post_type_link`), `Redirect` (canonical 301), `ArchiveLinks` (date/author links, `wp_get_archives`), `Conflict` (CPTP detection + notice), `Activation` (rewrite-rule reset), `AdminPage` (read-only Tools page) |
| `Helpers/` | `Registry` (the class themes call), `Fields` (key/type checks shared by every `fromArray()`) |
| `Structure/` | Immutable config objects, PHP 8.1 promoted `readonly` properties + named args, validated in the constructor (`InvalidArgumentException`): `PostTypeConfig`, `TaxonomyConfig`, `PermalinkConfig`, `PostsConfig`, each with `fromArray()` rejecting unknown keys and wrong types |
| `functions.php` | `tobiuo_get_year_link()` / `_month_` / `_day_` / `tobiuo_get_author_link()`, thin wrappers over `ArchiveLinks` |
| `Consts.php` | Plugin-wide constants (admin slug, priorities, private tag prefixes, `/date`, conflicting plugin) |

### Keeping WP calls at the edges

Every decision is a static pure function that takes what it needs as arguments:
`Rewrite::permastruct()`, `tags()`, `permastructArgs()`, `archiveSlug()`, `dateFront()`,
`dateRules()`, `authorRules()`, `missingRules()`; `Permalink::buildPath()`, `linkBase()`,
`chooseTerm()`, `dateParts()`; `Redirect::target()`; `ArchiveLinks::parseDateUrl()`;
`Registration::permalinkErrors()`; `Posts::archiveRules()`, `structureMismatch()`. The WP-facing
methods (`apply()`, `filterLink()`, `maybeRedirect()`, …) only gather inputs and apply results. Links and rules share
`Permalink::linkBase()` / `Rewrite::archiveSlug()`, so they cannot disagree about a slug.

The unit stubs cannot model `WP_Rewrite::generate_rewrite_rules()` or `WP::parse_request()`. Any
change to the rewrite side must be checked against a real install: register test post types, build
`$wp_rewrite->rewrite_rules()` in memory (never flush), and run `WP::parse_request()` for sample
paths with `pre_option_rewrite_rules` returning the in-memory rules.

---

## Permalink design decisions

The spec was derived from reading CPTP. What is kept, and what is deliberately different:

- **Private term tag.** `%{taxonomy}%` becomes `%tobiuo_term_{taxonomy}%` (`(.+?)`, query
  `tobiuo_term_{taxonomy}=`), which is not a public query var, so WordPress drops it after matching.
  CPTP overwrote core's `%{taxonomy}%` tag, which turned single-post requests into term queries as
  well and needed a `parse_request` hack for `parent/child` paths. Core's tag (term archives) is
  untouched, so no request fix is needed.
- **Wrong term path → 301**, not 404: the post is found by name/ID, `Init\Redirect` compares the
  requested path with the permalink path (ignoring trailing slash and percent-encoding; stripping
  and re-adding `/{page}` and `/comment-page-N`; keeping the query string). Only requests matched by
  a rewrite rule, never feeds/embeds/trackbacks/previews/attachments/endpoints.
- **`%post_id%` without `%postname%`** becomes `%tobiuo_post_id_{pt}%` with query
  `post_type={pt}&p=` — core's `%post_id%` queries `p=` alone, which WP_Query resolves among `post`
  only. (CPTP solved this with a slug placeholder tag on every structure.)
- **Permastruct removed and re-added** so it sorts after the taxonomy permastructs: term archives
  inside the post type's slug (`case/category/…`) win over `case/(.+?)/([^/]+)`. `walk_dirs` is false
  (rules for `case/%term%/` alone would shadow term archives). `feed` follows `rewrite['feeds']`
  (CPTP: always true). `with_front`, `ep_mask` come from the registered post type's `rewrite`.
- **Date/author rules** use `$wp_rewrite->pagination_base`, `$wp_rewrite->feeds` and `author_base`
  (CPTP hard-coded them), are added only with `has_archive`, and get feed rules only when the post
  type has feeds. Archive slug = `has_archive` string or the rewrite slug, with front when
  `with_front` — the same as core's `get_post_type_archive_link()`.
- **`dateFront` auto** = `/date` when every structure segment is numeric tags/digits and there are at
  most three segments (only then can a post URL equal a date archive URL); CPTP's rule was "`%post_id%`
  among the first three tags", which also prefixed `/%category%/%post_id%/`.
- **Term choice**: drop terms that are an *ancestor* (not just the direct parent, as in CPTP) of
  another assigned term, then lowest term ID; `tobiuo_post_link_term` may replace it (only a
  `WP_Term` of that taxonomy is accepted). No term → `default_term_{tax}` (`default_category` for
  categories) → else core's plain link. A link never contains a literal `%tag%`.
- **Plain link fallback** matches core's: `?{query_var}={name}` or `?post_type=…&p=ID`.
- **Non-viewable posts**: `wp_force_plain_post_permalink($post) && !$sample` returns core's link
  unchanged; sample permalinks keep `%{pt}%` for core's editable-slug box (core adds the parents).
- **Trailing slash** of post links follows the structure, not Settings → Permalinks. Archive links
  use `user_trailingslashit()` like core's.
- **Hierarchical post types**: parents walked with a seen-set (loop-safe); not `get_page_uri()`, so
  the loop guard is ours and testable.
- **No auto-flush, no stored hash.** Activation and deactivation only `delete_option('rewrite_rules')`:
  in both requests the rules cannot be generated correctly (activation: the theme's `init` already
  ran without TOBIUO; deactivation: TOBIUO is still loaded), and core regenerates them lazily on the
  next request. The admin page compares `Rewrite::expectedRules()` (generated in memory with
  `matches = 'matches'` and the `{$pt}_rewrite_rules` filter) with the stored option.
- **Config errors that cannot work** — a taxonomy tag that is not a registered taxonomy attached to
  the post type, a `PermalinkConfig` on `rewrite => false`, date/author archives without
  `has_archive` — stop with `wp_die()` on `init` 99, all reported at once. Registering after the
  hand-over also `wp_die()`s.
- **CPTP active**: register post types and taxonomies, hook nothing else, notice on Plugins + TOBIUO
  page. CPTP enabled date/author archives by default; TOBIUO's default is off (documented in the
  migration table).

### Built-in posts (`PostsConfig`)

- **`post` is never registered again.** `Registry::registerPostType()` refuses the names in
  `Consts::BUILTIN_POST_TYPES` at once (pointing to `registerPosts()` for `post`), and
  `Registration::handOver()` refuses anything core reports as `_builtin` (future core types).
- **Built-in taxonomies are refused the same way**: `Consts::BUILTIN_TAXONOMIES` in
  `registerTaxonomy()`, `_builtin` at the hand-over (`Registry::refuseBuiltinTaxonomy()`).
- **The post permalink is the site's permalink structure, and stays core's setting.** TOBIUO never
  writes or filters `permalink_structure` (an earlier version supplied it through
  `pre_option_permalink_structure` and re-initialised `WP_Rewrite` at the hand-over; Settings →
  Permalinks then seemed to ignore the user's choice, and every way of explaining that on core's
  screen — notice, settings section — raised review concerns). `PostsConfig::$permalink` is the
  structure the theme *expects*; `Posts::structureMismatch()` (pure) compares
  `'/' . archive . structure` with the stored option, and the admin page's Posts section shows
  "Matches the theme" or a warning with the expected structure and a link to Settings → Permalinks.
  Nothing is shown on Settings → Permalinks itself.
  Only core's tags plus `%category%` are allowed, and `dateArchive` / `authorArchive` / `dateFront`
  must stay default (core provides those archives, under the front) — checked in `PostsConfig`.
- **Archive.** Core registers `post` at `init` 0 with `rewrite => false`, before any theme config
  exists, so `register_post_type_args` is too late: `apply()` sets `has_archive` on the registered
  object (so `is_post_type_archive('post')` works) and adds core-shaped archive rules
  (`Posts::archiveRules()`, root-relative, not under the front — the front *is* the archive). The
  args filter is kept for a later re-registration. `post_type_archive_link` returns
  `home_url(user_trailingslashit(root . archive))` (core returns the posts page / home for `post`).
  The archive rules are part of `Rewrite::expectedRules()`.
- **Template functions** delegate to core for `post` (`get_year_link()` etc.) rather than returning
  `''`: those archives always exist for posts.
- `category_base` / `tag_base` are separate stored options and are left alone.

Known limits are listed in `docs/index.md#limits` (hierarchical post type + taxonomy tag, numeric
slugs vs date archives, trailing slash vs core's `redirect_canonical()`).

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
`composer.json`) into `build/` and regenerates a classmap autoloader. Adapted from TONKATSU's.

Before a release, run Plugin Check's PHPCS rulesets on `build/` (both must report 0 errors, 0 warnings):

```bash
PC=../plugin-check
php $PC/vendor/bin/phpcs --standard=$PC/phpcs-rulesets/plugin-review.xml --extensions=php --ignore=vendor/ build
php $PC/vendor/bin/phpcs --standard=$PC/phpcs-rulesets/plugin-check.ruleset.xml --extensions=php --ignore=vendor/ build
```

Translations: none are bundled. WordPress.org serves them from translate.wordpress.org by slug
(`tobiuo-content-structure`), so there is no `languages/` directory and no `load_plugin_textdomain()`.
`TextDomainTest` fails if a translation call in `src/` does not pass the literal
`'tobiuo-content-structure'`.

Admin notices are limited to the screens they concern (WordPress.org guideline 11): the CPTP notice
to Plugins and the TOBIUO page. Admin CSS lives in `assets/css/admin.css`, enqueued on the TOBIUO
page only — never printed inline, and no `style` attributes in the markup.

## Coding conventions

- PSR-12-ish style, 4-space indent, `namespace` + `use` at top, docblocks on public methods
- `Structure/` value objects use PHP 8.1 constructor property promotion + named arguments, validate
  in the constructor and throw `InvalidArgumentException`. Registry-level errors (duplicate
  registration, registration after the hand-over) and config errors found at registration use
  `wp_die()`, as TONKATSU's `Seo` does
- `Init/` classes start with the `if (!defined('WPINC')) die;` guard (the test bootstrap defines
  `WPINC` for that reason)
- No phpcs/ESLint/Prettier are configured for development — PHPStan (level 5, `phpstan.neon`) is the
  only enforced static check; match the surrounding file's style since nothing else will catch drift

## Security conventions

- Exception and `wp_die()` messages: every interpolated value goes through `esc_html()` (sprintf +
  `esc_html()` per argument); the format string itself is literal
- Escape at output: `esc_html()` / `esc_url()` / `esc_attr()` in the admin page; HTML-returning helpers
  (`AdminPage::badgeHtml()`) go through `wp_kses()` with `AdminPage::ALLOWED_HTML` at echo time
- `wp_parse_url()`, never `parse_url()`; the request URI is read through `add_query_arg([])` and only
  compared and passed to `wp_safe_redirect()`
- `ArchiveLinks::filterArchivesLink()` replaces only the quoted `href` / `value` attribute value with
  an `esc_url()`'d URL; no other HTML is touched

## Hook conventions

The plugin fires one action and applies three filters (`docs/hooks/index.md`). When adding to them:

- **Hook names are never renamed or removed** — a site's callback silently stops running after an
  update.
- **Config describes what the site *is*; a hook describes what the site *does*.** Fixed per post type
  or taxonomy and set by whoever writes the theme → a `Structure/` property. Depends on
  editor-maintained content (the primary term), or wanted by an unrelated plugin → a hook.
- **No filter on config objects.** They are `readonly` and live in the theme's own code.
- **Filters pass only scalars, arrays and core objects** (`WP_Post`, `WP_Term`). `apply_filters()`
  returns `mixed`, which PHPStan level 5 flags at every call site.
- **Every filter needs a type guard that falls back to the unfiltered value** — a faulty callback
  must never break every link on the site.
- `tests/bootstrap.php` implements a mini hook registry (priorities + `accepted_args`, `do_action`,
  `has_action`) and stubs core's registration, terms, posts and a `WP_Rewrite` with core's permastruct
  bookkeeping; `BaseTestCase` resets it, the stub globals and every static between tests. New hooks
  are expected to come with tests.
