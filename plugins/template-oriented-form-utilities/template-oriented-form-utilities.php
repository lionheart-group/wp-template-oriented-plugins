<?php

/**
 * @link https://sus-happy.net/
 * @since 0.0.1
 * @package Tofu
 *
 * @wordpress-plugin
 * Plugin Name: TOFU
 * Plugin URI: https://tofu.sus-happy.net
 * Description: Template-Oriented Form Utilities
 * Version: 0.0.1
 * Author: SUSH
 * Author URI: https://sus-happy.net/
 * Text Domain: template-oriented-form-utilities
 * Domain Path: /languages
 * Requires PHP: 7.4
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

use TofuPlugin\Init\Activate;
use TofuPlugin\Init\Deactivate;
use TofuPlugin\Init\Endpoint;
use TofuPlugin\Logger;

// Register hooks that are fired when the plugin is activated or deactivated.
register_activation_hook(__FILE__, function () {
    Activate::activate();
});

register_deactivation_hook(__FILE__, function () {
    Deactivate::deactivate();
});

// Prepare Logger
Logger::init('tofu');

// Initialize endpoint
Endpoint::init();
