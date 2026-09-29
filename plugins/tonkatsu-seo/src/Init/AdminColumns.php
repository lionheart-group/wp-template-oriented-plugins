<?php

namespace ToroPlugin\Init;

use ToroPlugin\Consts;
use ToroPlugin\Helpers\Visibility;
use ToroPlugin\Models\Context;
use ToroPlugin\Models\Resolver;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * SEO columns on the post list screens (Pages by default).
 *
 * Shows, for every post, the title, description and robots it outputs —
 * not only for the paths the theme registered — so a missing description
 * or an unexpected noindex is visible without opening each page.
 */
class AdminColumns
{
    public const COLUMN_TITLE = 'toro_title';
    public const COLUMN_DESCRIPTION = 'toro_description';
    public const COLUMN_ROBOTS = 'toro_robots';

    /**
     * Badge tones: a supplementary note, or something worth a second look.
     */
    public const TONE_INFO = 'info';
    public const TONE_WARNING = 'warning';

    /**
     * Tags cellHtml() and badgeHtml() produce, for wp_kses() at output time.
     */
    public const ALLOWED_HTML = [
        'br'   => [],
        'span' => ['class' => true],
    ];

    /**
     * Post types that get the columns.
     */
    private const DEFAULT_POST_TYPES = ['page'];

    /**
     * Register the admin_init action. Call once from the plugin bootstrap.
     */
    public static function register(): void
    {
        add_action('admin_init', [static::class, 'addHooks']);
        add_action('admin_head', [static::class, 'printStyles']);
    }

    /**
     * Hook the columns for each post type the user may see them on.
     */
    public static function addHooks(): void
    {
        if (!current_user_can(AdminPage::capability())) {
            return;
        }

        foreach (self::postTypes() as $postType) {
            add_filter("manage_{$postType}_posts_columns", [static::class, 'addColumns']);
            add_action("manage_{$postType}_posts_custom_column", [static::class, 'renderColumn'], 10, 2);
        }
    }

    /**
     * Post types that get the columns.
     *
     * @return string[]
     */
    public static function postTypes(): array
    {
        /**
         * Filters the post types whose list screen shows the SEO columns.
         *
         * @param string[] $postTypes Defaults to ['page'].
         */
        $postTypes = apply_filters('toro_admin_column_post_types', self::DEFAULT_POST_TYPES);

        if (!is_array($postTypes)) {
            return self::DEFAULT_POST_TYPES;
        }

        return array_values(array_filter($postTypes, fn ($postType) => is_string($postType) && $postType !== ''));
    }

    /**
     * Insert the columns after the title column (or at the end when there is none).
     *
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public static function addColumns(array $columns): array
    {
        $ours = [
            self::COLUMN_TITLE       => __('SEO title', 'template-oriented-rank-optimizer'),
            self::COLUMN_DESCRIPTION => __('Description', 'template-oriented-rank-optimizer'),
            self::COLUMN_ROBOTS      => __('Robots', 'template-oriented-rank-optimizer'),
        ];

        $position = array_search('title', array_keys($columns), true);
        if ($position === false) {
            return $columns + $ours;
        }

        return array_slice($columns, 0, $position + 1, true) + $ours + array_slice($columns, $position + 1, null, true);
    }

    /**
     * Print one cell.
     *
     * @param string $column
     * @param int    $postId
     */
    public static function renderColumn(string $column, int $postId): void
    {
        if (!in_array($column, [self::COLUMN_TITLE, self::COLUMN_DESCRIPTION, self::COLUMN_ROBOTS], true)) {
            return;
        }

        $cells = self::cellsForPost($postId);
        if ($cells === null) {
            return;
        }

        echo wp_kses(self::cellHtml($cells[$column]), self::ALLOWED_HTML);
    }

    /**
     * Resolve the cells once per post; the three columns share them.
     *
     * @param int $postId
     * @return ?array<string, array{value: string, badge: ?array{tone: string, label: string}}>
     */
    private static function cellsForPost(int $postId): ?array
    {
        static $cache = [];

        if (!array_key_exists($postId, $cache)) {
            $post = get_post($postId);
            $cache[$postId] = null;

            if ($post instanceof \WP_Post) {
                $context = Context::forPost($post);
                $cache[$postId] = self::cells(
                    Resolver::fromContext($context),
                    $context,
                    Visibility::searchEnginesDiscouraged()
                );
            }
        }

        return $cache[$postId];
    }

