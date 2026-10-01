<?php

namespace TonkatsuPlugin\Init;

use TonkatsuPlugin\Helpers\Visibility;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Warn while "Discourage search engines from indexing this site" is on.
 *
 * The setting overrides everything TONKATSU resolves: every page is noindex and
 * the sitemaps are off. It is right on a staging site and a costly mistake
 * on a live one, so it is flagged on the screens where SEO is checked.
 */
class SearchVisibilityNotice
{
    /**
     * Register the admin_notices action. Call once from the plugin bootstrap.
     */
    public static function register(): void
    {
        add_action('admin_notices', [static::class, 'render']);
    }

    /**
     * Print the notice on the screens that show SEO values.
     */
    public static function render(): void
    {
        if (!Visibility::searchEnginesDiscouraged() || !current_user_can(AdminPage::capability())) {
            return;
        }

        $screen = get_current_screen();
        if ($screen === null || !self::shouldShowOn($screen->id, $screen->base, $screen->post_type)) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p><strong>%s</strong></p><p>%s <a href="%s">%s</a></p></div>',
            esc_html__('Search engines are discouraged from indexing this site.', 'tonkatsu-seo'),
            esc_html__('Every page is output as noindex and the XML sitemaps are disabled, whatever the SEO settings say. Uncheck the setting before the site goes live.', 'tonkatsu-seo'),
            esc_url(admin_url('options-reading.php')),
            esc_html__('Reading Settings', 'tonkatsu-seo')
        );
    }

    /**
     * The TONKATSU page and the list screens with SEO columns — the screens
     * that show SEO values — rather than the whole admin.
     *
     * @param string $screenId
     * @param string $screenBase
     * @param string $postType
     * @return bool
     */
    public static function shouldShowOn(string $screenId, string $screenBase, string $postType): bool
    {
        return AdminColumns::isSeoScreen($screenId, $screenBase, $postType);
    }
}
