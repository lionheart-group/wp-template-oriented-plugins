# RedirectConfig

One redirect, or one path that answers **410 Gone**. Register with `Seo::registerRedirect()`, or
several at once with `Seo::registerRedirects()`.

## Usage

```php
use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Structure\RedirectConfig;

add_action('init', function () {
    if (!class_exists('TonkatsuPlugin\Helpers\Seo')) {
        return;
    }

    Seo::registerRedirects([
        // /old-page/ → /new-page/ (301)
        ['from' => '/old-page/', 'to' => '/new-page/'],

        // /old-dir/a/ → /new-dir/a/ (the rest of the path is carried over)
        ['from' => '/old-dir/', 'to' => '/new-dir/', 'type' => 'prefix'],

        // /news/123/ → /topics/123/ (regex only when declared)
        ['from' => '^news/(\d+)$', 'to' => '/topics/$1/', 'type' => 'regex'],

        // Temporary redirect to another site
        ['from' => '/campaign/', 'to' => 'https://example.com/', 'status' => 302],

        // 410 Gone, rendered with the theme's 404 template
        ['from' => '/closed/', 'status' => 410],
    ]);

    // The same, one at a time
    Seo::registerRedirect(new RedirectConfig(from: '/旧ページ/', to: '/company/'));
});
```

