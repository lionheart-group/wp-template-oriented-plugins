<?php

namespace TobiuoPlugin\Init;

use TobiuoPlugin\Helpers\Registry;
use TobiuoPlugin\Structure\PostsConfig;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * The archive and permalink of core's `post` post type (Structure\PostsConfig).
 *
 * `post` is not registered again. Its archive is set on the registered post
 * type object and gets core-style rules, since core registers `post` without
 * rewrite rules of its own (`init` 0, before the theme's config exists).
 *
 * Its permalink is the site's permalink structure, which stays core's
 * setting (Settings → Permalinks). The PostsConfig only says which structure
 * the theme expects; structureMismatch() compares the two for the admin page.
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

        add_filter('post_type_archive_link', [static::class, 'filterArchiveLink'], 10, 2);
        add_filter('register_post_type_args', [static::class, 'filterPostTypeArgs'], 10, 2);
    }

    /**
     * Give the `post` object the archive of the PostsConfig, and its rules.
     *
     * Called by Init\Registration::handOver() before it registers anything.
     */
    public static function apply(): void
    {
        global $wp_rewrite;

        $config = Registry::getPosts();
        if (!self::$enabled || $config === null || $config->archive === null || !$wp_rewrite instanceof \WP_Rewrite) {
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
     * The structure the theme expects, when the stored one differs from it.
     *
     * @param ?PostsConfig $config The theme's PostsConfig, if any.
     * @param string $stored The `permalink_structure` option.
     * @return ?string The expected structure (`/news/%postname%/`), or null
     *                 when the theme expects none or the stored one matches.
     */
    public static function structureMismatch(?PostsConfig $config, string $stored): ?string
    {
        $expected = $config?->permalinkStructure();

        return $expected !== null && $expected !== $stored ? $expected : null;
    }
}
