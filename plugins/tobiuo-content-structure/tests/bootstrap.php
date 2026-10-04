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

if (!defined('EP_PERMALINK')) {
    define('EP_PERMALINK', 1);
}

if (!defined('TOBIUO_PLUGIN_FILE')) {
    define('TOBIUO_PLUGIN_FILE', dirname(__DIR__) . '/tobiuo-content-structure.php');
}

if (!defined('TOBIUO_VERSION')) {
    define('TOBIUO_VERSION', '0.0.0');
}

/*
 * Minimal stand-ins for core's classes.
 *
 * Each takes what the code under test reads and nothing more. WP_Rewrite
 * keeps core's permastruct bookkeeping (add/remove/get) but cannot generate
 * rules — that is checked against a real install (see CLAUDE.md).
 */

if (!class_exists('WP_Post')) {
    final class WP_Post
    {
        public $ID = 0;
        public $post_type = 'post';
        public $post_name = '';
        public $post_title = '';
        public $post_status = 'publish';
        public $post_parent = 0;
        public $post_author = 0;
        public $post_date = '2024-05-12 09:08:07';

        public function __construct($post)
        {
            foreach (get_object_vars($post) as $key => $value) {
                $this->$key = $value;
            }
        }
    }
}

if (!class_exists('WP_Term')) {
    final class WP_Term
    {
        public $term_id = 0;
        public $slug = '';
        public $taxonomy = '';
        public $parent = 0;

        public function __construct($term)
        {
            foreach (get_object_vars($term) as $key => $value) {
                $this->$key = $value;
            }
        }
    }
}

if (!class_exists('WP_User')) {
    class WP_User
    {
        public $ID = 0;
        public $user_nicename = '';

        public function __construct(int $id = 0, string $nicename = '')
        {
            $this->ID = $id;
            $this->user_nicename = $nicename;
        }
    }
}

if (!class_exists('WP_Post_Type')) {
    final class WP_Post_Type
    {
        public $name;
        public $label = '';
        public $hierarchical = false;
        public $has_archive = false;
        public $rewrite = false;
        public $query_var = false;
        public $taxonomies = [];
        public $_builtin = false;

        public function __construct(string $name, array $args = [])
        {
            $this->name = $name;
            foreach ($args as $key => $value) {
                $this->$key = $value;
            }
        }
    }
}

if (!class_exists('WP_Taxonomy')) {
    final class WP_Taxonomy
    {
        public $name;
        public $label = '';
        public $object_type = [];
        public $rewrite = false;
        public $_builtin = false;

        public function __construct(string $name, array $objectType, array $args = [])
        {
            $this->name = $name;
            $this->object_type = $objectType;
            foreach ($args as $key => $value) {
                $this->$key = $value;
            }
        }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private string $message;

        public function __construct(string $code = '', string $message = '')
        {
            $this->message = $message;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }
    }
}

if (!class_exists('WP')) {
    class WP
    {
        public $matched_rule = '';
        public $query_vars = [];
    }
}

