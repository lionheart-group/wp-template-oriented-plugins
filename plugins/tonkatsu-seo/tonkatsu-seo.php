<?php

/**
 * @link https://www.lionheart.co.jp/
 * @since 0.0.1
 * @package Tonkatsu
 *
 * @wordpress-plugin
 * Plugin Name: TONKATSU (Template-Oriented No-database Knowledge-graph & Tag Setup Utility)
 * Plugin URI: https://github.com/lionheart-group/wp-template-oriented-plugins/tree/master/plugins/tonkatsu-seo
 * Description: TONKATSU outputs SEO metadata, structured data and sitemap adjustments configured entirely in theme code, with nothing stored in the database.
 * Version: 0.0.1
 * Author: lionheartgroup
 * Author URI: https://www.lionheart.co.jp/
 * Text Domain: tonkatsu-seo
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * License: GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('TONKATSU_VERSION', '0.0.1');
define('TONKATSU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TONKATSU_PLUGIN_FILE', __FILE__);

// Load autoloader
require_once __DIR__ . '/vendor/autoload.php';

use TonkatsuPlugin\Init\AdminColumns;
use TonkatsuPlugin\Init\AdminPage;
use TonkatsuPlugin\Init\Conflict;
use TonkatsuPlugin\Init\Head;
use TonkatsuPlugin\Init\SearchVisibilityNotice;
use TonkatsuPlugin\Init\Sitemap;

/**
 * Hook the front-end output once every plugin has loaded.
 *
 * Conflict detection cannot run in this file's body: plugins load in
 * alphabetical order, so Yoast SEO (`wordpress-seo`) has not defined its
 * constants yet when TONKATSU's main file runs. `plugins_loaded` still fires
 * before `init`, which Sitemap::register() relies on.
 */
add_action('plugins_loaded', function () {
    if (Conflict::detect() !== null) {
        // Another SEO plugin owns <head> and the sitemaps. Printing a second
        // set of tags would leave search engines picking between two
        // canonicals, so stand down and say so instead.
        Conflict::register();
        return;
    }

    Head::register();
    Sitemap::register();
    AdminColumns::register();
});

// Register the read-only admin page (Tools → SEO (TONKATSU))
AdminPage::register();

// Warn while "Discourage search engines" is on (Settings → Reading)
SearchVisibilityNotice::register();
