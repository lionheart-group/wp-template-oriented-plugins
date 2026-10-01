<?php

namespace TobiuoPlugin\Init;

use TobiuoPlugin\Helpers\Registry;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Links to the date and author archives Init\Rewrite adds.
 *
 * Backs the global tobiuo_get_*_link() functions, and gives
 * `wp_get_archives(['post_type' => …])` the same URLs instead of core's
 * `/2024/05/?post_type=case`.
 */
class ArchiveLinks
{
    /**
     * Whether TOBIUO handles the permalinks (no conflicting plugin).
     *
     * @var bool
     */
    protected static bool $enabled = false;

    /**
     * Register the get_archives_link filter. Call once from the plugin bootstrap.
     */
    public static function register(): void
    {
        self::$enabled = true;
        add_filter('get_archives_link', [static::class, 'filterArchivesLink'], 10, 2);
    }

    /**
     * Year, month or day archive link of a post type.
     *
     * @param string $postType
     * @param int $year
     * @param ?int $month
     * @param ?int $day Needs $month.
     * @return string `''` when the post type has no date archive, or the date is out of range.
     */
    public static function dateLink(string $postType, int $year, ?int $month = null, ?int $day = null): string
    {
        if ($year < 1000 || $year > 9999 || ($month !== null && ($month < 1 || $month > 12)) || ($day !== null && ($month === null || $day < 1 || $day > 31))) {
            return '';
        }

        $context = self::context($postType, 'date');
        if ($context === null) {
            return '';
        }

        [$base, $wpRewrite, $permalink] = $context;

        if (!$wpRewrite->using_permalinks()) {
            return home_url(add_query_arg(array_filter([
                'post_type' => $postType,
                'year'      => $year,
                'monthnum'  => $month,
                'day'       => $day,
            ], fn ($value) => $value !== null), '/'));
        }

        $path = $base . Rewrite::dateFront($permalink) . '/' . sprintf('%04d', $year);
        $type = 'year';

        if ($month !== null) {
            $path .= '/' . sprintf('%02d', $month);
            $type = 'month';
        }

        if ($day !== null) {
            $path .= '/' . sprintf('%02d', $day);
            $type = 'day';
        }

        return home_url(user_trailingslashit($path, $type));
    }

    /**
     * Author archive link of a post type.
     *
     * @param string $postType
     * @param \WP_User|int $user
     * @return string `''` when the post type has no author archive, or there is no such user.
     */
    public static function authorLink(string $postType, \WP_User|int $user): string
    {
        if (is_int($user)) {
            $user = get_userdata($user);
        }

        if (!$user instanceof \WP_User || (string) $user->user_nicename === '') {
            return '';
        }

        $context = self::context($postType, 'author');
        if ($context === null) {
            return '';
        }

        [$base, $wpRewrite] = $context;

        if (!$wpRewrite->using_permalinks()) {
            return home_url(add_query_arg(['post_type' => $postType, 'author' => $user->ID], '/'));
        }

        return home_url(user_trailingslashit($base . '/' . $wpRewrite->author_base . '/' . $user->user_nicename));
    }

    /**
     * The archive slug, WP_Rewrite and PermalinkConfig when the archive is on.
     *
     * @param string $postType
     * @param string $kind 'date' or 'author'.
     * @return ?array{string, \WP_Rewrite, \TobiuoPlugin\Structure\PermalinkConfig}
     */
    private static function context(string $postType, string $kind): ?array
    {
        global $wp_rewrite;

        $permalink = Registry::getPermalink($postType);
        $object = get_post_type_object($postType);

        if (!self::$enabled || $permalink === null || !$object instanceof \WP_Post_Type || !is_array($object->rewrite) || !$wp_rewrite instanceof \WP_Rewrite) {
            return null;
        }

        if (!($kind === 'date' ? $permalink->dateArchive : $permalink->authorArchive)) {
            return null;
        }

        $base = Rewrite::archiveSlug($object->has_archive, $object->rewrite, $wp_rewrite->front, $wp_rewrite->root);

        return $base !== null ? [$base, $wp_rewrite, $permalink] : null;
    }

