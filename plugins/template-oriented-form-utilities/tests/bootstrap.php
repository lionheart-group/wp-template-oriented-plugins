<?php

/**
 * PHPUnit bootstrap file
 */

// Composer autoloader must be loaded before anything else
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load Yoast PHPUnit Polyfills
require_once dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

// Define WordPress constants for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 4) . '/');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

// Mock WordPress $wpdb global if it doesn't exist
if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public $prefix = 'wp_';
        public $insert_id = 0;

        public function insert($table, $data, $format = null) {
            $this->insert_id = 1;
            return 1;
        }

        public function update($table, $data, $where, $format = null, $where_format = null) {
            return 1;
        }

        public function delete($table, $where, $where_format = null) {
            return 1;
        }

        public function prepare($query, ...$args) {
            return vsprintf(str_replace(['%s', '%d', '%f'], ["'%s'", '%d', '%f'], $query), $args);
        }

        public function get_results($query, $output = OBJECT) {
            return [];
        }

        public function get_row($query, $output = OBJECT, $y = 0) {
            return null;
        }

        public function get_var($query, $x = 0, $y = 0) {
            return null;
        }

        public function query($query) {
            return true;
        }
    };
}

// Define OBJECT constant if it doesn't exist
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

// Mock common WordPress functions if they don't exist
if (!function_exists('esc_sql')) {
    function esc_sql($data) {
        global $wpdb;
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = esc_sql($v);
            }
            return $data;
        }
        return addslashes($data);
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        echo $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        return [
            'path' => sys_get_temp_dir(),
            'url' => 'http://example.com/wp-content/uploads',
            'subdir' => '',
            'basedir' => sys_get_temp_dir(),
            'baseurl' => 'http://example.com/wp-content/uploads',
            'error' => false,
        ];
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) {
        if (is_dir($target)) {
            return true;
        }
        return @mkdir($target, 0755, true);
    }
}
