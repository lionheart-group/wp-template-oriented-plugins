# WordPress Template-Oriented Plugins

WordPress plugins that are configured from theme PHP code. Nothing is stored in the database and there is no settings screen, so the configuration is reviewed, versioned and deployed together with the theme.

| Plugin | What it does | WordPress.org | Docs |
|---|---|---|---|
| **TOFU** — Template-Oriented Form Utilities | Forms: validation, confirm step, mail, records | [template-oriented-form-utilities](https://wordpress.org/plugins/template-oriented-form-utilities/) | [docs](plugins/template-oriented-form-utilities/docs/) |
| **TONKATSU** — Template-Oriented No-database Knowledge-graph & Tag Setup Utility | SEO: title, meta, OGP, JSON-LD, sitemap adjustments | [tonkatsu-seo](https://wordpress.org/plugins/tonkatsu-seo/) | [docs](plugins/tonkatsu-seo/docs/) |
| **TOBIUO** — Template-Oriented Builder of Items, URLs & Organization | Post types, taxonomies, permalinks and their archives | (not published yet) | [docs](plugins/tobiuo-content-structure/docs/) |

## Layout

```
plugins/<slug>/   One plugin per folder, named after its WordPress.org slug.
                  Each is self-contained: its own composer.json, tests, docs and build script.
scripts/          Scripts shared by all plugins (checks, Plugin Check, SVN release).
```

The plugins share no runtime code: each is distributed on its own and may run on the same site as the others.

## Development

Requires PHP 8.1+ and Composer.

```sh
composer install-all   # composer install in every plugin
composer check-all     # PHPStan + PHPUnit in every plugin
composer build-all     # build/ for every plugin

cd plugins/<slug>
composer check                          # one plugin
php scripts/build-release.php --zip     # build/ and build/<slug>-<version>.zip
```

Plugin Check (WordPress.org's rules) on a build:

```sh
scripts/plugin-check.sh <slug>
```

## Releasing to WordPress.org

Each plugin has its own SVN repository. The working copies live next to this repository, in `../svn/<slug>/`.

```sh
scripts/svn-release.sh <slug>
```

It builds the plugin, syncs `build/` into `trunk/`, copies it to `tags/<version>/`, shows the changes and asks before committing. Tag the release in git as `<slug>/<version>`.
