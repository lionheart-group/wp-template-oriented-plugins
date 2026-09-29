# PageConfig

SEO values for one URL path. Register with `Seo::registerPage()`, or several at once with
`Seo::registerPages()`.

## Usage

```php
use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Structure\PageConfig;

add_action('init', function () {
    if (!class_exists('TonkatsuPlugin\Helpers\Seo')) {
        return;
    }

    // Front page
    Seo::registerPage('/', new PageConfig(
        title: 'Example Inc. — widgets since 1900',
        description: 'Example Inc. makes widgets.',
    ));

    Seo::registerPage('/company/about/', new PageConfig(
        title: '会社概要',
        description: 'Example Inc. の会社概要です。',
        ogImage: get_theme_file_uri('images/ogp-company.png'),
    ));

    Seo::registerPage('/contact/thanks/', new PageConfig(noindex: true));
});
```

### From a definition file

`PageConfig::fromArray()` builds a `PageConfig` from an array whose keys are the constructor's
parameter names, and `Seo::registerPages()` accepts such arrays directly:

```php
// inc/seo-pages.php
return [
    '/'               => ['title' => 'Example Inc. — widgets since 1900'],
    'company/about'   => ['title' => '会社概要', 'description' => 'Example Inc. の会社概要です。'],
    'contact/thanks'  => ['noindex' => true],
];
```

```php
add_action('init', function () {
    if (!class_exists('TonkatsuPlugin\Helpers\Seo')) {
        return;
    }

    \TonkatsuPlugin\Helpers\Seo::registerPages(require get_theme_file_path('inc/seo-pages.php'));
});
```

Unknown keys throw `InvalidArgumentException`, naming the key and the page. A typo such as
`'no_index' => true` fails on `init` instead of silently leaving the page indexable. Values must
have the right type too: `'noindex' => 'yes'` is rejected rather than coerced.

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `title` | `?string` | No | `null` | Title part. WordPress still appends the page number and the site name. On the front page it replaces the site name and tagline. |
| `description` | `?string` | No | `null` | Meta description and `og:description`. |
| `ogImage` | `?string` | No | `null` | `og:image` (absolute, or root-relative). |
| `noindex` | `bool` | No | `false` | Adds `noindex` to the robots meta and removes the page from the sitemap. |
| `nofollow` | `bool` | No | `false` | Adds `nofollow` to the robots meta. |
| `canonical` | `?string` | No | `null` | Canonical URL override (absolute, or root-relative). |

## Notes

- Paths are relative to the home URL; slashes, query strings and percent-encoding do not matter.
  `''` or `/` is the front page. See [Paths](../index.md#paths).
- For singular requests the path comes from the post's permalink. Changing a page's slug
  therefore detaches it from its `PageConfig` — use `tonkatsu_post_values` for values that should
  follow the post rather than the URL.
- Registering the same path twice calls `wp_die()` — also when spelled differently
  (`/about/` and `about`).
- Empty strings are accepted and treated as "not set", so a definition file can carry placeholders.
- Search results and 404s never use a `PageConfig`.
