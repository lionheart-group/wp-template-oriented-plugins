<?php

namespace TonkatsuPlugin\Init;

use TonkatsuPlugin\Consts;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Detects another active SEO plugin.
 *
 * Two plugins printing canonicals, robots metas and OGP tags leave search
 * engines to pick between them, so while one is active TONKATSU outputs
 * nothing and shows an admin notice instead.
 */
class Conflict
{
    /**
     * The name of the first active conflicting plugin, or null.
     *
     * Call on `plugins_loaded` or later: before that, plugins loading after
     * TONKATSU have not defined their constants yet.
     *
     * @param array<string, string> $plugins Constant => plugin name.
     * @return ?string
     */
    public static function detect(array $plugins = Consts::CONFLICTING_PLUGINS): ?string
    {
        foreach ($plugins as $constant => $name) {
            if (defined($constant)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Register the admin notice.
     */
    public static function register(): void
    {
        add_action('admin_notices', [static::class, 'renderNotice']);
    }

    /**
     * The Plugins screen, where the other plugin can be deactivated, and
     * the TONKATSU screen. The notice stays off every other admin screen.
     *
     * @param string $screenId
     * @return bool
     */
    public static function shouldShowOn(string $screenId): bool
    {
        return in_array($screenId, ['plugins', 'tools_page_' . Consts::ADMIN_PAGE_SLUG], true);
    }

    /**
     * Tell the people who can fix it why TONKATSU is doing nothing.
     */
    public static function renderNotice(): void
    {
        $plugin = static::detect();
        if ($plugin === null || !current_user_can('activate_plugins')) {
            return;
        }

        $screen = get_current_screen();
        if ($screen === null || !self::shouldShowOn($screen->id)) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p>%s</p></div>',
            esc_html(sprintf(
                /* translators: %s: name of the other SEO plugin, e.g. "Rank Math SEO" */
                __('TONKATSU is not outputting any SEO tags or sitemap changes because %s is active. Deactivate it to let TONKATSU take over.', 'tonkatsu-seo'),
                $plugin
            ))
        );
    }
}
