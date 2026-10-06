<?php

/**
 * Remove what TONKATSU stored: the redirect log tables and their schema version.
 *
 * TONKATSU stores no settings; these exist only on sites that turned on
 * SiteConfig::$logRedirects. The names match Models\RedirectLog's constants.
 *
 * @package Tonkatsu
 */

// If uninstall is not called from WordPress, abort.
defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Drop the tables and the option of the current site.
 */
function tonkatsu_uninstall_site(): void
{
    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removing the plugin's own tables on uninstall
    $wpdb->query($wpdb->prepare(
        'DROP TABLE IF EXISTS %i, %i',
        $wpdb->prefix . 'tonkatsu_redirect_stats',
        $wpdb->prefix . 'tonkatsu_redirect_log'
    ));

    delete_option('tonkatsu_db_version');
}

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $tonkatsu_site_id) {
        switch_to_blog((int) $tonkatsu_site_id);
        tonkatsu_uninstall_site();
        restore_current_blog();
    }
} else {
    tonkatsu_uninstall_site();
}
