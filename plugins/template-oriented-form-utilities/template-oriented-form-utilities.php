<?php

/**
 * @link https://www.lionheart.co.jp/
 * @since 0.0.1
 * @package Tofu
 *
 * @wordpress-plugin
 * Plugin Name: TOFU (Template-Oriented Form Utilities)
 * Plugin URI: https://www.lionheart.co.jp/
 * Description: Template-Oriented Form Utilities is a WordPress plugin that provides a set of utilities for handling forms in a template-oriented manner.
 * Version: 0.0.1
 * Author: LionHeart Group
 * Author URI: https://www.lionheart.co.jp/
 * Text Domain: template-oriented-form-utilities
 * Domain Path: /languages
 * Requires PHP: 8.2
 * License: GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 */

// If this file is called directly, abort.
if ( !defined('WPINC') || !defined('ABSPATH') ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('TOFU_VERSION', '0.0.1');
define('TOFU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TOFU_PLUGIN_FILE', __FILE__);

// Load autoloader
require_once __DIR__ . '/vendor/autoload.php';

use TofuPlugin\Consts;
use TofuPlugin\Helpers\Session;
use TofuPlugin\Helpers\Uploader;
use TofuPlugin\Init\Initializer;
use TofuPlugin\Init\Endpoint;
use TofuPlugin\Logger;

// Prepare Logger
Logger::init('tofu');

// Register hooks that are fired when the plugin is activated or deactivated.
register_activation_hook(__FILE__, function () {
    Initializer::activate();
});

register_deactivation_hook(__FILE__, function () {
    Initializer::deactivate();
});

// Register hooks that are fired when the plugin is upgraded.
add_action('upgrade_process_complete', function ($upgrader_object, $options) {
    $current_plugin_path_name = plugin_basename(__FILE__);
    if ($options['action'] === 'update' && $options['type'] === 'plugin' && in_array($current_plugin_path_name, $options['plugins'])) {
        Initializer::upgrade();
    }
}, 10, 2);

// Register hooks that are fired when the sendmail is failed
add_action('wp_mail_failed', function ($wp_error) {
    Logger::error('Mail sending failed: ' . $wp_error->get_error_message());
}, 10, 1);

// Garbage collection for expired sessions
add_action('init', function () {
    $rand = wp_rand(1, 100);

    if ($rand <= Consts::GARBAGE_COLLECTION_PERCENTAGE) {
        Session::clearExpired();
        Uploader::clearExpired();
    }
});

// Initialize endpoint
Endpoint::init();
