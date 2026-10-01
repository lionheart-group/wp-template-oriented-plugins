<?php

namespace TonkatsuPlugin\Init;

use TonkatsuPlugin\Consts;
use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Models\Context;
use TonkatsuPlugin\Models\Resolver;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Adjusts WordPress core's sitemaps (`/wp-sitemap.xml`) from SitemapConfig
 * and the registered pages. TONKATSU generates no sitemap of its own.
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
     * True while filterPreUrlList() asks core for the unfiltered list.
     *
     * @var bool
     */
    protected static bool $buildingUrlList = false;

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
        add_filter('wp_sitemaps_posts_pre_url_list', [static::class, 'filterPreUrlList'], 10, 3);
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
     * Core offers no hook that drops a single entry, and adding
     * `post__not_in` to the query makes it hard to cache. Instead, core's own
     * list is built as usual — every other filter, the front page entry and
     * all — and the excluded posts are removed from it in PHP.
     *
     * The page count is still computed by core without the exclusion, so a
     * page that held an excluded post lists a few URLs fewer than the limit.
     *
     * @param mixed $urlList  Null unless another callback built the list.
     * @param mixed $postType
     * @param mixed $pageNum
     * @return mixed
     */
    public static function filterPreUrlList(mixed $urlList, mixed $postType = null, mixed $pageNum = 1): mixed
    {
        if ($urlList !== null || static::$buildingUrlList || !is_string($postType)) {
            return $urlList;
        }

        $ids = static::excludedPostIds($postType);
        if ($ids === []) {
            return $urlList;
        }

        $provider = wp_sitemaps_get_server()->registry->get_provider('posts');
        if ($provider === null) {
            return $urlList;
        }

        $excluded = [];
        foreach ($ids as $id) {
            $permalink = get_permalink($id);
            if (is_string($permalink)) {
                $excluded[$permalink] = true;
            }
        }

        static::$buildingUrlList = true;
        try {
            $list = $provider->get_url_list(is_numeric($pageNum) ? (int) $pageNum : 1, $postType);
        } finally {
            static::$buildingUrlList = false;
        }

        return array_values(array_filter(
            $list,
            static fn ($entry): bool => !(is_array($entry) && isset($entry['loc']) && isset($excluded[$entry['loc']]))
        ));
    }

    /**
     * IDs of $postType posts to leave out of the sitemap.
     *
     * Only registered pages are examined — each resolved in full, so both a
     * PageConfig noindex and a `tonkatsu_post_values` noindex count. Checking
     * `tonkatsu_post_values` for every post would mean running the filter over
     * the entire post table on each sitemap request; posts outside the
     * registry are added through `tonkatsu_sitemap_excluded_post_ids` instead.
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
        $filtered = apply_filters('tonkatsu_sitemap_excluded_post_ids', $ids, $postType);

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
