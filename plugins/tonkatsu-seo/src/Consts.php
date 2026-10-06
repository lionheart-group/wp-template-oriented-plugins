<?php

namespace TonkatsuPlugin;

final class Consts
{
    /**
     * Default title separator, used when SiteConfig does not set one.
     */
    public const DEFAULT_SEPARATOR = '|';

    /**
     * Admin page slug (Tools → SEO (TONKATSU)).
     */
    public const ADMIN_PAGE_SLUG = 'tonkatsu-seo';

    /**
     * Capability required to view the admin page when the
     * `tonkatsu_admin_page_capability` filter returns nothing usable.
     */
    public const DEFAULT_CAPABILITY = 'manage_options';

    /**
     * `wp_head` priority for the meta/OGP/JSON-LD block.
     *
     * Same as core's `wp_robots`, so the SEO tags sit together near the top
     * of <head> rather than after every enqueued stylesheet.
     */
    public const HEAD_PRIORITY = 1;

    /**
     * `init` priority core's sitemap server is moved to.
     *
     * Core builds it on `init` at 10, which is before a theme's own `init`
     * callback at the same priority has registered SiteConfig — so
     * `wp_sitemaps_enabled` and `wp_sitemaps_add_provider` would only ever
     * see the default config. See Init\Sitemap::deferCoreServer().
     */
    public const SITEMAP_INIT_PRIORITY = 20;

    /**
     * Version of the redirect log tables (Models\RedirectLog::schema()).
     *
     * Raise it whenever the schema changes; the tables are brought up to date
     * on the next request, whatever way the plugin's files were updated.
     */
    public const DB_VERSION = '1';

    /**
     * `init` priority the redirect log tables are created at.
     *
     * After the theme's own `init` callback (10) has registered SiteConfig,
     * which says whether the log is wanted at all.
     */
    public const REDIRECT_LOG_INIT_PRIORITY = 100;

    /**
     * Flags for the JSON-LD block.
     *
     * JSON_HEX_TAG is not optional: with JSON_UNESCAPED_SLASHES a string
     * containing `</script>` would otherwise be printed verbatim and close
     * the script element early.
     */
    public const JSON_LD_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;

    /**
     * SEO plugins TONKATSU stands down for, keyed by a constant each defines.
     *
     * @var array<string, string>
     */
    public const CONFLICTING_PLUGINS = [
        'RANK_MATH_VERSION' => 'Rank Math SEO',
        'WPSEO_VERSION'     => 'Yoast SEO',
        'AIOSEO_VERSION'    => 'All in One SEO',
        'SEOPRESS_VERSION'  => 'SEOPress',
    ];

    /**
     * og:locale for WordPress locales that carry no region.
     *
     * Open Graph expects `ll_TT`; WordPress's Japanese locale is plain `ja`.
     *
     * @var array<string, string>
     */
    public const LOCALE_REGIONS = [
        'ja' => 'ja_JP',
    ];
}
