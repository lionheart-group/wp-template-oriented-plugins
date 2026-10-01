<?php

/**
 * PHPStan bootstrap file
 *
 * This file provides stubs and definitions for PHPStan analysis
 */

// Define WordPress constants
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 4) . '/');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

// Plugin constants, defined by tobiuo-content-structure.php at runtime
if (!defined('TOBIUO_VERSION')) {
    define('TOBIUO_VERSION', '0.0.0');
}

if (!defined('TOBIUO_PLUGIN_DIR')) {
    define('TOBIUO_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!defined('TOBIUO_PLUGIN_FILE')) {
    define('TOBIUO_PLUGIN_FILE', dirname(__DIR__) . '/tobiuo-content-structure.php');
}
