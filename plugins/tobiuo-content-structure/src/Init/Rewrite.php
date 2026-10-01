<?php

namespace TobiuoPlugin\Init;

use TobiuoPlugin\Consts;
use TobiuoPlugin\Helpers\Registry;
use TobiuoPlugin\Structure\PermalinkConfig;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Rewrite rules for the post types that have a PermalinkConfig.
 *
 * Core's permastruct for the post type (`{slug}/%{post type}%`) is replaced
 * by one built from the structure, and date and author archive rules are
 * added below the post type archive. Everything else — attachments,
 * trackbacks, feeds, embeds, pagination, comment pages, endpoints — is left
 * to WP_Rewrite::generate_rewrite_rules(), as for any permastruct.
 *
 * The rules themselves come from pure functions (permastruct(), dateRules(),
 * authorRules(), …); apply() only passes them to core.
 */
class Rewrite
{
    /**
     * Hook in once the post types are registered. Call once from the plugin bootstrap.
     */
    public static function register(): void
    {
        add_action('tobiuo_registered', [static::class, 'apply']);
    }

    /**
     * Replace the permastruct and add the archive rules of every post type
     * that has a PermalinkConfig.
     */
    public static function apply(): void
    {
        global $wp_rewrite;

        if (!$wp_rewrite instanceof \WP_Rewrite) {
            return;
        }

        foreach (self::managedPostTypes() as [$postType, $permalink]) {
            foreach (self::tags($postType->name, $permalink) as $tag => [$regex, $query]) {
                add_rewrite_tag($tag, $regex, $query);
            }

            // Removed and added again rather than overwritten, so that it moves
            // after the taxonomy permastructs: a term archive such as
            // `case/category/(.+?)/?$` has to be matched before a post rule
            // such as `case/(.+?)/([^/]+)/?$` swallows it.
            $wp_rewrite->remove_permastruct($postType->name);
            $wp_rewrite->add_permastruct(
                $postType->name,
                self::permastruct($postType->name, (string) $postType->rewrite['slug'], $permalink),
                self::permastructArgs($postType->rewrite)
            );

            foreach (self::archiveRules($postType, $permalink, $wp_rewrite) as $regex => $query) {
                add_rewrite_rule($regex, $query, 'top');
            }
        }
    }

    /**
     * Post types TOBIUO builds the permalinks of, with their config.
     *
     * Those registered with a PermalinkConfig and rewrite rules; the others
     * are left to core.
     *
     * @return array<string, array{\WP_Post_Type, PermalinkConfig}> Keyed by post type.
     */
    public static function managedPostTypes(): array
    {
        $managed = [];

        foreach (Registry::getPostTypes() as $name => $config) {
            $postType = get_post_type_object($name);

            if ($config->permalink !== null && $postType instanceof \WP_Post_Type && is_array($postType->rewrite)) {
                $managed[$name] = [$postType, $config->permalink];
            }
        }

        return $managed;
    }

    /**
     * Date and author archive rules for a post type, as regex => query.
     *
     * @param \WP_Post_Type $postType
     * @param PermalinkConfig $permalink
     * @param \WP_Rewrite $wpRewrite
     * @return array<string, string>
     */
    public static function archiveRules(\WP_Post_Type $postType, PermalinkConfig $permalink, \WP_Rewrite $wpRewrite): array
    {
        $archiveSlug = self::archiveSlug($postType->has_archive, $postType->rewrite, $wpRewrite->front, $wpRewrite->root);
        if ($archiveSlug === null) {
            return [];
        }

        $feeds = !empty($postType->rewrite['feeds']) ? $wpRewrite->feeds : [];
        $rules = [];

        if ($permalink->dateArchive) {
            $rules += self::dateRules($archiveSlug . self::dateFront($permalink), $postType->name, $wpRewrite->pagination_base, $feeds);
        }

        if ($permalink->authorArchive) {
            $rules += self::authorRules($archiveSlug . '/' . $wpRewrite->author_base, $postType->name, $wpRewrite->pagination_base, $feeds);
        }

        return $rules;
    }

    /**
     * The permastruct that replaces core's `{slug}/%{post type}%`.
     *
     * `%postname%` becomes core's post type tag, `%{taxonomy}%` a private
     * term tag (Consts::TERM_TAG_PREFIX) and, when there is no `%postname%`,
     * `%post_id%` a private tag that carries the post type
     * (Consts::POST_ID_TAG_PREFIX). The other tags are core's own.
     *
     * @param string $postType
     * @param string $slug The post type's rewrite slug.
     * @param PermalinkConfig $permalink
     * @return string e.g. `case/%tobiuo_term_case_category%/%case%/`
     */
    public static function permastruct(string $postType, string $slug, PermalinkConfig $permalink): string
    {
        $replacements = ['%postname%' => "%{$postType}%"];

        if (!str_contains($permalink->structure, '%postname%')) {
            $replacements['%post_id%'] = '%' . Consts::POST_ID_TAG_PREFIX . $postType . '%';
        }

        foreach ($permalink->taxonomyTags() as $taxonomy) {
            $replacements["%{$taxonomy}%"] = '%' . Consts::TERM_TAG_PREFIX . $taxonomy . '%';
        }

        return trim($slug, '/') . strtr($permalink->structure, $replacements);
    }

