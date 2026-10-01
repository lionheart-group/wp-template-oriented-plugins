<?php

/**
 * @link https://www.lionheart.co.jp/
 * @since 0.0.1
 * @package Tobiuo
 *
 * @wordpress-plugin
 * Plugin Name: TOBIUO (Template-Oriented Builder of Items, URLs & Organization)
 * Plugin URI: https://github.com/lionheart-group/wp-template-oriented-plugins/tree/master/plugins/tobiuo-content-structure
 * Description: TOBIUO registers post types, taxonomies and custom permalink structures configured entirely in theme code, with nothing stored in the database.
 * Version: 0.0.1
 * Author: lionheartgroup
 * Author URI: https://www.lionheart.co.jp/
 * Text Domain: tobiuo-content-structure
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
define('TOBIUO_VERSION', '0.0.1');
define('TOBIUO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TOBIUO_PLUGIN_FILE', __FILE__);

// Load autoloader and template functions
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/functions.php';

use TobiuoPlugin\Init\Activation;
use TobiuoPlugin\Init\AdminPage;
use TobiuoPlugin\Init\ArchiveLinks;
use TobiuoPlugin\Init\Conflict;
use TobiuoPlugin\Init\Permalink;
use TobiuoPlugin\Init\Redirect;
use TobiuoPlugin\Init\Registration;
use TobiuoPlugin\Init\Rewrite;

// Hand the registered taxonomies and post types to core on init
Registration::register();

/**
 * Hook the permalink handling once every plugin has loaded.
 *
 * Conflict detection waits for `plugins_loaded` so that it does not depend
 * on the order plugins load in. It still precedes `init`, where the post
 * types are registered and Rewrite::apply() runs.
 */
add_action('plugins_loaded', function () {
    if (Conflict::detect() !== null) {
        // Custom Post Type Permalinks replaces the same permastructs and
        // filters the same links. Keep the post types and taxonomies, leave
        // their URLs to it, and say so.
        Conflict::register();
        return;
    }

    Rewrite::register();
    Permalink::register();
    Redirect::register();
    ArchiveLinks::register();
});

// Register the read-only admin page (Tools → Content Structure (TOBIUO))
AdminPage::register();

// Have core regenerate the rewrite rules after activation and deactivation
Activation::register();
