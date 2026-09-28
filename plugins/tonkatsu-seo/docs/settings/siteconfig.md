# SiteConfig

Site-wide defaults. Register once with `Seo::setSite()`, in the `init` action.

## Usage

```php
use ToroPlugin\Helpers\Seo;
use ToroPlugin\Structure\SiteConfig;

add_action('init', function () {
    if (!class_exists('ToroPlugin\Helpers\Seo')) {
        return;
    }

    Seo::setSite(new SiteConfig(
        separator: '|',
        defaultDescription: 'Example Inc. makes widgets.',
        defaultOgImage: get_theme_file_uri('images/ogp.png'),
        twitterSite: '@example',
        locale: 'ja_JP',
    ));
});
```

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `siteName` | `?string` | No | `null` | Site name for the title's site part, `og:site_name` and JSON-LD. `null` uses the WordPress site title (Settings → General), read at output time. |
| `separator` | `string` | No | `'\|'` | Title separator, e.g. `About \| Example`. Must not be empty. |
| `defaultDescription` | `?string` | No | `null` | Description used when nothing more specific is set. Also the `WebSite` node's description. |
| `defaultOgImage` | `?string` | No | `null` | `og:image` used when nothing more specific is set. Absolute http(s) URL, or root-relative path. |
| `twitterSite` | `?string` | No | `null` | `twitter:site`. Must start with `@`, 1–15 letters, digits or `_`. |
| `locale` | `?string` | No | `null` | `og:locale` in `ll_TT` form (`ja_JP`). `null` derives it from `get_locale()`; WordPress's `ja` becomes `ja_JP`. |
| `organization` | `?OrganizationConfig` | No | `null` | See [OrganizationConfig](organizationconfig.md). `null` outputs no `Organization` node. |
| `sitemap` | `SitemapConfig` | No | `new SitemapConfig()` | See [SitemapConfig](sitemapconfig.md). |

## Notes

- Invalid values throw `InvalidArgumentException` from the constructor, on `init` — where you are
  looking — rather than producing broken markup later.
- Register it at an `init` priority below 20 (the default 10 is fine). TORO moves core's sitemap
  bootstrap to `init` 20 so that it sees this configuration.
- Calling `Seo::setSite()` a second time replaces the first configuration.
- Without `Seo::setSite()` the defaults above apply.