    /**
     * `get_archives_link` callback: give wp_get_archives() the post type's
     * own date archive URLs.
     *
     * Only the URL in the `href` / `value` attribute is replaced; the rest of
     * the markup is core's.
     *
     * @param mixed $html
     * @param mixed $url Already passed through esc_url() by core.
     * @return mixed
     */
    public static function filterArchivesLink(mixed $html, mixed $url = ''): mixed
    {
        global $wp_rewrite;

        if (!is_string($html) || !is_string($url) || $url === '' || !$wp_rewrite instanceof \WP_Rewrite || !$wp_rewrite->using_permalinks()) {
            return $html;
        }

        $date = self::parseDateUrl(
            $url,
            home_url('/'),
            [
                'day'   => (string) $wp_rewrite->get_day_permastruct(),
                'month' => (string) $wp_rewrite->get_month_permastruct(),
                'year'  => (string) $wp_rewrite->get_year_permastruct(),
            ]
        );

        if ($date === null) {
            return $html;
        }

        $link = self::dateLink($date['post_type'], $date['year'], $date['month'], $date['day']);
        if ($link === '') {
            return $html;
        }

        if ($date['query'] !== []) {
            $link = add_query_arg(urlencode_deep($date['query']), $link);
        }

        $escaped = esc_url($link);

        return str_replace(["'{$url}'", "\"{$url}\""], ["'{$escaped}'", "\"{$escaped}\""], $html);
    }

    /**
     * Read a post type date archive URL as wp_get_archives() builds it:
     * core's date archive link plus `?post_type=…`.
     *
     * @param string $url As in the markup (`&` may be `&#038;`).
     * @param string $homeUrl home_url('/').
     * @param array<string, string> $permastructs 'day' / 'month' / 'year' => WP_Rewrite's date permastruct.
     * @return ?array{post_type: string, year: int, month: ?int, day: ?int, query: array<string, mixed>}
     *         `query` holds any other query arguments. Null when the URL is not one.
     */
    public static function parseDateUrl(string $url, string $homeUrl, array $permastructs): ?array
    {
        $url = str_replace(['&#038;', '&amp;'], '&', $url);
        $parts = wp_parse_url($url);

        if (!is_array($parts) || !isset($parts['query'])) {
            return null;
        }

        parse_str($parts['query'], $query);
        $postType = $query['post_type'] ?? null;
        if (!is_string($postType) || $postType === '') {
            return null;
        }
        unset($query['post_type']);

        $homePath = trim((string) wp_parse_url($homeUrl, PHP_URL_PATH), '/');
        $path = trim($parts['path'] ?? '', '/');

        if ($homePath !== '') {
            if ($path !== $homePath && !str_starts_with($path, $homePath . '/')) {
                return null;
            }
            $path = ltrim(substr($path, strlen($homePath)), '/');
        }

        foreach (['day', 'month', 'year'] as $type) {
            $struct = trim($permastructs[$type] ?? '', '/');
            if ($struct === '') {
                continue;
            }

            $regex = strtr(Rewrite::quote($struct), [
                '%year%'     => '(?<year>[0-9]{4})',
                '%monthnum%' => '(?<month>[0-9]{1,2})',
                '%day%'      => '(?<day>[0-9]{1,2})',
            ]);

            if (preg_match('#^' . $regex . '$#', $path, $m) === 1 && isset($m['year'])) {
                return [
                    'post_type' => $postType,
                    'year'      => (int) $m['year'],
                    'month'     => isset($m['month']) ? (int) $m['month'] : null,
                    'day'       => isset($m['day']) ? (int) $m['day'] : null,
                    'query'     => array_filter($query, fn ($key) => is_string($key), ARRAY_FILTER_USE_KEY),
                ];
            }
        }

        return null;
    }
}
