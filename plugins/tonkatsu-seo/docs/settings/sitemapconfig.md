# SitemapConfig

Adjusts WordPress core's sitemaps (`/wp-sitemap.xml`). The only sitemap TONKATSU adds lists the
pages outside WordPress written in `extraUrls`, as a provider of core's index.

## Usage

```php
use TonkatsuPlugin\Structure\SiteConfig;
use TonkatsuPlugin\Structure\SitemapConfig;

new SiteConfig(
    sitemap: new SitemapConfig(
        excludeProviders: ['users'],
        excludePostTypes: ['attachment', 'lp'],
        excludeTaxonomies: ['post_tag'],
        extraUrls: [
            '/lp/campaign/',                                     // lastmod: lp/campaign/index.html
            '/lp/recruit.html',
            ['url' => '/lp/summer/', 'lastmod' => '2026-10-01'], // a written lastmod wins
        ],
    ),
);
```

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `enabled` | `bool` | No | `true` | `false` turns core sitemaps off (they 404). A site set to discourage search engines has no sitemaps either way. |
| `excludeProviders` | `string[]` | No | `['users']` | Core providers to remove: `posts`, `taxonomies`, `users`. |
| `excludePostTypes` | `string[]` | No | `[]` | Post types to remove from the `posts` provider. |
| `excludeTaxonomies` | `string[]` | No | `[]` | Taxonomies to remove from the `taxonomies` provider. |
| `extraUrls` | `array` | No | `[]` | Pages outside WordPress (static landing pages) listed in `/wp-sitemap-static-1.xml`. See below. |

## Pages outside WordPress (`extraUrls`)

Static pages placed under the site but not served by WordPress (`/lp/campaign/index.html`) can be
listed without creating a page for them. They get a sitemap of their own, `/wp-sitemap-static-1.xml`,
which core lists in `/wp-sitemap.xml` with the others.

- Each entry is a URL string, or `['url' => ..., 'lastmod' => ...]`. Any other key throws.
- A URL is root-relative (`/lp/campaign/`, made absolute against the site's host, also when WordPress
  is installed in a subdirectory) or an absolute `http(s)` URL. A fragment (`#`) throws.
- Non-ASCII characters are percent-encoded in the sitemap (`/lp/日本語/` →
  `/lp/%E6%97%A5%E6%9C%AC%E8%AA%9E/`). `%xx` already written is kept, so both spellings are one URL.
- An absolute URL on **another host or scheme** is not listed: the sitemap protocol only allows URLs
  with the sitemap's own protocol and host, so `http://example.com/lp/` is left out on
  `https://example.com/`. The home URL is only known at runtime, so this does not throw; the admin
  page marks the URL instead. Each URL is listed once.
- Up to 2000 URLs per page (core's `wp_sitemaps_max_urls`); more are split into
  `/wp-sitemap-static-2.xml` and so on.
- The sitemap is not served when there is no URL to list, when sitemaps are off (`enabled: false`,
  "Discourage search engines"), or in conflict mode. `excludeProviders: ['static']` removes it too.

### lastmod

1. A written `lastmod`: `Y-m-d` (`2026-10-01`) or a W3C datetime with a time zone
   (`2026-10-01T09:30:00+09:00`). Anything else throws.
2. Otherwise, the modification time of the file the URL points to, in UTC (`2026-10-15T01:02:03+00:00`).
3. Otherwise none; the URL is still listed.

The files are looked at only while a page of this sitemap is built, and only for the URLs on that
page without a written `lastmod`: a `realpath`, an `is_file` and a `filemtime` each.

### How the file is found

The URL's path, relative to the home URL's path, is looked up under the directory that serves the home
URL — `ABSPATH`, or the directory above it when WordPress lives in a subdirectory of the site (as
core's `get_home_path()` decides).

- A path ending in `/` → `index.html`, then `index.php`.
- Any other path → that file. A directory without the trailing slash is not found.
- A path with a `..` segment, or one that resolves outside that directory (through a symbolic link,
  say), is not looked up.
- A URL outside the home URL's path (`/lp/` on a site whose home URL is `/blog/`) has no file.

Tools → SEO (TONKATSU) shows each URL with its lastmod, and a **File not found** badge when no file
was found (the URL is still listed; check for a typo) or **Another host or scheme: left out**.

## Noindex pages

A registered page whose resolved values are `noindex` — through `PageConfig::$noindex` or the
`tonkatsu_post_values` filter — is removed from its post type's sitemap.

Only **registered pages** are checked this way. Running `tonkatsu_post_values` for every post on each
sitemap request would mean loading the whole post table; to exclude other posts, return their IDs
from [`tonkatsu_sitemap_excluded_post_ids`](../hooks/index.md#tonkatsu_sitemap_excluded_post_ids).

## Notes

- `users` is excluded by default: author archives are rarely worth indexing on a corporate site,
  and the user sitemap publishes every author's slug.
- Core decides whether sitemaps are enabled and which providers exist while booting its sitemap
  server on `init` at priority 10 — before a theme's `init` callback at the same priority has run.
  TONKATSU moves that bootstrap to `init` 20 so the configuration above is seen. Register `SiteConfig`
  at a priority below 20. The `static` provider is added on core's `wp_sitemaps_init`, which fires
  right after that bootstrap.
- Rank Math served `/sitemap_index.xml`; core serves `/wp-sitemap.xml`. Update the sitemap URL in
  Search Console after switching.
