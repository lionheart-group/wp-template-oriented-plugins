# SitemapConfig

Adjusts WordPress core's sitemaps (`/wp-sitemap.xml`). TORO does not generate a sitemap of its own.

## Usage

```php
use ToroPlugin\Structure\SiteConfig;
use ToroPlugin\Structure\SitemapConfig;

new SiteConfig(
    sitemap: new SitemapConfig(
        excludeProviders: ['users'],
        excludePostTypes: ['attachment', 'lp'],
        excludeTaxonomies: ['post_tag'],
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

## Noindex pages

A registered page whose resolved values are `noindex` — through `PageConfig::$noindex` or the
`toro_post_values` filter — is removed from its post type's sitemap.

Only **registered pages** are checked this way. Running `toro_post_values` for every post on each
sitemap request would mean loading the whole post table; to exclude other posts, return their IDs
from [`toro_sitemap_excluded_post_ids`](../hooks/index.md#toro_sitemap_excluded_post_ids).

## Notes

- `users` is excluded by default: author archives are rarely worth indexing on a corporate site,
  and the user sitemap publishes every author's slug.
- Core decides whether sitemaps are enabled and which providers exist while booting its sitemap
  server on `init` at priority 10 — before a theme's `init` callback at the same priority has run.
  TORO moves that bootstrap to `init` 20 so the configuration above is seen. Register `SiteConfig`
  at a priority below 20.
- Rank Math served `/sitemap_index.xml`; core serves `/wp-sitemap.xml`. Update the sitemap URL in
  Search Console after switching.