`Seo::registerRedirects()` takes a list whose entries are `RedirectConfig` instances or arrays for
`RedirectConfig::fromArray()` (keys are the constructor's parameter names). Unknown keys and
wrongly-typed values (`'status' => '302'`) throw `InvalidArgumentException`, naming the entry
(`redirect #3`).

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `from` | `string` | Yes | — | The source. For `exact` and `prefix`, a path relative to the home URL. For `regex`, a pattern matched against that path. |
| `to` | `?string` | Unless 410 | `null` | The target: an absolute http(s) URL, or a path starting with `/`, which is relative to the **home URL** (like `from`). Must be omitted for 410. |
| `status` | `int` | No | `301` | `301`, `302`, `307`, `308`, or `410` (Gone). |
| `type` | `string` | No | `'exact'` | `exact` (`RedirectConfig::TYPE_EXACT`), `prefix` (`TYPE_PREFIX`) or `regex` (`TYPE_REGEX`). |

`$config->path` holds the normalized source (`old-page` for `/old-page/`; the pattern as written for
a regex).

## Notes

### Matching

- The request path is normalized like the paths of `Seo::registerPage()` (see
  [Paths](../index.md#paths)): relative to the home URL, percent-decoded, without the query string
  and without slashes at either end. `/old-page`, `/old-page/` and `/%E6%97%A7…/` all match their
  source. `''` (or `/`) is the front page.
- Order: **exact** sources first, then **prefixes** (longest first), then **regexes** in
  registration order. The first one that applies wins.
- A prefix matches the path itself and every path below it: `/old-dir/` matches `/old-dir/` and
  `/old-dir/a/b/`, not `/old-directory/`. A prefix cannot be the front page; use the regex `^(.*)$`
  to redirect every URL.
- A regex is wrapped in `~…~u` (a `~` inside is escaped for you) and matched against the normalized
  path, e.g. `news/123` — so anchor it with `^…$`, and leave out the leading and trailing slashes.
  `$1` or `${1}` in `to` is replaced with the group.

### Building the target

- `to` starting with `/` is relative to the home URL: on a site installed at
  `https://example.com/wp/`, `'/new-page/'` is `https://example.com/wp/new-page/`. (This differs
  from `PageConfig::$canonical` and `ogImage`, which are relative to the host root.)
- For a prefix, the rest of the path is appended to `to`. The trailing slash follows `to`
  (`/new-dir/` gives `/new-dir/a/`, `/new-dir` gives `/new-dir/a`), except after a file name
  (`/old-dir/a.pdf` → `/new-dir/a.pdf`).
- Carried-over path parts and regex groups are percent-encoded segment by segment, so Japanese paths
  survive. `to` itself may be written in Japanese; WordPress encodes it when redirecting.
- The request's query string is appended (`/old-page/?a=1` → `/new-page/?a=1`) unless `to` has its
  own query string, which then replaces it.
- A redirect whose result is the request itself is skipped. Because trailing slashes are not part of
  the matched path, a regex that only adds or removes a slash (`^news/(\d+)$` → `/news/$1/`) never
  fires; WordPress's own canonical redirect already handles slashes.

### Validation

- A same-site target pointing back at its own source throws: an exact `/a/` → `/a/`, or a prefix
  `/a/` → `/a/b/` (which would grow on every hop). Only targets starting with `/` are checked here.
- An invalid regex throws, quoting PHP's error (`missing closing parenthesis at offset 5`).
- Registering the same source twice calls `wp_die()` (*TONKATSU Redirect Registration Error*). Exact
  and prefix sources are compared after normalization, so `/old/` and `old` are the same; an exact
  and a prefix redirect from the same path may coexist (the exact one wins on that path).

### What happens on a match

- Redirects run on `template_redirect` at priority 0, before core's `redirect_canonical`, through
  `wp_safe_redirect()` with `X-Redirect-By: TONKATSU`. They run for front-end requests only: the
  admin, the REST API and cron never reach `template_redirect`.
- The hosts of absolute `to` URLs are allowed (`allowed_redirect_hosts`) for TONKATSU's own redirect
  only, never for other redirects on the site. A target that would leave for any other host — for
  example a regex group building `//other.example/` — is not followed.
- **410**: the request becomes a 404 (`$wp_query->set_404()`), is answered with status 410 and no-cache
  headers, and renders the theme's 404 template. TONKATSU's head output treats it as a 404
  (`noindex`). Core's 404 guessing (`redirect_canonical`) is skipped for it.
- An existing page or post at a source path is no longer reachable: the redirect runs first.

### Redirect log

With `SiteConfig::$logRedirects` on, every redirect and 410 TONKATSU answers is recorded:

```php
Seo::setSite(new SiteConfig(
    logRedirects: true,
    // redirectLogDays: 90, // days the per-request rows are kept
));
```

- Two tables are created on the first request after logging is turned on (on `init`, after the
  theme's own callback), and again whenever a TONKATSU update changes them. The schema version is
  kept in the `tonkatsu_db_version` option.
  - `{prefix}tonkatsu_redirect_stats`: one row per rule, with its hit count and last hit (UTC). A
    rule is identified by its type and normalized source, so changing its `to` or `status` keeps
    its count.
  - `{prefix}tonkatsu_redirect_log`: one row per request: the requested URI (path and query
    string), the location (none for 410), the status, the referrer without its query string and
    fragment, whether the user agent looked like a bot, and the time (UTC).
- **No IP address or user agent is stored.** The user agent is only checked for `bot`, `crawl`,
  `spider`, `slurp` and a few other crawler names; an empty one counts as a bot.
- A redirect is recorded just before it is sent, and only when it is sent (a target refused by
  `wp_safe_redirect()` is not). 404s are not recorded.
- Rows older than `redirectLogDays` are deleted now and then as new ones are written (on about one
  write in a hundred). The counts per rule are kept. There is no button to clear the log.
- While the tables are missing (their creation failed, or the database user may not create tables)
  nothing is recorded and redirects work as usual.
- Turning logging off stops the writes; the tables and their rows stay until the plugin is deleted,
  which drops them.
- **Tools → SEO (TONKATSU)** adds **Hits** and **Last hit** columns to the redirects, and links to
  the **Redirect log** (`tools.php?page=tonkatsu-seo&view=redirect-log`): 25 rows per page, newest
  first, filterable by rule, times in the site's timezone.

### Elsewhere

- **Tools → SEO (TONKATSU)** lists the redirects (with their hit counts when logging is on), warning when an exact source is a path registered
  with `Seo::registerPage()` (that page becomes unreachable), and when a same-site target is itself
  redirected (a chain).
- While another SEO plugin is active (see [Other SEO plugins](../index.md#other-seo-plugins)),
  TONKATSU runs no redirects either.
