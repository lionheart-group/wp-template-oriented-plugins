<?php

namespace TobiuoPlugin\Init;

use TobiuoPlugin\Helpers\Registry;
use TobiuoPlugin\Structure\PermalinkConfig;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Builds the permalinks of the post types that have a PermalinkConfig.
 *
 * Core's get_post_permalink() only fills in `%{post type}%`, so TOBIUO
 * rebuilds the link from the structure on `post_type_link`. A link never
 * keeps a literal `%tag%`: when a tag has no value (no term and no default
 * term) the post gets core's plain link instead, which always works.
 */
class Permalink
{
    /**
     * Register the post_type_link filter. Call once from the plugin bootstrap.
     */
    public static function register(): void
    {
        add_filter('post_type_link', [static::class, 'filterLink'], 10, 4);
    }

    /**
     * `post_type_link` callback.
     *
     * @param mixed $link Core's link.
     * @param mixed $post
     * @param mixed $leavename Keep `%{post type}%` in place of the post name (the editor's sample permalink).
     * @param mixed $sample Whether this is a sample permalink.
     * @return mixed
     */
    public static function filterLink(mixed $link, mixed $post, mixed $leavename = false, mixed $sample = false): mixed
    {
        global $wp_rewrite;

        if (!is_string($link) || !$post instanceof \WP_Post) {
            return $link;
        }

        $permalink = Registry::getPermalink($post->post_type);
        $postType = get_post_type_object($post->post_type);

        if ($permalink === null || !$postType instanceof \WP_Post_Type || !is_array($postType->rewrite)) {
            return $link;
        }

        if (!$wp_rewrite instanceof \WP_Rewrite || !$wp_rewrite->using_permalinks()) {
            return $link;
        }

        // Drafts, pending and other non-viewable posts: core has already
        // produced its plain link, the only one that works for them.
        if (wp_force_plain_post_permalink($post) && !$sample) {
            return $link;
        }

        $values = self::tagValues($post, $postType, $permalink, (bool) $leavename);
        $base = self::linkBase($postType->rewrite, $wp_rewrite->front, $wp_rewrite->root);
        $path = $values !== null ? self::buildPath($base, $permalink, $values) : null;

        if ($path === null) {
            return self::plainLink($post, $postType);
        }

        return home_url($path);
    }

    /**
     * The path of a post's link, from the base and the tag values.
     *
     * @param string $base What precedes the structure, e.g. `case` or `blog/case`.
     * @param PermalinkConfig $permalink
     * @param array<string, string> $values Tag => value, e.g. `%postname%` => `my-case`.
     * @return ?string Starting with `/`, ending with one when the structure does. Null when a tag has no value.
     */
    public static function buildPath(string $base, PermalinkConfig $permalink, array $values): ?string
    {
        preg_match_all('/%[a-z0-9_-]+%/', $permalink->structure, $matches);

        foreach ($matches[0] as $tag) {
            if (!isset($values[$tag]) || $values[$tag] === '') {
                return null;
            }
        }

        // strtr() replaces each tag once, so a value that looks like a tag
        // (`%case%` for the sample permalink) is left alone.
        $path = '/' . trim(trim($base, '/') . '/' . trim(strtr($permalink->structure, $values), '/'), '/');

        return $permalink->hasTrailingSlash() ? $path . '/' : $path;
    }

    /**
     * What precedes the structure: the front (or root) and the rewrite slug.
     *
     * @param array<string, mixed> $rewrite The post type's `rewrite`.
     * @param string $front WP_Rewrite::$front.
     * @param string $root WP_Rewrite::$root.
     * @return string
     */
    public static function linkBase(array $rewrite, string $front, string $root): string
    {
        $prefix = !empty($rewrite['with_front']) ? ltrim($front, '/') : $root;

        return trim($prefix . trim((string) ($rewrite['slug'] ?? ''), '/'), '/');
    }

