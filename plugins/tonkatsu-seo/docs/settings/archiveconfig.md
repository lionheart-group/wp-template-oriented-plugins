# ArchiveConfig

SEO values for a post type archive, or for every term archive of a taxonomy.

## Usage

```php
use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Structure\ArchiveConfig;

add_action('init', function () {
    if (!class_exists('TonkatsuPlugin\Helpers\Seo')) {
        return;
    }

    // /news/ (a post type registered with has_archive)
    Seo::registerArchive('news', new ArchiveConfig(
        title: 'お知らせ',
        description: 'Example Inc. からのお知らせ一覧です。',
    ));

    // The posts page (Settings → Reading) is the `post` archive.
    Seo::registerArchive('post', new ArchiveConfig(title: 'ブログ'));

    // Every tag archive
    Seo::registerTaxonomy('post_tag', new ArchiveConfig(noindex: true));
});
```

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `title` | `?string` | No | `null` | Title part. For a taxonomy it applies to every term, so it usually stays `null` — the term name already is the title. |
| `description` | `?string` | No | `null` | Meta description and `og:description`. For terms, this takes precedence over the term's own description. |
| `noindex` | `bool` | No | `false` | Adds `noindex` to the robots meta. |

## Notes

- A `PageConfig` registered for the archive's path (e.g. `news`) takes precedence over the
  `ArchiveConfig`.
- Registering the same post type or taxonomy twice calls `wp_die()`.
- `noindex` here does not remove anything from the sitemap: exclude the taxonomy with
  `SitemapConfig::$excludeTaxonomies` for that.