if (!class_exists('WP_Rewrite')) {
    class WP_Rewrite
    {
        public $permalink_structure = '';
        public $front = '';
        public $root = '';
        public $feeds = ['feed', 'rdf', 'rss', 'rss2', 'atom'];
        public $pagination_base = 'page';
        public $author_base = 'author';
        public $comments_pagination_base = 'comment-page';
        public $endpoints = [];
        public $extra_permastructs = [];
        public $extra_rules_top = [];
        public $extra_rules = [];
        public $non_wp_rules = [];
        public $use_trailing_slashes = false;
        public $matches = '';

        // As core, the structure comes from the option; a test passes it in
        // instead, and it is stored as the option.
        public function __construct(string $permalinkStructure = '/%postname%/')
        {
            $GLOBALS['__tobiuo_test_options']['permalink_structure'] = $permalinkStructure;
            $this->init();
        }

        // Core's init(), minus the index.php root detection and the verbose page rules.
        public function init()
        {
            $this->extra_rules = [];
            $this->non_wp_rules = [];
            $this->endpoints = [];
            $this->permalink_structure = (string) get_option('permalink_structure');
            $this->front = substr($this->permalink_structure, 0, (int) strpos($this->permalink_structure, '%'));
            $this->use_trailing_slashes = str_ends_with($this->permalink_structure, '/');
        }

        public function using_permalinks()
        {
            return $this->permalink_structure !== '';
        }

        // Same bookkeeping as core's add_permastruct(), minus wp_parse_args().
        public function add_permastruct($name, $struct, $args = [])
        {
            $args += [
                'with_front'  => true,
                'ep_mask'     => 0,
                'paged'       => true,
                'feed'        => true,
                'forcomments' => false,
                'walk_dirs'   => true,
                'endpoints'   => true,
            ];
            $args['struct'] = ($args['with_front'] ? $this->front : $this->root) . $struct;
            $this->extra_permastructs[$name] = $args;
        }

        public function remove_permastruct($name)
        {
            unset($this->extra_permastructs[$name]);
        }

        public function get_extra_permastruct($name)
        {
            return $this->extra_permastructs[$name]['struct'] ?? false;
        }

        public function get_year_permastruct()
        {
            return $this->using_permalinks() ? $this->front . '%year%/' : false;
        }

        public function get_month_permastruct()
        {
            return $this->using_permalinks() ? $this->front . '%year%/%monthnum%/' : false;
        }

        public function get_day_permastruct()
        {
            return $this->using_permalinks() ? $this->front . '%year%/%monthnum%/%day%/' : false;
        }

        // Not core's generator: one rule per permastruct is enough to check
        // that expectedRules() passes the stored arguments and the matches style.
        public function generate_rewrite_rules($struct, $ep_mask = 0, $paged = true, $feed = true, $forcomments = false, $walk_dirs = true, $endpoints = true)
        {
            $index = $this->matches === '' ? '$1' : '$' . $this->matches . '[1]';
            return [trim($struct, '/') . '/?$' => 'index.php?struct=' . $index];
        }
    }
}

/*
 * Translation and escaping.
 */

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('_n')) {
    function _n($single, $plural, $number, $domain = 'default') {
        return $number === 1 ? $single : $plural;
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
    // Not core's full sanitizer: enough to see that a URL went through it
    // (core encodes & as &#038;).
    function esc_url($url) {
        return str_replace(['&', '"', "'", '<', '>'], ['&#038;', '&#034;', '&#039;', '%3C', '%3E'], (string) $url);
    }
}

// Keeps only the allowed tags and attributes.
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

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = []) {
        $status = is_array($args) ? ($args['response'] ?? 500) : 500;
        throw new \RuntimeException(
            sprintf('wp_die called: [%d] %s — %s', $status, $title, $message)
        );
    }
}

/*
 * URLs.
 */