    /**
     * The value of every tag the structure uses.
     *
     * @param \WP_Post $post
     * @param \WP_Post_Type $postType
     * @param PermalinkConfig $permalink
     * @param bool $leavename
     * @return ?array<string, string> Null when a tag has no value.
     */
    public static function tagValues(\WP_Post $post, \WP_Post_Type $postType, PermalinkConfig $permalink, bool $leavename): ?array
    {
        $structure = $permalink->structure;
        $values = [];

        if (str_contains($structure, '%postname%')) {
            if ($leavename) {
                // Core's sample permalink puts the parents in front itself.
                $values['%postname%'] = "%{$postType->name}%";
            } else {
                $values['%postname%'] = $postType->hierarchical ? self::hierarchicalName($post) : (string) $post->post_name;
            }
        }

        if (str_contains($structure, '%post_id%')) {
            $values['%post_id%'] = (string) $post->ID;
        }

        $date = self::dateParts((string) $post->post_date);
        foreach ($date as $tag => $value) {
            if (str_contains($structure, $tag)) {
                $values[$tag] = $value;
            }
        }

        if (str_contains($structure, '%author%')) {
            $author = get_userdata((int) $post->post_author);
            $values['%author%'] = $author instanceof \WP_User ? (string) $author->user_nicename : '';
        }

        foreach ($permalink->taxonomyTags() as $taxonomy) {
            $path = self::termPath($post, $taxonomy);
            if ($path === null) {
                return null;
            }
            $values["%{$taxonomy}%"] = $path;
        }

        return $values;
    }

