# TONKATSU (Template-Oriented No-database Knowledge-graph & Tag Setup Utility) documentation

## Settings

TONKATSU is configured in your WordPress theme's `functions.php` (on `init`) using the following settings:

- [SiteConfig](settings/siteconfig.md) — site-wide defaults, registered with `Seo::setSite()`
  - [OrganizationConfig](settings/organizationconfig.md)
  - [SitemapConfig](settings/sitemapconfig.md)
- [PageConfig](settings/pageconfig.md) — per URL path, registered with `Seo::registerPage()` / `Seo::registerPages()`
- [ArchiveConfig](settings/archiveconfig.md) — per post type archive (`Seo::registerArchive()`) or taxonomy (`Seo::registerTaxonomy()`)
- [RedirectConfig](settings/redirectconfig.md) — redirects and 410 Gone, registered with `Seo::registerRedirect()` / `Seo::registerRedirects()`

Here's a complete example showing all of them together:

[Example configuration](settings/example.md)

## How values are resolved

For every request TONKATSU works out one value per field, taking the first **non-empty** one in this
order:

| # | Source | Applies to |
|---|---|---|
| 1 | [`tonkatsu_post_values`](hooks/index.md#tonkatsu_post_values) filter | The queried post (singulars, a static front page, the posts page) |
| 2 | `PageConfig` registered for the request's path | Any request except search results and 404s |
| 3 | `ArchiveConfig` | Post type archives (`registerArchive`), term archives (`registerTaxonomy`), the posts page (`registerArchive('post', …)`) |
| 4 | What WordPress already holds | A post's hand-written excerpt (description), featured image (og:image), permalink (canonical); a term's description |
| 5 | `SiteConfig` defaults | `defaultDescription`, `defaultOgImage` |

| Field | 1 | 2 | 3 | 4 | 5 | When nothing is set |
|---|---|---|---|---|---|---|
| Title part | `title` | `title` | `title` | — | — | WordPress's own title is kept |
| Description | `description` | `description` | `description` | excerpt / term description | `defaultDescription` | No meta description |
| Canonical | `canonical` | `canonical` | — | permalink / archive URL | — | Core's canonical (singular) |
| og:image | `og_image` | `ogImage` | — | featured image | `defaultOgImage` | No og:image |
| noindex | `noindex` | `noindex` | `noindex` | — | — | Indexable (search and 404 are always noindex) |
| nofollow | `nofollow` | `nofollow` | — | — | — | Followable |

`false` counts as empty, so noindex/nofollow can only be *added* on the way up: a filter returning
`'noindex' => false` does not re-index a page whose `PageConfig` says `noindex: true`.

### Paths

A path is relative to the home URL, so on a site installed at `https://example.com/wp/` the page
`https://example.com/wp/company/about/` is the path `company/about`. Leading and trailing slashes,
query strings and percent-encoding are ignored — `/company/about/`, `company/about` and
`https://example.com/wp/company/about/?utm_source=x` are the same page. `''` (or `/`) is the front
page.

For singular requests the path is taken from the post's permalink, so the lookup follows the post
even if it is reached through another URL. Everything else uses the request URI.

## What TONKATSU outputs

- `<title>` — through `document_title_parts` / `document_title_separator`, so the theme keeps
  `add_theme_support('title-tag')` and nothing else changes. The configured title replaces the
  *title part*; WordPress still appends the page number and the site name.
- `<meta name="robots">` — through core's `wp_robots`.
- `<link rel="canonical">` — core prints it for singular requests (TONKATSU filters
  `get_canonical_url`); TONKATSU prints it for everything else except search results, 404s and date
  archives.
- `<meta name="description">`, Open Graph (`og:site_name`, `og:title`, `og:description`,
  `og:type`, `og:url`, `og:image`, `og:locale`), Twitter Card (`twitter:card`, `twitter:site`) and
  a JSON-LD `@graph` (`WebSite`, `Organization`, `BreadcrumbList`) on `wp_head`.
- Sitemap adjustments to core's `/wp-sitemap.xml` — see [SitemapConfig](settings/sitemapconfig.md).

## Redirects

`Seo::registerRedirects()` replaces a redirection plugin for URLs that moved when a site was
rebuilt: exact paths, path prefixes (the rest of the path is carried over) and regular expressions,
with 301/302/307/308, or 410 Gone rendered with the theme's 404 template.

```php
Seo::registerRedirects([
    ['from' => '/old-page/', 'to' => '/new-page/'],
    ['from' => '/old-dir/', 'to' => '/new-dir/', 'type' => 'prefix'],
    ['from' => '/closed/', 'status' => 410],
]);
```

Sources are paths normalized like page paths; `to` starting with `/` is relative to the home URL.
The query string is carried over. Exact sources are checked first, then prefixes (longest first),
then regexes. Details in [RedirectConfig](settings/redirectconfig.md).

## Hooks

[Actions and filters reference](hooks/index.md)

## Admin page

**Tools → SEO (TONKATSU)** lists what the theme registered and, for every registered page, the values
it resolves to. The redirects are listed too, with a warning when one takes over a registered page or
leads to another redirect. It is read-only. The capability required to see it is `manage_options`, filterable
with [`tonkatsu_admin_page_capability`](hooks/index.md#tonkatsu_admin_page_capability).

**Pages** gets **SEO title**, **Description** and **Robots** columns showing what every page
outputs, registered or not: the finished `<title>` (separator and site name included) and meta
description, as a visitor's browser receives them. A note marks values that came from a fallback
(the page title, the site name, the site-wide default description) or that are not output. Add other post types with
[`tonkatsu_admin_column_post_types`](hooks/index.md#tonkatsu_admin_column_post_types).

While **Settings → Reading → Discourage search engines** is checked, core outputs every page as
`noindex, nofollow` and turns the sitemaps off. The robots columns say so, and a warning is shown
on the TONKATSU page and the list screens with SEO columns.

## Other SEO plugins

While Rank Math, Yoast SEO, All in One SEO or SEOPress is active, TONKATSU hooks nothing on the front
end (no redirects either) and shows an admin notice on the Plugins screen and the TONKATSU page instead. Two plugins printing canonicals and robots metas leave
search engines to choose between them.

## Migrating from Rank Math

Rank Math keeps its settings in the database; TONKATSU keeps them in code. Before deactivating Rank
Math, carry across:

| Rank Math | TONKATSU |
|---|---|
| Titles & Meta → Global → separator | `SiteConfig::$separator` |
| Titles & Meta → Local SEO → name / logo / social profiles | `OrganizationConfig` |
| Titles & Meta → Social Meta → Twitter username | `SiteConfig::$twitterSite` |
| Titles & Meta → Global → OpenGraph thumbnail | `SiteConfig::$defaultOgImage` |
| Per-page title / description / robots set in the editor | `Seo::registerPage()`, or `tonkatsu_post_values` reading your own custom fields |
| Redirections | `Seo::registerRedirects()` (exact, prefix and regex sources; 301/302/307/308/410) |
| Sitemap settings | `SitemapConfig` (core's `/wp-sitemap.xml` replaces `/sitemap_index.xml` — update the URL in Search Console) |
