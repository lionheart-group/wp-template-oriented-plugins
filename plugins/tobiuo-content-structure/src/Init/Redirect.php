<?php

namespace TobiuoPlugin\Init;

use TobiuoPlugin\Consts;
use TobiuoPlugin\Helpers\Registry;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Redirects a post requested through a URL other than its permalink.
 *
 * The rewrite rules find a post by its name or ID; the term path in front of
 * it is not part of the query (see Rewrite::tags()), so
 * `/case/wrong-term/my-case/` finds the post as well. Rather than serving the
 * same post under several URLs, such a request is sent to the permalink
 * with a 301.
 */
class Redirect
{
    /**
     * Register the template_redirect action. Call once from the plugin bootstrap.
     */
    public static function register(): void
    {
        add_action('template_redirect', [static::class, 'maybeRedirect'], Consts::REDIRECT_PRIORITY);
    }

    /**
     * `template_redirect` callback.
     */
    public static function maybeRedirect(): void
    {
        global $wp, $wp_rewrite;

        if (!is_singular() || is_feed() || is_embed() || is_trackback() || is_preview() || is_attachment()) {
            return;
        }

        // Only requests that came in through a rewrite rule: `?case=my-case`
        // and other query-string forms are core's redirect_canonical()'s.
        if (!$wp instanceof \WP || empty($wp->matched_rule) || !$wp_rewrite instanceof \WP_Rewrite || !$wp_rewrite->using_permalinks()) {
            return;
        }

        $post = get_queried_object();
        if (!$post instanceof \WP_Post) {
            return;
        }

        $permalink = Registry::getPermalink($post->post_type);
        if ($permalink === null || !$permalink->hasContextTags()) {
            return;
        }

        // An endpoint (`/my-case/amp/`) is not part of the permalink, and
        // redirecting would drop it.
        foreach ($wp_rewrite->endpoints as $endpoint) {
            if (isset($endpoint[2]) && is_array($wp->query_vars) && array_key_exists($endpoint[2], $wp->query_vars)) {
                return;
            }
        }

        /**
         * Filters whether a post requested through another URL is redirected to its permalink.
         *
         * @param bool     $redirect Defaults to true.
         * @param \WP_Post $post     The requested post.
         */
        $redirect = apply_filters('tobiuo_redirect_canonical', true, $post);
        if ($redirect === false) {
            return;
        }

        $link = get_permalink($post);
        if (!is_string($link) || $link === '') {
            return;
        }

        // add_query_arg() with no arguments returns the request URI.
        $uri = add_query_arg([]);
        $target = self::target(
            (string) wp_parse_url($uri, PHP_URL_PATH),
            (string) wp_parse_url($uri, PHP_URL_QUERY),
            $link,
            (int) get_query_var('page'),
            (int) get_query_var('cpage'),
            $wp_rewrite->comments_pagination_base
        );

        if ($target !== null && wp_safe_redirect($target, 301, 'TOBIUO')) {
            exit;
        }
    }

    /**
     * Where to redirect a request, or null to serve it as it is.
     *
     * Only the permalink part of the requested path is compared: a page
     * number (`/2/`) and a comment page (`/comment-page-3/`) are taken off
     * first and put back on the target, and the query string is kept.
     * Trailing slashes and percent-encoding do not count as a difference;
     * the former is core's redirect_canonical()'s business.
     *
     * @param string $requestPath Path of the request URI, e.g. `/case/wrong/my-case/2/`.
     * @param string $query Query string of the request URI, without `?`.
     * @param string $permalink The post's permalink.
     * @param int $page The `page` query var (0 when absent).
     * @param int $cpage The `cpage` query var (0 when absent).
     * @param string $commentsPaginationBase WP_Rewrite::$comments_pagination_base.
     * @return ?string
     */
    public static function target(string $requestPath, string $query, string $permalink, int $page, int $cpage, string $commentsPaginationBase): ?string
    {
        $parts = wp_parse_url($permalink);

        // A plain permalink (`?case=…`) has nothing to compare against.
        if (!is_array($parts) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        $permalinkPath = $parts['path'] ?? '/';
        $path = rtrim($requestPath, '/');
        $suffix = '';

        if ($cpage > 0) {
            $segment = '/' . $commentsPaginationBase . '-' . $cpage;
            if (!str_ends_with($path, $segment)) {
                return null;
            }
            $path = substr($path, 0, -strlen($segment));
            $suffix = $segment;
        }

        if ($page > 0) {
            $segment = '/' . $page;
            if (!str_ends_with($path, $segment)) {
                return null;
            }
            $path = substr($path, 0, -strlen($segment));
            $suffix = ($page > 1 ? $segment : '') . $suffix;
        }

        if (self::normalize($path) === self::normalize($permalinkPath)) {
            return null;
        }

        $target = rtrim($permalink, '/') . $suffix;
        if (str_ends_with($permalinkPath, '/')) {
            $target .= '/';
        }

        return $query !== '' ? $target . '?' . $query : $target;
    }

    /**
     * @param string $path
     * @return string
     */
    private static function normalize(string $path): string
    {
        return trim((string) preg_replace('#/+#', '/', rawurldecode($path)), '/');
    }
}