    /**
     * The private rewrite tags a permastruct uses, as tag => [regex, query].
     *
     * @param string $postType
     * @param PermalinkConfig $permalink
     * @return array<string, array{string, string}>
     */
    public static function tags(string $postType, PermalinkConfig $permalink): array
    {
        $tags = [];

        foreach ($permalink->taxonomyTags() as $taxonomy) {
            // Not a public query var, so WordPress drops it after matching:
            // the post is found by name or ID, and Init\Redirect corrects
            // the term path.
            $tags['%' . Consts::TERM_TAG_PREFIX . $taxonomy . '%'] = ['(.+?)', Consts::TERM_TAG_PREFIX . $taxonomy . '='];
        }

        if (!str_contains($permalink->structure, '%postname%')) {
            $tags['%' . Consts::POST_ID_TAG_PREFIX . $postType . '%'] = ['([0-9]+)', "post_type={$postType}&p="];
        }

        return $tags;
    }

    /**
     * add_permastruct() arguments for a post type's `rewrite` argument.
     *
     * @param array<string, mixed> $rewrite The registered post type's `rewrite` (core has filled in the defaults).
     * @return array{with_front: bool, ep_mask: int, paged: bool, feed: bool, forcomments: bool, walk_dirs: bool, endpoints: bool}
     */
    public static function permastructArgs(array $rewrite): array
    {
        return [
            'with_front'  => (bool) ($rewrite['with_front'] ?? true),
            'ep_mask'     => (int) ($rewrite['ep_mask'] ?? EP_PERMALINK),
            'paged'       => true,
            // Core's own permastruct follows `feeds`; Custom Post Type
            // Permalinks always added feeds.
            'feed'        => (bool) ($rewrite['feeds'] ?? false),
            'forcomments' => false,
            // Rules for each leading part of the structure (`case/%term%/`
            // alone) would shadow the taxonomy's own archive.
            'walk_dirs'   => false,
            'endpoints'   => true,
        ];
    }

    /**
     * The post type archive path, without slashes at either end — the same
     * one core gives `get_post_type_archive_link()`.
     *
     * @param mixed $hasArchive The post type's `has_archive`.
     * @param array<string, mixed> $rewrite The post type's `rewrite`.
     * @param string $front WP_Rewrite::$front, e.g. `/` or `/blog/`.
     * @param string $root WP_Rewrite::$root, `index.php/` with PATHINFO permalinks.
     * @return ?string Null when there is no archive.
     */
    public static function archiveSlug(mixed $hasArchive, array $rewrite, string $front, string $root): ?string
    {
        if (!$hasArchive) {
            return null;
        }

        $slug = is_string($hasArchive) ? $hasArchive : ($rewrite['slug'] ?? '');

        return Permalink::linkBase(['slug' => $slug] + $rewrite, $front, $root);
    }

    /**
     * The path segment between the archive and the year.
     *
     * An explicit PermalinkConfig::$dateFront wins. Otherwise Consts::DATE_FRONT
     * when every segment of the structure is numeric and there are at most
     * three of them (`/%post_id%/`, `/%year%/%post_id%/`): post URLs would
     * then look exactly like `/2024/`, `/2024/05/` or `/2024/05/12/`, and the
     * date rules, matched first, would hide the posts.
     *
     * @param PermalinkConfig $permalink
     * @return string `''` or a path starting with `/`.
     */
    public static function dateFront(PermalinkConfig $permalink): string
    {
        if ($permalink->dateFront !== null) {
            return $permalink->dateFront;
        }

        $segments = explode('/', trim($permalink->structure, '/'));
        if (count($segments) > 3) {
            return '';
        }

        foreach ($segments as $segment) {
            if (preg_match('/^(%(post_id|year|monthnum|day|hour|minute|second)%|[0-9])+$/', $segment) !== 1) {
                return '';
            }
        }

        return Consts::DATE_FRONT;
    }

    /**
     * Day, month and year archive rules of one post type, most specific first.
     *
     * @param string $base Archive slug plus date front, e.g. `case` or `case/date`.
     * @param string $postType
     * @param string $paginationBase WP_Rewrite::$pagination_base.
     * @param string[] $feeds WP_Rewrite::$feeds, or [] for no feed rules.
     * @return array<string, string> regex => query
     */
    public static function dateRules(string $base, string $postType, string $paginationBase, array $feeds): array
    {
        $levels = [
            ['([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})', ['year', 'monthnum', 'day']],
            ['([0-9]{4})/([0-9]{1,2})', ['year', 'monthnum']],
            ['([0-9]{4})', ['year']],
        ];

        $rules = [];
        foreach ($levels as [$pattern, $vars]) {
            $query = [];
            foreach ($vars as $i => $var) {
                $query[] = $var . '=$matches[' . ($i + 1) . ']';
            }

            $rules += self::archiveLevel(self::quote($base) . '/' . $pattern, $query, count($vars) + 1, $postType, $paginationBase, $feeds);
        }

        return $rules;
    }