    /**
     * Date tag values from a `Y-m-d H:i:s` post date (site time, as core uses).
     *
     * A post that has no date yet (`0000-00-00 00:00:00`) gets the current time,
     * which is what it will get when it is published now.
     *
     * @param string $postDate
     * @return array<string, string>
     */
    public static function dateParts(string $postDate): array
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', $postDate, $m) !== 1 || $m[1] === '0000') {
            preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', (string) current_time('mysql'), $m);
        }

        return [
            '%year%'     => $m[1] ?? '',
            '%monthnum%' => $m[2] ?? '',
            '%day%'      => $m[3] ?? '',
            '%hour%'     => $m[4] ?? '',
            '%minute%'   => $m[5] ?? '',
            '%second%'   => $m[6] ?? '',
        ];
    }

    /**
     * `parent/child/post` for a post of a hierarchical post type.
     *
     * Stops at a parent that is missing, has no slug or was already seen, so
     * a corrupted `post_parent` loop cannot hang the request.
     *
     * @param \WP_Post $post
     * @return string
     */
    public static function hierarchicalName(\WP_Post $post): string
    {
        $names = [(string) $post->post_name];
        $seen = [(int) $post->ID => true];
        $parentId = (int) $post->post_parent;

        while ($parentId > 0 && !isset($seen[$parentId])) {
            $seen[$parentId] = true;
            $parent = get_post($parentId);

            if (!$parent instanceof \WP_Post || (string) $parent->post_name === '') {
                break;
            }

            array_unshift($names, (string) $parent->post_name);
            $parentId = (int) $parent->post_parent;
        }

        return implode('/', $names);
    }

    /**
     * `parent/child` for the term a post's link uses in a taxonomy.
     *
     * The term is chosen by chooseTerm(), then offered to the
     * `tobiuo_post_link_term` filter; with no term at all, the taxonomy's
     * default term is used.
     *
     * @param \WP_Post $post
     * @param string $taxonomy
     * @return ?string Null when the post has no term and the taxonomy no default term.
     */
    public static function termPath(\WP_Post $post, string $taxonomy): ?string
    {
        $terms = get_the_terms($post, $taxonomy);
        $terms = is_array($terms) ? array_values(array_filter($terms, fn ($term) => $term instanceof \WP_Term)) : [];

        $ancestors = [];
        foreach ($terms as $term) {
            $ancestors[$term->term_id] = array_map('intval', get_ancestors($term->term_id, $taxonomy, 'taxonomy'));
        }

        $chosen = self::chooseTerm($terms, $ancestors);

        /**
         * Filters the term whose path a post's permalink uses.
         *
         * @param ?\WP_Term  $term     The term TOBIUO chose: the lowest term_id among the
         *                             assigned terms that are not an ancestor of another
         *                             assigned term. Null when the post has none.
         * @param \WP_Term[] $terms    The post's terms in the taxonomy.
         * @param string     $taxonomy
         * @param \WP_Post   $post
         */
        $filtered = apply_filters('tobiuo_post_link_term', $chosen, $terms, $taxonomy, $post);

        if ($filtered instanceof \WP_Term && $filtered->taxonomy === $taxonomy) {
            $chosen = $filtered;
        }

        $chosen ??= self::defaultTerm($taxonomy);

        return $chosen !== null ? self::termChain($chosen, $taxonomy) : null;
    }

    /**
     * The most specific assigned term: drop every term that is an ancestor
     * of another assigned term, then take the lowest term_id.
     *
     * @param \WP_Term[] $terms
     * @param array<int, int[]> $ancestors term_id => ancestor term IDs.
     * @return ?\WP_Term
     */
    public static function chooseTerm(array $terms, array $ancestors): ?\WP_Term
    {
        if ($terms === []) {
            return null;
        }

        $ancestorIds = [];
        foreach ($terms as $term) {
            foreach ($ancestors[$term->term_id] ?? [] as $id) {
                $ancestorIds[(int) $id] = true;
            }
        }

        $candidates = array_filter($terms, fn (\WP_Term $term) => !isset($ancestorIds[(int) $term->term_id]));
        if ($candidates === []) {
            // Only possible with a loop in the term parents; any choice is as good.
            $candidates = $terms;
        }

        usort($candidates, fn (\WP_Term $a, \WP_Term $b) => (int) $a->term_id <=> (int) $b->term_id);

        return $candidates[0];
    }

    /**
     * The taxonomy's default term, if it has one.
     *
     * Core keeps it in `default_term_{taxonomy}` (the `default_term`
     * argument of register_taxonomy()), and in `default_category` for
     * categories.
     *
     * @param string $taxonomy
     * @return ?\WP_Term
     */
    public static function defaultTerm(string $taxonomy): ?\WP_Term
    {
        $id = (int) get_option($taxonomy === 'category' ? 'default_category' : "default_term_{$taxonomy}");
        if ($id <= 0) {
            return null;
        }

        $term = get_term($id, $taxonomy);

        return $term instanceof \WP_Term ? $term : null;
    }

    /**
     * `grandparent/parent/term`.
     *
     * @param \WP_Term $term
     * @param string $taxonomy
     * @return string
     */
    public static function termChain(\WP_Term $term, string $taxonomy): string
    {
        $slugs = [(string) $term->slug];

        // get_ancestors() returns the closest first and stops at a loop.
        foreach (get_ancestors($term->term_id, $taxonomy, 'taxonomy') as $id) {
            $ancestor = get_term((int) $id, $taxonomy);
            if ($ancestor instanceof \WP_Term) {
                array_unshift($slugs, (string) $ancestor->slug);
            }
        }

        return implode('/', $slugs);
    }

    /**
     * Core's plain link: `?{query var}={name}`, or `?post_type={type}&p={ID}`.
     *
     * @param \WP_Post $post
     * @param \WP_Post_Type $postType
     * @return string
     */
    public static function plainLink(\WP_Post $post, \WP_Post_Type $postType): string
    {
        $name = $postType->hierarchical ? self::hierarchicalName($post) : (string) $post->post_name;

        if (!empty($postType->query_var) && (string) $post->post_name !== '') {
            return home_url(add_query_arg((string) $postType->query_var, $name, ''));
        }

        return home_url(add_query_arg(['post_type' => $post->post_type, 'p' => $post->ID], ''));
    }
}
