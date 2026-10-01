<?php

namespace TobiuoPlugin\Init;

use TobiuoPlugin\Helpers\Registry;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * The archive and permalink of core's `post` post type (Structure\PostsConfig).
 *
 * `post` is not registered again. Its permalink is the site's permalink
 * structure, so it is supplied through `pre_option_permalink_structure`; its
 * archive is set on the registered post type object and gets core-style
 * rules, since core registers `post` without rewrite rules of its own.
 *
 * Timing: WP_Rewrite is built after `plugins_loaded` from the stored
 * structure, and the theme registers its PostsConfig on `init` only after
 * that, and after core has registered `post` (`init` 0). So apply(), called
 * by Init\Registration at the hand-over, brings WP_Rewrite and the `post`
 * object up to date (see reinitRewrite()).
 */
class Posts
{
    /**
     * Whether TOBIUO handles the posts configuration (no conflicting plugin).
     *
     * @var bool
     */
    protected static bool $enabled = false;

    /**
     * Register the filters. Call once from the plugin bootstrap.
     */
    public static function register(): void
    {
        self::$enabled = true;

        add_filter('pre_option_permalink_structure', [static::class, 'filterPermalinkStructure']);
        add_filter('post_type_archive_link', [static::class, 'filterArchiveLink'], 10, 2);
        add_filter('register_post_type_args', [static::class, 'filterPostTypeArgs'], 10, 2);
        add_action('admin_notices', [static::class, 'renderPermalinkNotice']);
    }

    /**
     * `pre_option_permalink_structure` callback: the structure from the PostsConfig.
     *
     * Returns $pre (false: read the option) until the theme has registered a
     * PostsConfig with a permalink.
     *
     * @param mixed $pre
     * @return mixed
     */
    public static function filterPermalinkStructure(mixed $pre): mixed
    {
        $structure = self::$enabled ? Registry::getPosts()?->permalinkStructure() : null;

        return $structure ?? $pre;
    }

    /**
     * Bring WP_Rewrite and the `post` object in line with the PostsConfig.
     *
     * Called by Init\Registration::handOver() before it registers anything.
     */
    public static function apply(): void
    {
        global $wp_rewrite;

        $config = Registry::getPosts();
        if (!self::$enabled || $config === null || !$wp_rewrite instanceof \WP_Rewrite) {
            return;
        }

        if ($config->permalink !== null) {
            self::reinitRewrite($wp_rewrite);
        }

        if ($config->archive === null) {
            return;
        }

        // Core registered `post` on init 0, before the theme's config existed,
        // so `register_post_type_args` came too late for it.
        $post = get_post_type_object('post');
        if ($post instanceof \WP_Post_Type) {
            $post->has_archive = $config->archive;
        }

        foreach (self::expectedRules() as $regex => $query) {
            add_rewrite_rule($regex, $query, 'top');
        }
    }

    /**
     * Re-read the permalink structure into WP_Rewrite, keeping what was added since.
     *
     * WP_Rewrite::init() is what core runs after changing the structure, but
     * it also empties the endpoints, the 'bottom' rules and the non-WP rules,
     * which plugins may have added on `init` already — those are put back.
     * What was built on the old front or root is moved to the new one: the
     * permastructs (core's categories and tags, other plugins' types) and
     * core's archive rules of post types with an archive.
     *
     * @param \WP_Rewrite $wpRewrite
     */
    public static function reinitRewrite(\WP_Rewrite $wpRewrite): void
    {
        if ($wpRewrite->permalink_structure === get_option('permalink_structure')) {
            return;
        }

        $oldFront = (string) $wpRewrite->front;
        $oldRoot = (string) $wpRewrite->root;
        $kept = [$wpRewrite->endpoints, $wpRewrite->extra_rules, $wpRewrite->non_wp_rules];

        $wpRewrite->init();

        [$wpRewrite->endpoints, $wpRewrite->extra_rules, $wpRewrite->non_wp_rules] = $kept;

        if ($oldFront === $wpRewrite->front && $oldRoot === $wpRewrite->root) {
            return;
        }

        $wpRewrite->extra_permastructs = self::rebasePermastructs(
            $wpRewrite->extra_permastructs,
            $oldFront,
            $wpRewrite->front,
            $oldRoot,
            $wpRewrite->root
        );

        $moves = [];
        foreach (get_post_types([], 'objects') as $postType) {
            if (!$postType instanceof \WP_Post_Type || !$postType->has_archive || !is_array($postType->rewrite)) {
                continue;
            }

            $slug = $postType->has_archive === true ? (string) ($postType->rewrite['slug'] ?? '') : (string) $postType->has_archive;
            [$old, $new] = !empty($postType->rewrite['with_front'])
                ? [substr($oldFront, 1) . $slug, substr($wpRewrite->front, 1) . $slug]
                : [$oldRoot . $slug, $wpRewrite->root . $slug];

            $moves += array_combine(
                self::archiveRuleKeys($old, $wpRewrite->pagination_base, $wpRewrite->feeds),
                self::archiveRuleKeys($new, $wpRewrite->pagination_base, $wpRewrite->feeds)
            );
        }

        $wpRewrite->extra_rules_top = self::renameRules($wpRewrite->extra_rules_top, $moves);
    }

