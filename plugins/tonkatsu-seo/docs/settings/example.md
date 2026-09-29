# Complete Example

Here's a complete example showing all configuration options:

```php
<?php
// functions.php

use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Structure\ArchiveConfig;
use TonkatsuPlugin\Structure\OrganizationConfig;
use TonkatsuPlugin\Structure\PageConfig;
use TonkatsuPlugin\Structure\SiteConfig;
use TonkatsuPlugin\Structure\SitemapConfig;

add_action('init', function () {
    // Keep the theme working while the plugin is deactivated.
    if (!class_exists('TonkatsuPlugin\Helpers\Seo')) {
        return;
    }

    // Site-wide defaults
    Seo::setSite(new SiteConfig(
        siteName: 'Example Inc.',
        separator: '|',
        defaultDescription: 'Example Inc. makes widgets for the whole world.',
        defaultOgImage: get_theme_file_uri('images/ogp.png'),
        twitterSite: '@example',
        locale: 'ja_JP',
        organization: new OrganizationConfig(
            name: '株式会社サンプル',
            url: home_url('/'),
            logo: get_theme_file_uri('images/logo.png'),
            sameAs: [
                'https://x.com/example',
                'https://www.facebook.com/example',
            ],
        ),
        sitemap: new SitemapConfig(
            excludeProviders: ['users'],
            excludePostTypes: ['attachment'],
            excludeTaxonomies: ['post_tag'],
        ),
    ));

    // Fixed pages
    Seo::registerPage('/', new PageConfig(
        title: 'Example Inc. — widgets since 1900',
        description: 'Example Inc. makes widgets for the whole world.',
    ));
    Seo::registerPage('/company/', new PageConfig(
        title: '会社概要',
        description: '株式会社サンプルの会社概要です。',
        ogImage: get_theme_file_uri('images/ogp-company.png'),
    ));

    // Or from a definition file
    Seo::registerPages([
        'contact'         => ['title' => 'お問い合わせ', 'description' => 'お問い合わせフォームです。'],
        'contact/confirm' => ['noindex' => true],
        'contact/thanks'  => ['noindex' => true, 'nofollow' => true],
    ]);

    // Archives
    Seo::registerArchive('news', new ArchiveConfig(
        title: 'お知らせ',
        description: '株式会社サンプルからのお知らせです。',
    ));
    Seo::registerTaxonomy('post_tag', new ArchiveConfig(noindex: true));
});

// Per-post values from custom fields
add_filter('tonkatsu_post_values', function (array $values, WP_Post $post): array {
    if ($post->post_type !== 'news') {
        return $values;
    }

    return array_merge($values, [
        'description' => (string) get_post_meta($post->ID, 'seo_description', true),
        'noindex'     => (bool) get_post_meta($post->ID, 'seo_noindex', true),
    ]);
}, 10, 2);
```

Empty values returned from `tonkatsu_post_values` are ignored, so the `get_post_meta()` calls above
can return `''` / `false` for posts that have nothing set, and the lower levels apply.
