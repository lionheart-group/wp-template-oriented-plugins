<?php

/**
 * @link https://www.lionheart.co.jp/
 * @since 0.0.1
 * @package Tofu
 *
 * @wordpress-plugin
 * Plugin Name: TOFU
 * Plugin URI: https://www.lionheart.co.jp/
 * Description: Template-Oriented Form Utilities
 * Version: 0.0.1
 * Author: LionHeart Group
 * Author URI: https://www.lionheart.co.jp/
 * Text Domain: template-oriented-form-utilities
 * Domain Path: /languages
 * Requires PHP: 8.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('TOFU_VERSION', '0.0.1');
define('TOFU_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Load autoloader
require_once __DIR__ . '/vendor/autoload.php';

use TofuPlugin\Init\Initializer;
use TofuPlugin\Init\Endpoint;
use TofuPlugin\Init\Migrate;
use TofuPlugin\Logger;

// Prepare Logger
Logger::init('tofu');

// Prepare Migrate Class
Migrate::prepareWpdb();

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

// Initialize endpoint
Endpoint::init();
