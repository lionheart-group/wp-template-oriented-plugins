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

// Plugin constants, defined by tonkatsu-seo.php at runtime
if (!defined('TONKATSU_VERSION')) {
    define('TONKATSU_VERSION', '0.0.0');
}

if (!defined('TONKATSU_PLUGIN_DIR')) {
    define('TONKATSU_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!defined('TONKATSU_PLUGIN_FILE')) {
    define('TONKATSU_PLUGIN_FILE', dirname(__DIR__) . '/tonkatsu-seo.php');
}
