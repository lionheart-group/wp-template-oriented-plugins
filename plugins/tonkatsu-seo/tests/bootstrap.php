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
// direct-access guard (every class under Init/) don't silently kill the PHP
// process the moment PHPUnit's autoloader touches one of those classes.
if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

/**
 * Minimal stand-in for core's WP_Post.
 *
 * Core's class is final and takes a row object; so does this one, which is
 * all the Resolver and the filters need — an instance to pass along and a
 * few public properties.
 */
if (!class_exists('WP_Post')) {
    final class WP_Post
    {
        public $ID = 0;
        public $post_type = 'post';
        public $post_title = '';
        public $post_excerpt = '';
        public $post_password = '';

        public function __construct($post)
        {
            foreach (get_object_vars($post) as $key => $value) {
                $this->$key = $value;
            }
        }
    }
}

/**
 * Minimal gettext catalogue read straight from languages/*.po.
 *
 * Reads the .po rather than the compiled .mo so the source of truth in the
 * repository is what gets tested, and so a stale .mo cannot mask a missing
 * translation.
 *
 * @return array<string, string> Keyed by msgid, or "context\4msgid".
 */
function toro_test_load_translations(string $locale): array {
    static $cache = [];

    if (isset($cache[$locale])) {
        return $cache[$locale];
    }

    $language = explode('_', $locale)[0];
    $path = dirname(__DIR__) . '/languages/template-oriented-rank-optimizer-' . $language . '.po';

    $catalogue = [];
    if (is_file($path)) {
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

if (!function_exists('get_locale')) {
    // Tests can steer the resolved locale by setting this global directly,
    // e.g. $GLOBALS['__toro_test_locale'] = 'ja';
    function get_locale(): string {
        return $GLOBALS['__toro_test_locale'] ?? 'en_US';
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        $catalogue = toro_test_load_translations(get_locale());

        return $catalogue[$text] ?? $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return esc_html(__($text, $domain));
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    // Not core's full sanitizer: enough to prove URL-valued tags go through
    // esc_url() rather than esc_attr() (core encodes & as &#038;).
    function esc_url($url) {
        return str_replace(['&', '"', "'", '<', '>'], ['&#038;', '&#034;', '&#039;', '%3C', '%3E'], (string) $url);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
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

if (!function_exists('home_url')) {
    function home_url(string $path = '', $scheme = null): string {
        $url = $GLOBALS['__toro_test_home_url'] ?? 'https://example.com';
        return rtrim($url, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo(string $show = '', string $filter = 'raw'): string {
        return $show === 'name' ? 'Example Site' : '';
    }
}

// Minimal hook registry, the same one TOFU's tests use: registration,
// priority ordering and $accepted_args — enough to assert on the filters the
// plugin applies.
//
// BaseTestCase resets $GLOBALS['__toro_hooks'] between tests — a callback left
// registered by one test would otherwise fire in every later one.
$GLOBALS['__toro_hooks'] = [];

if (!function_exists('add_filter')) {
    function add_filter(string $tag, callable $callback, int $priority = 10, int $accepted_args = 1): bool {
        $GLOBALS['__toro_hooks'][$tag][$priority][] = [
            'callback' => $callback,
            'accepted_args' => $accepted_args,
        ];
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $tag, $value, ...$args) {
        $hooks = $GLOBALS['__toro_hooks'][$tag] ?? [];
        if ($hooks === []) {
            return $value;
        }

        ksort($hooks);

        foreach ($hooks as $callbacks) {
            foreach ($callbacks as $hook) {
                // WordPress passes $accepted_args parameters, the filtered value
                // always being the first one.
                $params = array_slice(array_merge([$value], $args), 0, $hook['accepted_args']);
                $value = ($hook['callback'])(...$params);
            }
        }

        return $value;
    }
}

if (!function_exists('add_action')) {
    function add_action(string $tag, callable $callback, int $priority = 10, int $accepted_args = 1): bool {
        return add_filter($tag, $callback, $priority, $accepted_args);
    }
}

if (!function_exists('has_action')) {
    function has_action(string $tag, $callback = false) {
        foreach ($GLOBALS['__toro_hooks'][$tag] ?? [] as $priority => $callbacks) {
            foreach ($callbacks as $hook) {
                if ($hook['callback'] === $callback) {
                    return $priority;
                }
            }
        }
        return false;
    }
}

if (!function_exists('remove_action')) {
    function remove_action(string $tag, $callback, int $priority = 10): bool {
        foreach ($GLOBALS['__toro_hooks'][$tag][$priority] ?? [] as $i => $hook) {
            if ($hook['callback'] === $callback) {
                unset($GLOBALS['__toro_hooks'][$tag][$priority][$i]);
                return true;
            }
        }
        return false;
    }
}

// Options a test sets, e.g. $GLOBALS['__toro_test_options']['blog_public'] = '0';
if (!function_exists('get_option')) {
    function get_option(string $option, $default = false) {
        return $GLOBALS['__toro_test_options'][$option] ?? $default;
    }
}

// Minimal stand-ins for core's sitemap classes. A test registers a provider
// with $GLOBALS['__toro_test_sitemap_server']->registry->providers['posts'] = ...;
if (!class_exists('WP_Sitemaps_Provider')) {
    abstract class WP_Sitemaps_Provider {
        abstract public function get_url_list($page_num, $object_subtype = '');
    }
}

if (!class_exists('WP_Sitemaps_Registry')) {
    class WP_Sitemaps_Registry {
        /** @var array<string, WP_Sitemaps_Provider> */
        public array $providers = [];

        public function get_provider($name) {
            return $this->providers[$name] ?? null;
        }
    }
}

if (!class_exists('WP_Sitemaps')) {
    class WP_Sitemaps {
        public WP_Sitemaps_Registry $registry;

        public function __construct() {
            $this->registry = new WP_Sitemaps_Registry();
        }
    }
}

if (!function_exists('wp_sitemaps_get_server')) {
    function wp_sitemaps_get_server() {
        return $GLOBALS['__toro_test_sitemap_server'] ??= new WP_Sitemaps();
    }
}

// Permalinks a test sets, e.g. $GLOBALS['__toro_test_permalinks'][12] = 'https://example.com/a/';
if (!function_exists('get_permalink')) {
    function get_permalink($post = 0, $leavename = false) {
        $id = is_object($post) ? $post->ID : (int) $post;
        return $GLOBALS['__toro_test_permalinks'][$id] ?? false;
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) {
        return parse_url($url, $component);
    }
}

// Keeps only the allowed tags and attributes — enough for the admin cells.
if (!function_exists('wp_kses')) {
    function wp_kses($content, $allowed_html, $allowed_protocols = []) {
        return (string) preg_replace_callback('#</?([a-z]+)([^>]*)>#i', function ($m) use ($allowed_html) {
            $tag = strtolower($m[1]);
            if (!isset($allowed_html[$tag])) {
                return '';
            }
            preg_match_all('#\\s([a-z-]+)="[^"]*"#i', $m[2], $attrs, PREG_SET_ORDER);
            $kept = '';
            foreach ($attrs as $attr) {
                if (isset($allowed_html[$tag][strtolower($attr[1])])) {
                    $kept .= $attr[0];
                }
            }
            return str_starts_with($m[0], '</') ? "</{$tag}>" : "<{$tag}{$kept}>";
        }, (string) $content);
    }
}

// Same output shape as core's wp_get_inline_script_tag().
if (!function_exists('wp_print_inline_script_tag')) {
    function wp_print_inline_script_tag($data, $attributes = []) {
        $attrs = '';
        foreach ($attributes as $name => $value) {
            $attrs .= sprintf(' %s="%s"', $name, esc_attr($value));
        }
        echo "<script{$attrs}>\n" . trim($data, "\n\r ") . "\n</script>\n";
    }
}
