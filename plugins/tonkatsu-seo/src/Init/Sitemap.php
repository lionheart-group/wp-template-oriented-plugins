<?php

namespace ToroPlugin\Init;

use ToroPlugin\Consts;
use ToroPlugin\Helpers\Seo;
use ToroPlugin\Models\Context;
use ToroPlugin\Models\Resolver;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Adjusts WordPress core's sitemaps (`/wp-sitemap.xml`) from SitemapConfig
 * and the registered pages. TORO generates no sitemap of its own.
 */
class Sitemap
{
    /**
     * Excluded post IDs per post type, computed once per request.
     *
     * @var array<string, int[]>
     */
    protected static array $excludedPostIds = [];

    /**
     * Register the hooks. Call once, before `init`.
     */
    public static function register(): void
    {
        static::deferCoreServer();

        add_filter('wp_sitemaps_enabled', [static::class, 'filterEnabled']);
        add_filter('wp_sitemaps_add_provider', [static::class, 'filterProvider'], 10, 2);
        add_filter('wp_sitemaps_post_types', [static::class, 'filterPostTypes']);
        add_filter('wp_sitemaps_taxonomies', [static::class, 'filterTaxonomies']);
        add_filter('wp_sitemaps_posts_query_args', [static::class, 'filterPostsQueryArgs'], 10, 2);
    }

    /**
     * Move core's sitemap bootstrap from `init` 10 to `init` 20.
     *
     * Core evaluates `wp_sitemaps_enabled` and adds its providers while
     * building the server, on `init` at priority 10. Core's callback was
     * added first, so it runs before a theme's own `init` callback at the
     * same priority — i.e. before the theme has called Seo::setSite(). Only
     * the defaults would ever reach those two filters.
     */
    public static function deferCoreServer(): void
    {
        $priority = has_action('init', 'wp_sitemaps_get_server');

        if ($priority === false || $priority >= Consts::SITEMAP_INIT_PRIORITY) {
            return;
        }

        remove_action('init', 'wp_sitemaps_get_server', $priority);
        add_action('init', static function (): void {
            wp_sitemaps_get_server();
        }, Consts::SITEMAP_INIT_PRIORITY);
    }

    /**
     * @param mixed $enabled
     * @return bool
     */
    public static function filterEnabled(mixed $enabled): bool
    {
        return Seo::getSite()->sitemap->enabled && (bool) $enabled;
    }

    /**
     * Drop excluded providers. Core skips anything that is not a provider.
     *
     * @param mixed $provider
     * @param mixed $name
     * @return mixed
     */
    public static function filterProvider(mixed $provider, mixed $name = null): mixed
    {
        if (is_string($name) && in_array($name, Seo::getSite()->sitemap->excludeProviders, true)) {
            return false;
        }

        return $provider;
    }

    /**
     * @param mixed $postTypes Post type objects keyed by name.
     * @return mixed
     */
    public static function filterPostTypes(mixed $postTypes): mixed
    {
        if (!is_array($postTypes)) {
            return $postTypes;
        }

        return array_diff_key($postTypes, array_flip(Seo::getSite()->sitemap->excludePostTypes));
    }

    /**
     * @param mixed $taxonomies Taxonomy objects keyed by name.
     * @return mixed
     */
    public static function filterTaxonomies(mixed $taxonomies): mixed
    {
        if (!is_array($taxonomies)) {
            return $taxonomies;
        }

        return array_diff_key($taxonomies, array_flip(Seo::getSite()->sitemap->excludeTaxonomies));
    }

    /**
     * Keep noindex posts out of the post type's sitemap.
     *
     * @param mixed $args WP_Query arguments.
     * @param mixed $postType
     * @return mixed
     */
    public static function filterPostsQueryArgs(mixed $args, mixed $postType = null): mixed
    {
        if (!is_array($args) || !is_string($postType)) {
            return $args;
        }

        $ids = static::excludedPostIds($postType);
        if ($ids === []) {
            return $args;
        }

        $existing = isset($args['post__not_in']) && is_array($args['post__not_in']) ? $args['post__not_in'] : [];
        $args['post__not_in'] = array_values(array_unique(array_merge(array_map('intval', $existing), $ids)));

        return $args;
    }

    /**
     * IDs of $postType posts to leave out of the sitemap.
     *
     * Only registered pages are examined — each resolved in full, so both a
     * PageConfig noindex and a `toro_post_values` noindex count. Checking
     * `toro_post_values` for every post would mean running the filter over
     * the entire post table on each sitemap request; posts outside the
     * registry are added through `toro_sitemap_excluded_post_ids` instead.
     *
     * @param string $postType
     * @return int[]
     */
    public static function excludedPostIds(string $postType): array
    {
        if (isset(static::$excludedPostIds[$postType])) {
            return static::$excludedPostIds[$postType];
        }

        $ids = [];
        foreach (array_keys(Seo::getPages()) as $path) {
            $post = Context::findPost((string) $path);
            if ($post === null || $post->post_type !== $postType) {
                continue;
            }

            if (Resolver::fromContext(Context::forPost($post))->noindex()) {
                $ids[] = $post->ID;
            }
        }

        /**
         * Filters the post IDs excluded from a post type's sitemap.
         *
         * @param int[]  $ids
         * @param string $postType
         */
        $filtered = apply_filters('toro_sitemap_excluded_post_ids', $ids, $postType);

        return static::$excludedPostIds[$postType] = self::sanitizeIds(is_array($filtered) ? $filtered : $ids);
    }

    /**
     * Positive integer IDs (ints or digit strings), unique, in order.
     *
     * @param array<array-key, mixed> $ids
     * @return int[]
     */
    private static function sanitizeIds(array $ids): array
    {
        $clean = [];
        foreach ($ids as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $id = (int) $id;
                if ($id > 0) {
                    $clean[$id] = $id;
                }
            }
        }

        return array_values($clean);
    }
}
