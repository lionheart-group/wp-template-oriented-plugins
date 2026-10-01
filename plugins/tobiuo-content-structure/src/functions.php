<?php

/**
 * Template functions.
 *
 * Global so templates can call them like core's get_year_link(), and so a
 * theme can guard them with function_exists() while the plugin is inactive.
 */

use TobiuoPlugin\Init\ArchiveLinks;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * URL of a post type's year archive, e.g. `https://example.com/case/2024/`.
 *
 * @param string $post_type
 * @param int $year
 * @return string `''` unless the post type has a PermalinkConfig with `dateArchive`.
 *                For `post`, core's own archive link.
 */
function tobiuo_get_year_link(string $post_type, int $year): string
{
    return ArchiveLinks::dateLink($post_type, $year);
}

/**
 * URL of a post type's month archive, e.g. `https://example.com/case/2024/05/`.
 *
 * @param string $post_type
 * @param int $year
 * @param int $month
 * @return string `''` unless the post type has a PermalinkConfig with `dateArchive`.
 *                For `post`, core's own archive link.
 */
function tobiuo_get_month_link(string $post_type, int $year, int $month): string
{
    return ArchiveLinks::dateLink($post_type, $year, $month);
}

/**
 * URL of a post type's day archive, e.g. `https://example.com/case/2024/05/12/`.
 *
 * @param string $post_type
 * @param int $year
 * @param int $month
 * @param int $day
 * @return string `''` unless the post type has a PermalinkConfig with `dateArchive`.
 *                For `post`, core's own archive link.
 */
function tobiuo_get_day_link(string $post_type, int $year, int $month, int $day): string
{
    return ArchiveLinks::dateLink($post_type, $year, $month, $day);
}

/**
 * URL of a post type's archive for one author, e.g. `https://example.com/case/author/jane/`.
 *
 * @param string $post_type
 * @param WP_User|int $user A user or a user ID.
 * @return string `''` unless the post type has a PermalinkConfig with `authorArchive`.
 *                For `post`, core's own archive link.
 */
function tobiuo_get_author_link(string $post_type, WP_User|int $user): string
{
    return ArchiveLinks::authorLink($post_type, $user);
}
