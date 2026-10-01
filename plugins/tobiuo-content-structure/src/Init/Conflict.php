<?php

namespace TobiuoPlugin\Init;

use TobiuoPlugin\Consts;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Detects another active permalink plugin.
 *
 * Two plugins replacing the same permastruct and filtering the same links
 * leave each post with whichever URL ran last, so while Custom Post Type
 * Permalinks is active TOBIUO only registers the post types and taxonomies,
 * and shows an admin notice.
 */
class Conflict
{
    /**
     * The name of the first active conflicting plugin, or null.
     *
     * Call on `plugins_loaded` or later: before that, plugins loading after
     * TOBIUO have not defined their constants yet.
     *
     * @param array<string, string> $constants Constant => plugin name.
     * @param array<string, string> $classes Class => plugin name.
     * @return ?string
     */
    public static function detect(
        array $constants = Consts::CONFLICTING_PLUGINS,
        array $classes = Consts::CONFLICTING_CLASSES
    ): ?string {
        foreach ($constants as $constant => $name) {
            if (defined($constant)) {
                return $name;
            }
        }

        foreach ($classes as $class => $name) {
            if (class_exists($class, false)) {
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
     * the TOBIUO screen. The notice stays off every other admin screen.
     *
     * @param string $screenId
     * @return bool
     */
    public static function shouldShowOn(string $screenId): bool
    {
        return in_array($screenId, ['plugins', 'tools_page_' . Consts::ADMIN_PAGE_SLUG], true);
    }

    /**
     * Tell the people who can fix it why TOBIUO is not building permalinks.
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
                /* translators: %s: name of the other permalink plugin, e.g. "Custom Post Type Permalinks" */
                __('TOBIUO is registering the post types and taxonomies but not building their permalinks because %s is active. Deactivate it to let TOBIUO take over, then open Settings → Permalinks to regenerate the rewrite rules.', 'tobiuo-content-structure'),
                $plugin
            ))
        );
    }
}