    /**
     * Author archive rules of one post type.
     *
     * @param string $base Archive slug plus author base, e.g. `case/author`.
     * @param string $postType
     * @param string $paginationBase WP_Rewrite::$pagination_base.
     * @param string[] $feeds WP_Rewrite::$feeds, or [] for no feed rules.
     * @return array<string, string> regex => query
     */
    public static function authorRules(string $base, string $postType, string $paginationBase, array $feeds): array
    {
        return self::archiveLevel(self::quote($base) . '/([^/]+)', ['author_name=$matches[1]'], 2, $postType, $paginationBase, $feeds);
    }

    /**
     * Feed, pagination and plain rules for one archive pattern.
     *
     * @param string $regex Without the trailing `/?$`.
     * @param string[] $query Query parts for the groups in $regex.
     * @param int $next Number of the first group after $regex.
     * @param string $postType
     * @param string $paginationBase
     * @param string[] $feeds
     * @return array<string, string>
     */
    private static function archiveLevel(string $regex, array $query, int $next, string $postType, string $paginationBase, array $feeds): array
    {
        $query = 'index.php?' . implode('&', $query);
        $postTypeQuery = '&post_type=' . $postType;
        $rules = [];

        if ($feeds !== []) {
            $feed = '(' . implode('|', array_map([self::class, 'quote'], $feeds)) . ')';
            $rules["{$regex}/feed/{$feed}/?$"] = "{$query}&feed=\$matches[{$next}]{$postTypeQuery}";
            $rules["{$regex}/{$feed}/?$"] = "{$query}&feed=\$matches[{$next}]{$postTypeQuery}";
        }

        $rules[$regex . '/' . self::quote($paginationBase) . '/?([0-9]{1,})/?$'] = "{$query}&paged=\$matches[{$next}]{$postTypeQuery}";
        $rules["{$regex}/?$"] = $query . $postTypeQuery;

        return $rules;
    }

    /**
     * Every rule the current configuration needs, as regex => query.
     *
     * Generated in memory the way core does when it saves the rules, for
     * comparing with the stored ones (missingRules()). Writes nothing.
     *
     * @return array<string, string>
     */
    public static function expectedRules(): array
    {
        global $wp_rewrite;

        if (!$wp_rewrite instanceof \WP_Rewrite || !$wp_rewrite->using_permalinks()) {
            return [];
        }

        // Stored rules refer to matches as $matches[1]; generate_rewrite_rules()
        // only writes them that way while this is set, as core does when saving.
        $matches = $wp_rewrite->matches;
        $wp_rewrite->matches = 'matches';

        $rules = Posts::expectedRules();
        foreach (self::managedPostTypes() as [$postType, $permalink]) {
            $rules += self::archiveRules($postType, $permalink, $wp_rewrite);

            $struct = $wp_rewrite->extra_permastructs[$postType->name] ?? null;
            if (!is_array($struct) || !isset($struct['struct'])) {
                continue;
            }

            $generated = $wp_rewrite->generate_rewrite_rules(
                $struct['struct'],
                $struct['ep_mask'],
                $struct['paged'],
                $struct['feed'],
                $struct['forcomments'],
                $struct['walk_dirs'],
                $struct['endpoints']
            );

            // The same filter core applies before saving, so a site that
            // adjusts the rules is not told they are missing.
            $filtered = apply_filters("{$postType->name}_rewrite_rules", $generated);
            $rules += is_array($filtered) ? $filtered : $generated;
        }

        $wp_rewrite->matches = $matches;

        return $rules;
    }

    /**
     * Rules from $expected that the stored rules lack or map to another query.
     *
     * @param array<string, string> $expected
     * @param mixed $stored get_option('rewrite_rules'): an array, or empty before core has generated any.
     * @return string[] The missing regexes.
     */
    public static function missingRules(array $expected, mixed $stored): array
    {
        if (!is_array($stored)) {
            return array_map('strval', array_keys($expected));
        }

        $missing = [];
        foreach ($expected as $regex => $query) {
            if (!array_key_exists($regex, $stored) || $stored[$regex] !== $query) {
                $missing[] = (string) $regex;
            }
        }

        return $missing;
    }

    /**
     * Escape regex metacharacters in a literal path. `-` and `/` stay as
     * they are so the rules remain readable.
     *
     * @param string $literal
     * @return string
     */
    public static function quote(string $literal): string
    {
        return (string) preg_replace('/[.\\\\+*?\[\]^$(){}=!<>|:#]/', '\\\\$0', $literal);
    }
}
