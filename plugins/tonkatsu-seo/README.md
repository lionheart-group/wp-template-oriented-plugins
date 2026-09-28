# TORO

Template-Oriented Rank Optimizer

## Description

This plugin outputs SEO metadata for WordPress — title, meta description, canonical, robots, OGP, Twitter Card and JSON-LD — and adjusts WordPress core's sitemaps. All of it is configured in theme PHP code: nothing is stored in the database and there is no settings screen, so SEO configuration is version-controlled with the theme that renders the pages.

It is a sibling of [TOFU](https://github.com/lionheart-group/template-oriented-form-utilities) and follows the same conventions.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/template-oriented-rank-optimizer` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Deactivate any other SEO plugin (Rank Math, Yoast SEO, All in One SEO, SEOPress). While one is active, TORO outputs nothing and shows an admin notice.

## Usage

1. Register the site settings in your theme on `init`.
2. Register per-page, per-archive and per-taxonomy values as needed.
3. Use the `toro_post_values` filter for values that come from post data (custom fields).

```php
add_action('init', function () {
    if (!class_exists('ToroPlugin\Helpers\Seo')) {
        return;
    }

    \ToroPlugin\Helpers\Seo::setSite(new \ToroPlugin\Structure\SiteConfig(
        defaultDescription: 'Example Inc. makes widgets.',
        defaultOgImage: get_theme_file_uri('images/ogp.png'),
    ));

    \ToroPlugin\Helpers\Seo::registerPage('company', new \ToroPlugin\Structure\PageConfig(
        title: '会社概要',
        description: 'Example Inc. の会社概要です。',
    ));
});
```

[Detailed Documentation](docs/index.md)
