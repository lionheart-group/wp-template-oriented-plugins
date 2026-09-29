<?php

/**
 * @link https://www.lionheart.co.jp/
 * @since 0.0.1
 * @package Toro
 *
 * @wordpress-plugin
 * Plugin Name: TORO (Template-Oriented Rank Optimizer)
 * Plugin URI: https://github.com/lionheart-group/template-oriented-rank-optimizer
 * Description: Template-Oriented Rank Optimizer is a WordPress plugin that outputs SEO metadata, structured data and sitemap adjustments configured entirely in theme code.
 * Version: 0.0.1
 * Author: lionheartgroup
 * Author URI: https://www.lionheart.co.jp/
 * Text Domain: template-oriented-rank-optimizer
 * Domain Path: /languages
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
define('TORO_VERSION', '0.0.1');
define('TORO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TORO_PLUGIN_FILE', __FILE__);

// Load autoloader
require_once __DIR__ . '/vendor/autoload.php';

use ToroPlugin\Init\AdminColumns;
use ToroPlugin\Init\AdminPage;
use ToroPlugin\Init\Conflict;
use ToroPlugin\Init\Head;
use ToroPlugin\Init\SearchVisibilityNotice;
use ToroPlugin\Init\Sitemap;

/**
 * Load translations from the bundled languages/ directory.
 *
 * Runs on `init` because that is the earliest point translations are
 * allowed to load.
 */
add_action('init', function () {
    load_plugin_textdomain(
        'template-oriented-rank-optimizer',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
});

/**
 * Hook the front-end output once every plugin has loaded.
 *
 * Conflict detection cannot run in this file's body: plugins load in
 * alphabetical order, so Yoast SEO (`wordpress-seo`) has not defined its
 * constants yet when TORO's main file runs. `plugins_loaded` still fires
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

// Register the read-only admin page (Tools → SEO (TORO))
AdminPage::register();

// Warn while "Discourage search engines" is on (Settings → Reading)
SearchVisibilityNotice::register();