    /**
     * Move permastructs from the old front (or root) to the new one.
     *
     * add_permastruct() prepends the front (`with_front`) or the root at the
     * time it is called, so structures added before the hand-over carry the
     * old one.
     *
     * @param array<string, mixed> $permastructs WP_Rewrite::$extra_permastructs.
     * @param string $oldFront
     * @param string $newFront
     * @param string $oldRoot
     * @param string $newRoot
     * @return array<string, mixed>
     */
    public static function rebasePermastructs(array $permastructs, string $oldFront, string $newFront, string $oldRoot, string $newRoot): array
    {
        foreach ($permastructs as $name => $args) {
            if (!is_array($args) || !isset($args['struct']) || !is_string($args['struct'])) {
                continue;
            }

            [$old, $new] = !empty($args['with_front']) ? [$oldFront, $newFront] : [$oldRoot, $newRoot];

            if ($old !== $new && str_starts_with($args['struct'], $old)) {
                $permastructs[$name]['struct'] = $new . substr($args['struct'], strlen($old));
            }
        }

        return $permastructs;
    }

    /**
     * Rename rule regexes, keeping their order and queries.
     *
     * @param array<string, string> $rules
     * @param array<string, string> $moves Old regex => new regex.
     * @return array<string, string>
     */
    public static function renameRules(array $rules, array $moves): array
    {
        $renamed = [];
        foreach ($rules as $regex => $query) {
            $renamed[$moves[$regex] ?? $regex] = $query;
        }

        return $renamed;
    }

    /**
     * The regexes core gives a post type archive, in core's order
     * (WP_Post_Type::add_rewrite_rules()).
     *
     * @param string $archiveSlug Including the front or root, e.g. `news`.
     * @param string $paginationBase WP_Rewrite::$pagination_base.
     * @param string[] $feeds WP_Rewrite::$feeds, or [] for no feed rules.
     * @return string[]
     */
    public static function archiveRuleKeys(string $archiveSlug, string $paginationBase, array $feeds): array
    {
        return array_keys(self::archiveRules($archiveSlug, $paginationBase, $feeds));
    }

    /**
     * Archive rules for posts, the same ones core gives a custom post type
     * with `has_archive`, as regex => query.
     *
     * @param string $archiveSlug Including the root, e.g. `news`.
     * @param string $paginationBase WP_Rewrite::$pagination_base.
     * @param string[] $feeds WP_Rewrite::$feeds, or [] for no feed rules.
     * @return array<string, string>
     */
    public static function archiveRules(string $archiveSlug, string $paginationBase, array $feeds): array
    {
        $rules = ["{$archiveSlug}/?$" => 'index.php?post_type=post'];

        if ($feeds !== []) {
            $feed = '(' . trim(implode('|', $feeds)) . ')';
            $rules["{$archiveSlug}/feed/{$feed}/?$"] = 'index.php?post_type=post&feed=$matches[1]';
            $rules["{$archiveSlug}/{$feed}/?$"] = 'index.php?post_type=post&feed=$matches[1]';
        }

        $rules["{$archiveSlug}/{$paginationBase}/([0-9]{1,})/?$"] = 'index.php?post_type=post&paged=$matches[1]';

        return $rules;
    }

    /**
     * The posts archive rules the current configuration needs.
     *
     * @return array<string, string>
     */
    public static function expectedRules(): array
    {
        global $wp_rewrite;

        $archive = Registry::getPosts()?->archive;
        if (!self::$enabled || $archive === null || !$wp_rewrite instanceof \WP_Rewrite) {
            return [];
        }

        return self::archiveRules($wp_rewrite->root . $archive, $wp_rewrite->pagination_base, $wp_rewrite->feeds);
    }

    /**
     * `post_type_archive_link` callback: the posts archive URL.
     *
     * Core returns the posts page (or the home page) for `post`.
     *
     * @param mixed $link
     * @param mixed $postType
     * @return mixed
     */
    public static function filterArchiveLink(mixed $link, mixed $postType = ''): mixed
    {
        global $wp_rewrite;

        $archive = Registry::getPosts()?->archive;
        if (!self::$enabled || $postType !== 'post' || $archive === null || !$wp_rewrite instanceof \WP_Rewrite) {
            return $link;
        }

        if (!$wp_rewrite->using_permalinks()) {
            return home_url('?post_type=post');
        }

        return home_url(user_trailingslashit($wp_rewrite->root . $archive, 'post_type_archive'));
    }

    /**
     * `register_post_type_args` callback: keep the archive if `post` is registered again later.
     *
     * @param mixed $args
     * @param mixed $postType
     * @return mixed
     */
    public static function filterPostTypeArgs(mixed $args, mixed $postType = ''): mixed
    {
        $archive = Registry::getPosts()?->archive;
        if (!self::$enabled || $postType !== 'post' || $archive === null || !is_array($args)) {
            return $args;
        }

        $args['has_archive'] = $archive;

        return $args;
    }

    /**
     * Settings → Permalinks only: its structure choice is overridden there.
     *
     * @param string $screenId
     * @return bool
     */
    public static function shouldShowNoticeOn(string $screenId): bool
    {
        return $screenId === 'options-permalink';
    }

    /**
     * Say on Settings → Permalinks that the structure comes from the theme.
     */
    public static function renderPermalinkNotice(): void
    {
        $structure = self::$enabled ? Registry::getPosts()?->permalinkStructure() : null;
        if ($structure === null || !current_user_can('manage_options')) {
            return;
        }

        $screen = get_current_screen();
        if ($screen === null || !self::shouldShowNoticeOn($screen->id)) {
            return;
        }

        printf(
            '<div class="notice notice-info"><p>%s <code>%s</code></p></div>',
            esc_html__('The permalink structure of posts is set in the theme\'s code with TOBIUO, so choosing another one here has no effect. In use:', 'tobiuo-content-structure'),
            esc_html($structure)
        );
    }
}