    /**
     * The values to show, with a badge saying where a fallback came from.
     *
     * Shared with the TORO admin page so both screens say the same thing.
     *
     * @param Resolver $resolver
     * @param Context  $context
     * @param bool     $searchEnginesDiscouraged
     * @return array<string, array{value: string, badge: ?array{tone: string, label: string}}>
     */
    public static function cells(Resolver $resolver, Context $context, bool $searchEnginesDiscouraged): array
    {
        // The finished <title>, noting when no SEO title is configured
        $title = Head::documentTitle($resolver);
        $titleBadge = null;
        if ($resolver->title() === null) {
            $titleBadge = self::badge(self::TONE_INFO, $context->isFront()
                ? __('From the site name', 'template-oriented-rank-optimizer')
                : __('From the page title', 'template-oriented-rank-optimizer'));
        }

        // The finished meta description, noting a site-wide default or no output
        $description = $resolver->description();
        $descriptionBadge = null;
        if ($description === null || trim($description) === '') {
            $description = '—';
            $descriptionBadge = self::badge(self::TONE_WARNING, __('Not output', 'template-oriented-rank-optimizer'));
        } elseif ($description === $resolver->site->defaultDescription) {
            $descriptionBadge = self::badge(self::TONE_INFO, __('Site default', 'template-oriented-rank-optimizer'));
        }

        if ($searchEnginesDiscouraged) {
            $robots = AdminPage::robots(true, true);
            $robotsBadge = self::searchEnginesDiscouragedBadge();
        } else {
            $robots = AdminPage::robots($resolver->noindex(), $resolver->nofollow());
            $robotsBadge = null;
        }

        return [
            self::COLUMN_TITLE       => ['value' => $title, 'badge' => $titleBadge],
            self::COLUMN_DESCRIPTION => ['value' => $description, 'badge' => $descriptionBadge],
            self::COLUMN_ROBOTS      => ['value' => $robots, 'badge' => $robotsBadge],
        ];
    }

    /**
     * @param string $tone  One of the TONE_* constants.
     * @param string $label
     * @return array{tone: string, label: string}
     */
    public static function badge(string $tone, string $label): array
    {
        return ['tone' => $tone, 'label' => $label];
    }

    /**
     * The badge for "Discourage search engines", shared with the TORO page.
     *
     * @return array{tone: string, label: string}
     */
    public static function searchEnginesDiscouragedBadge(): array
    {
        return self::badge(self::TONE_WARNING, __('Search engines discouraged', 'template-oriented-rank-optimizer'));
    }

    /**
     * @param array{value: string, badge: ?array{tone: string, label: string}} $cell
     * @return string
     */
    public static function cellHtml(array $cell): string
    {
        $html = esc_html($cell['value']);

        if ($cell['badge'] !== null) {
            $html .= '<br>' . self::badgeHtml($cell['badge']);
        }

        return $html;
    }

    /**
     * @param array{tone: string, label: string} $badge
     * @return string
     */
    public static function badgeHtml(array $badge): string
    {
        $tone = $badge['tone'] === self::TONE_WARNING ? self::TONE_WARNING : self::TONE_INFO;

        return sprintf('<span class="toro-badge toro-badge--%s">%s</span>', esc_attr($tone), esc_html($badge['label']));
    }

    /**
     * The TORO page, or a list screen with SEO columns.
     *
     * @param string $screenId
     * @param string $screenBase
     * @param string $postType
     * @return bool
     */
    public static function isSeoScreen(string $screenId, string $screenBase, string $postType): bool
    {
        if ($screenId === 'tools_page_' . Consts::ADMIN_PAGE_SLUG) {
            return true;
        }

        return $screenBase === 'edit' && in_array($postType, self::postTypes(), true);
    }

    /**
     * Badge styles, on the screens that show badges only.
     */
    public static function printStyles(): void
    {
        $screen = get_current_screen();
        if ($screen === null) {
            return;
        }

        if (!self::isSeoScreen($screen->id, $screen->base, $screen->post_type)) {
            return;
        }
        ?>
        <style>
            .toro-badge {
                display: inline-block;
                margin-top: 4px;
                padding: 0 8px;
                border: 1px solid;
                border-radius: 10px;
                font-size: 11px;
                line-height: 18px;
                white-space: nowrap;
            }
            .toro-badge--info {
                border-color: #c3c4c7;
                background: #f6f7f7;
                color: #50575e;
            }
            .toro-badge--warning {
                border-color: #dba617;
                background: #fcf9e8;
                color: #6b4e00;
            }
        </style>
        <?php
    }
}
