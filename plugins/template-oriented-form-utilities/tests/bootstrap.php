<?php

/**
 * PHPUnit bootstrap file
 */

// Composer autoloader must be loaded before anything else
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WordPress constants for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 4) . '/');
}

// Required so that files with the standard `if (!defined('WPINC')) { die; }`
// direct-access guard (Init/Endpoint.php, Init/AdminPage.php,
// Init/RestEndpoint.php, Init/Initializer.php) don't silently kill the PHP
// process the moment PHPUnit's autoloader touches one of those classes.
if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

// Encryption salt constants required by Encryptor::getKey()
if (!defined('AUTH_KEY')) {
    define('AUTH_KEY', 'test-auth-key-for-phpunit-only');
}
if (!defined('SECURE_AUTH_KEY')) {
    define('SECURE_AUTH_KEY', 'test-secure-auth-key-for-phpunit-only');
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
            $arg_index = 0;

            return preg_replace_callback(
                '/%([sdif])/',
                function ($matches) use (&$arg_index, $args) {
                    if (!array_key_exists($arg_index, $args)) {
                        return $matches[0];
                    }

                    $value = $args[$arg_index++];

                    switch ($matches[1]) {
                        case 'd':
                            return (string) (int) $value;
                        case 'f':
                            return (string) (float) $value;
                        case 'i':
                            // Identifier: backtick-quoted, no surrounding single quotes.
                            return '`' . str_replace('`', '``', (string) $value) . '`';
                        case 's':
                        default:
                            return "'" . (string) $value . "'";
                    }
                },
                $query
            );
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

/**
 * Minimal gettext catalogue read straight from languages/*.po.
 *
 * The __() stub used to be the identity function. That was fine while
 * validation messages lived in PHP arrays, but they now come from the
 * plugin's .po files — an identity stub would render every locale in
 * English and quietly make the Japanese half of the golden corpus
 * meaningless. Parsing the .po keeps the tests exercising the translations
 * that actually ship.
 *
 * Reads the .po rather than the compiled .mo so the source of truth in the
 * repository is what gets tested, and so a stale .mo cannot mask a missing
 * translation.
 *
 * @return array<string, string> Keyed by msgid, or "context\4msgid".
 */
function tofu_test_load_translations(string $locale): array {
    static $cache = [];

    if (isset($cache[$locale])) {
        return $cache[$locale];
    }

    $language = explode('_', $locale)[0];
    $path = dirname(__DIR__) . '/languages/template-oriented-form-utilities-' . $language . '.po';

    $catalogue = [];
    if (is_file($path)) {
        $context = null;
        $id = null;
        $field = null;
        $buffer = ['msgctxt' => '', 'msgid' => '', 'msgstr' => ''];

        $flush = static function () use (&$buffer, &$catalogue): void {
            if ($buffer['msgid'] !== '' && $buffer['msgstr'] !== '') {
                $key = $buffer['msgctxt'] !== ''
                    ? $buffer['msgctxt'] . "\4" . $buffer['msgid']
                    : $buffer['msgid'];
                $catalogue[$key] = $buffer['msgstr'];
            }
            $buffer = ['msgctxt' => '', 'msgid' => '', 'msgstr' => ''];
        };

        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                if ($line === '') {
                    $flush();
                    $field = null;
                }
                continue;
            }

            if (preg_match('/^(msgctxt|msgid|msgstr)\s+"(.*)"$/s', $line, $m) === 1) {
                $field = $m[1];
                $buffer[$field] = stripcslashes($m[2]);
                continue;
            }

            // Continuation line of the previous field.
            if ($field !== null && preg_match('/^"(.*)"$/s', $line, $m) === 1) {
                $buffer[$field] .= stripcslashes($m[1]);
            }
        }

        $flush();
    }

    return $cache[$locale] = $catalogue;
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        $catalogue = tofu_test_load_translations(get_locale());

        return $catalogue[$text] ?? $text;
    }
}

if (!function_exists('_x')) {
    function _x($text, $context, $domain = 'default') {
        $catalogue = tofu_test_load_translations(get_locale());

        return $catalogue[$context . "\4" . $text] ?? $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        echo __($text, $domain);
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

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = []) {
        $status = is_array($args) ? ($args['response'] ?? 500) : 500;
        throw new \RuntimeException(
            sprintf('wp_die called: [%d] %s — %s', $status, $title, $message)
        );
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password(int $length = 12, bool $special_chars = true, bool $extra_special_chars = false): string {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }
}

if (!function_exists('wp_unslash')) {
    // Mirrors WordPress's stripslashes_from_strings_only() behaviour: recurses
    // into arrays but leaves non-string scalars (int/float/bool/null) untouched
    // instead of coercing them to strings. This distinction matters for
    // validation rules whose behaviour depends on the value's PHP type
    // (e.g. numeric-string vs int) — see tests/Unit/Validation/.
    function wp_unslash($value) {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('is_ssl')) {
    function is_ssl(): bool {
        return false;
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = '', $scheme = null): string {
        $url = 'http://example.com';
        if ($path !== '') {
            $url .= '/' . ltrim($path, '/');
        }
        return $url;
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) {
        return $component === -1 ? parse_url($url) : parse_url($url, $component);
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type, bool $gmt = false): string|int {
        if ($type === 'timestamp' || $type === 'U') {
            return time();
        }
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('wp_timezone')) {
    function wp_timezone(): \DateTimeZone {
        return new \DateTimeZone('UTC');
    }
}

if (!function_exists('get_locale')) {
    // Tests can steer the resolved locale by setting this global directly,
    // e.g. $GLOBALS['__tofu_test_locale'] = 'ja_JP';
    function get_locale(): string {
        return $GLOBALS['__tofu_test_locale'] ?? 'en_US';
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $tag, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action(string $tag, ...$args): void {
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $tag, callable $callback, int $priority = 10, int $accepted_args = 1): bool {
        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action(string $tag, callable $callback, int $priority = 10, int $accepted_args = 1): bool {
        return true;
    }
}

if (!defined('COOKIEPATH')) {
    define('COOKIEPATH', '/');
}

if (!defined('COOKIE_DOMAIN')) {
    define('COOKIE_DOMAIN', '');
}

// Initialize TOFU Logger for tests (WP_DEBUG=true requires it to be set up before Form is constructed)
\TofuPlugin\Logger::init('test');

// Namespace-level mock for setcookie() — must be in its own file
require_once __DIR__ . '/bootstrap-helpers.php';