if (!function_exists('home_url')) {
    function home_url(string $path = '', $scheme = null): string {
        $url = $GLOBALS['__tobiuo_test_home_url'] ?? 'https://example.com';
        return rtrim($url, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) {
        return parse_url($url, $component);
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($value) {
        return rtrim((string) $value, '/\\') . '/';
    }
}

if (!function_exists('untrailingslashit')) {
    function untrailingslashit($value) {
        return rtrim((string) $value, '/\\');
    }
}

// Follows the site-wide structure's trailing slash, like core.
if (!function_exists('user_trailingslashit')) {
    function user_trailingslashit($url, $type_of_url = '') {
        $structure = ($GLOBALS['wp_rewrite'] ?? null)?->permalink_structure ?? '';
        return str_ends_with($structure, '/') ? trailingslashit($url) : untrailingslashit($url);
    }
}

// Enough of core's add_query_arg(): (key, value, url), (array, url), and
// (array) for the request URI.
if (!function_exists('add_query_arg')) {
    function add_query_arg(...$args) {
        if (is_array($args[0])) {
            $params = $args[0];
            $url = array_key_exists(1, $args) ? (string) $args[1] : ($_SERVER['REQUEST_URI'] ?? '');
        } else {
            $params = [$args[0] => $args[1]];
            $url = array_key_exists(2, $args) ? (string) $args[2] : ($_SERVER['REQUEST_URI'] ?? '');
        }

        [$base, $query] = array_pad(explode('?', $url, 2), 2, '');
        parse_str($query, $existing);
        $merged = array_merge($existing, $params);

        return $merged === [] ? $base : $base . '?' . http_build_query($merged);
    }
}

if (!function_exists('urlencode_deep')) {
    function urlencode_deep($value) {
        return is_array($value) ? array_map('urlencode_deep', $value) : urlencode((string) $value);
    }
}

/*
 * Hooks.
 *
 * Minimal hook registry, the same one TONKATSU's tests use: registration,
 * priority ordering and $accepted_args — enough to assert on the filters the
 * plugin applies. BaseTestCase resets $GLOBALS['__tobiuo_hooks'] between tests.
 */
$GLOBALS['__tobiuo_hooks'] = [];

if (!function_exists('add_filter')) {
    function add_filter(string $tag, callable $callback, int $priority = 10, int $accepted_args = 1): bool {
        $GLOBALS['__tobiuo_hooks'][$tag][$priority][] = [
            'callback' => $callback,
            'accepted_args' => $accepted_args,
        ];
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $tag, $value, ...$args) {
        $hooks = $GLOBALS['__tobiuo_hooks'][$tag] ?? [];
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

if (!function_exists('do_action')) {
    function do_action(string $tag, ...$args): void {
        $hooks = $GLOBALS['__tobiuo_hooks'][$tag] ?? [];
        ksort($hooks);

        foreach ($hooks as $callbacks) {
            foreach ($callbacks as $hook) {
                ($hook['callback'])(...array_slice($args, 0, $hook['accepted_args']));
            }
        }
    }
}

if (!function_exists('has_action')) {
    function has_action(string $tag, $callback = false) {
        foreach ($GLOBALS['__tobiuo_hooks'][$tag] ?? [] as $priority => $callbacks) {
            foreach ($callbacks as $hook) {
                if ($hook['callback'] === $callback) {
                    return $priority;
                }
            }
        }
        return false;
    }
}

if (!function_exists('has_filter')) {
    function has_filter(string $tag, $callback = false) {
        return has_action($tag, $callback);
    }
}

/*
 * Options, posts, terms and users, backed by globals a test fills in.
 */

// e.g. $GLOBALS['__tobiuo_test_options']['default_term_case_category'] = 7;
// `pre_option_{$option}` applies, as in core.
if (!function_exists('get_option')) {
    function get_option(string $option, $default = false) {
        $pre = apply_filters("pre_option_{$option}", false, $option, $default);
        if ($pre !== false) {
            return $pre;
        }
        return $GLOBALS['__tobiuo_test_options'][$option] ?? $default;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $option): bool {
        $existed = isset($GLOBALS['__tobiuo_test_options'][$option]);
        unset($GLOBALS['__tobiuo_test_options'][$option]);
        return $existed;
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        $now = $GLOBALS['__tobiuo_test_now'] ?? '2026-10-01 12:34:56';
        return $type === 'mysql' ? $now : date($type, (int) strtotime($now));
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool {
        return $thing instanceof WP_Error;
    }
}

// $GLOBALS['__tobiuo_test_posts'][12] = new WP_Post(...)
if (!function_exists('get_post')) {
    function get_post($post = null) {
        if ($post instanceof WP_Post) {
            return $post;
        }
        return $GLOBALS['__tobiuo_test_posts'][(int) $post] ?? null;
    }
}

// Viewable unless the status is draft/pending/auto-draft/future, as core decides for public types.
if (!function_exists('wp_force_plain_post_permalink')) {
    function wp_force_plain_post_permalink($post = null, $sample = null) {
        return in_array($post->post_status, ['draft', 'pending', 'auto-draft', 'future'], true);
    }
}

// $GLOBALS['__tobiuo_test_users'][3] = new WP_User(3, 'jane');
if (!function_exists('get_userdata')) {
    function get_userdata($id) {
        return $GLOBALS['__tobiuo_test_users'][(int) $id] ?? false;
    }
}

// $GLOBALS['__tobiuo_test_terms'][5] = new WP_Term((object) [...]);
if (!function_exists('get_term')) {
    function get_term($term, $taxonomy = '') {
        $term = $GLOBALS['__tobiuo_test_terms'][(int) $term] ?? null;
        if ($term === null || ($taxonomy !== '' && $term->taxonomy !== $taxonomy)) {
            return new WP_Error('invalid_term', 'Empty Term.');
        }
        return $term;
    }
}

// Walks the parents of the stubbed terms, stopping at a loop as core does.
if (!function_exists('get_ancestors')) {
    function get_ancestors($object_id = 0, $object_type = '', $resource_type = '') {
        $ancestors = [];
        $term = $GLOBALS['__tobiuo_test_terms'][(int) $object_id] ?? null;

        while ($term !== null && !empty($term->parent) && !in_array((int) $term->parent, $ancestors, true) && (int) $term->parent !== (int) $object_id) {
            $ancestors[] = (int) $term->parent;
            $term = $GLOBALS['__tobiuo_test_terms'][(int) $term->parent] ?? null;
        }

        return $ancestors;
    }
}

// $GLOBALS['__tobiuo_test_post_terms'][12]['case_category'] = [5, 6];
if (!function_exists('get_the_terms')) {
    function get_the_terms($post, $taxonomy) {
        $ids = $GLOBALS['__tobiuo_test_post_terms'][$post->ID][$taxonomy] ?? [];
        if ($ids === []) {
            return false;
        }
        return array_map(fn ($id) => $GLOBALS['__tobiuo_test_terms'][$id], $ids);
    }
}

// $GLOBALS['__tobiuo_test_permalinks'][12] = 'https://example.com/a/';
if (!function_exists('get_permalink')) {
    function get_permalink($post = 0, $leavename = false) {
        $id = is_object($post) ? $post->ID : (int) $post;
        return $GLOBALS['__tobiuo_test_permalinks'][$id] ?? false;
    }
}

/*
 * Registration, with the defaults core fills in that the plugin reads.
 */

if (!function_exists('register_taxonomy')) {
    function register_taxonomy($taxonomy, $object_type, $args = []) {
        // $GLOBALS['__tobiuo_test_registration_errors']['area'] = 'message';
        if (isset($GLOBALS['__tobiuo_test_registration_errors'][$taxonomy])) {
            return new WP_Error('invalid', $GLOBALS['__tobiuo_test_registration_errors'][$taxonomy]);
        }

        $GLOBALS['__tobiuo_test_log'][] = "taxonomy:{$taxonomy}";

        $rewrite = $args['rewrite'] ?? true;
        if ($rewrite !== false) {
            $rewrite = (is_array($rewrite) ? $rewrite : []) + ['slug' => $taxonomy, 'with_front' => true, 'hierarchical' => false];
        }

        return $GLOBALS['__tobiuo_test_taxonomies'][$taxonomy] = new WP_Taxonomy($taxonomy, (array) $object_type, [
            'label'   => $args['label'] ?? $taxonomy,
            'rewrite' => $rewrite,
        ]);
    }
}

if (!function_exists('register_post_type')) {
    function register_post_type($post_type, $args = []) {
        if (isset($GLOBALS['__tobiuo_test_registration_errors'][$post_type])) {
            return new WP_Error('invalid', $GLOBALS['__tobiuo_test_registration_errors'][$post_type]);
        }

        $GLOBALS['__tobiuo_test_log'][] = "post_type:{$post_type}";

        $hasArchive = $args['has_archive'] ?? false;
        $rewrite = $args['rewrite'] ?? true;
        if ($rewrite !== false) {
            $rewrite = (is_array($rewrite) ? $rewrite : []) + [
                'slug'       => $post_type,
                'with_front' => true,
                'pages'      => true,
                'feeds'      => (bool) $hasArchive,
                'ep_mask'    => EP_PERMALINK,
            ];
        }

        $object = new WP_Post_Type($post_type, [
            'label'        => $args['label'] ?? $post_type,
            'hierarchical' => $args['hierarchical'] ?? false,
            'has_archive'  => $hasArchive,
            'rewrite'      => $rewrite,
            'query_var'    => array_key_exists('query_var', $args) ? $args['query_var'] : $post_type,
            'taxonomies'   => $args['taxonomies'] ?? [],
        ]);

        // As core does in WP_Post_Type::add_rewrite_rules()
        if ($rewrite !== false && isset($GLOBALS['wp_rewrite'])) {
            $GLOBALS['wp_rewrite']->add_permastruct($post_type, "{$rewrite['slug']}/%{$post_type}%", ['feed' => $rewrite['feeds']] + $rewrite);
        }

        return $GLOBALS['__tobiuo_test_post_types'][$post_type] = $object;
    }
}

if (!function_exists('get_post_type_object')) {
    function get_post_type_object($post_type) {
        return $GLOBALS['__tobiuo_test_post_types'][$post_type] ?? null;
    }
}

if (!function_exists('get_taxonomy')) {
    function get_taxonomy($taxonomy) {
        return $GLOBALS['__tobiuo_test_taxonomies'][$taxonomy] ?? false;
    }
}

if (!function_exists('get_post_types')) {
    function get_post_types($args = [], $output = 'names') {
        $types = $GLOBALS['__tobiuo_test_post_types'] ?? [];
        return $output === 'objects' ? $types : array_combine(array_keys($types), array_keys($types));
    }
}

// Core's date and author archive links, under the front.
if (!function_exists('get_year_link')) {
    function get_year_link($year) {
        return home_url(user_trailingslashit($GLOBALS['wp_rewrite']->front . $year));
    }
}

if (!function_exists('get_month_link')) {
    function get_month_link($year, $month) {
        return home_url(user_trailingslashit($GLOBALS['wp_rewrite']->front . $year . '/' . sprintf('%02d', $month)));
    }
}

if (!function_exists('get_day_link')) {
    function get_day_link($year, $month, $day) {
        return home_url(user_trailingslashit($GLOBALS['wp_rewrite']->front . $year . '/' . sprintf('%02d/%02d', $month, $day)));
    }
}

if (!function_exists('get_author_posts_url')) {
    function get_author_posts_url($author_id, $author_nicename = '') {
        return home_url(user_trailingslashit($GLOBALS['wp_rewrite']->front . 'author/' . $author_nicename));
    }
}

if (!function_exists('get_taxonomies')) {
    function get_taxonomies($args = [], $output = 'names') {
        $names = array_keys($GLOBALS['__tobiuo_test_taxonomies'] ?? []);
        return array_combine($names, $names);
    }
}

if (!function_exists('get_object_taxonomies')) {
    function get_object_taxonomies($object_type) {
        $names = [];
        foreach ($GLOBALS['__tobiuo_test_taxonomies'] ?? [] as $name => $taxonomy) {
            if (in_array($object_type, $taxonomy->object_type, true)) {
                $names[] = $name;
            }
        }

        $postType = $GLOBALS['__tobiuo_test_post_types'][$object_type] ?? null;
        foreach ($postType->taxonomies ?? [] as $name) {
            if (isset($GLOBALS['__tobiuo_test_taxonomies'][$name]) && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }
}

if (!function_exists('add_rewrite_tag')) {
    function add_rewrite_tag($tag, $regex, $query = '') {
        $GLOBALS['__tobiuo_test_rewrite_tags'][$tag] = [$regex, $query];
    }
}

// Rules added with 'top', in order.
if (!function_exists('add_rewrite_rule')) {
    function add_rewrite_rule($regex, $query, $after = 'bottom') {
        $GLOBALS['__tobiuo_test_rewrite_rules'][$after][$regex] = $query;
    }
}

/*
 * Request conditionals, for Init\Redirect.
 *
 * $GLOBALS['__tobiuo_test_conditionals']['is_singular'] = true;
 */
function __tobiuo_test_conditional(string $name): bool {
    return $GLOBALS['__tobiuo_test_conditionals'][$name] ?? false;
}

if (!function_exists('is_singular')) {
    function is_singular() {
        return __tobiuo_test_conditional('is_singular');
    }
}

if (!function_exists('is_feed')) {
    function is_feed() {
        return __tobiuo_test_conditional('is_feed');
    }
}

if (!function_exists('is_embed')) {
    function is_embed() {
        return __tobiuo_test_conditional('is_embed');
    }
}

if (!function_exists('is_trackback')) {
    function is_trackback() {
        return __tobiuo_test_conditional('is_trackback');
    }
}

if (!function_exists('is_preview')) {
    function is_preview() {
        return __tobiuo_test_conditional('is_preview');
    }
}

if (!function_exists('is_attachment')) {
    function is_attachment() {
        return __tobiuo_test_conditional('is_attachment');
    }
}

if (!function_exists('get_queried_object')) {
    function get_queried_object() {
        return $GLOBALS['__tobiuo_test_queried_object'] ?? null;
    }
}

// $GLOBALS['__tobiuo_test_query_vars']['page'] = '2';
if (!function_exists('get_query_var')) {
    function get_query_var($name, $default = '') {
        return $GLOBALS['__tobiuo_test_query_vars'][$name] ?? $default;
    }
}

// Records the redirect and reports failure, so the caller does not exit().
if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect($location, $status = 302, $x_redirect_by = 'WordPress') {
        $GLOBALS['__tobiuo_test_redirect'] = [$location, $status];
        return false;
    }
}

// The template functions, loaded by the plugin's main file at runtime
require_once dirname(__DIR__) . '/src/functions.php';

// Settings → Permalinks section, recorded for the tests.
if (!function_exists('add_settings_section')) {
    function add_settings_section($id, $title, $callback, $page, $args = []) {
        $GLOBALS['__tobiuo_test_settings_sections'][] = ['id' => $id, 'title' => $title, 'callback' => $callback, 'page' => $page];
    }
}

// Every capability is granted except those listed in $GLOBALS['__tobiuo_test_cannot'].
if (!function_exists('current_user_can')) {
    function current_user_can($capability, ...$args) {
        return !in_array($capability, $GLOBALS['__tobiuo_test_cannot'] ?? [], true);
    }
}
