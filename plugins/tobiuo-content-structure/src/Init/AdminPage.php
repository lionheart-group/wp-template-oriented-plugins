<?php

namespace TobiuoPlugin\Init;

use TobiuoPlugin\Consts;
use TobiuoPlugin\Helpers\Registry;
use TobiuoPlugin\Structure\PostTypeConfig;
use TobiuoPlugin\Structure\TaxonomyConfig;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Read-only view of the content structure the theme registered.
 *
 * There is nothing to save: configuration lives in theme code. The page
 * exists so that someone without access to the code can see which post
 * types and taxonomies exist, what their URLs look like, and whether the
 * stored rewrite rules are up to date.
 */
class AdminPage
{
    /**
     * Badge tones: a supplementary note, or something worth a second look.
     */
    public const TONE_INFO = 'info';
    public const TONE_WARNING = 'warning';

    /**
     * Tags badgeHtml() produces, for wp_kses() at output time.
     */
    public const ALLOWED_HTML = [
        'span' => ['class' => true],
    ];

    /**
     * Register the admin_menu action. Call once from the plugin bootstrap.
     */
    public static function register(): void
    {
        add_action('admin_menu', [static::class, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [static::class, 'enqueueStyles']);
    }

    /**
     * Resolve the capability required to view the page.
     *
     * Used both to register the menu and to guard the page itself — filtering only
     * one of the two would show a menu entry that dies on click, or hide a page that
     * is still reachable by URL.
     */
    public static function capability(): string
    {
        /**
         * Filters the capability required to view the TOBIUO admin page.
         *
         * @param string $capability Defaults to 'manage_options'.
         */
        $capability = apply_filters('tobiuo_admin_page_capability', Consts::DEFAULT_CAPABILITY);

        return is_string($capability) && $capability !== '' ? $capability : Consts::DEFAULT_CAPABILITY;
    }

    /**
     * Register the page under Tools.
     */
    public static function addMenuPage(): void
    {
        add_management_page(
            page_title: __('Content Structure (TOBIUO)', 'tobiuo-content-structure'),
            menu_title: __('Content Structure (TOBIUO)', 'tobiuo-content-structure'),
            capability: self::capability(),
            menu_slug:  Consts::ADMIN_PAGE_SLUG,
            callback:   [static::class, 'renderPage'],
        );
    }

    /**
     * The screen ID of the page.
     *
     * @return string
     */
    public static function screenId(): string
    {
        return 'tools_page_' . Consts::ADMIN_PAGE_SLUG;
    }

    /**
     * Enqueue the page's styles on the page, and nowhere else.
     */
    public static function enqueueStyles(): void
    {
        $screen = get_current_screen();
        if ($screen === null || $screen->id !== self::screenId()) {
            return;
        }

        wp_enqueue_style(
            'tobiuo-content-structure-admin',
            plugins_url('assets/css/admin.css', TOBIUO_PLUGIN_FILE),
            [],
            TOBIUO_VERSION
        );
    }

    /**
     * Render the page.
     */
    public static function renderPage(): void
    {
        global $wp_rewrite;

        if (!current_user_can(self::capability())) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'tobiuo-content-structure'));
        }

        $conflict = Conflict::detect();
        $usingPermalinks = $wp_rewrite instanceof \WP_Rewrite && $wp_rewrite->using_permalinks();
        $missing = $conflict === null && $usingPermalinks
            ? Rewrite::missingRules(Rewrite::expectedRules(), get_option('rewrite_rules'))
            : [];
        ?>
        <div class="wrap tobiuo-page">
            <h1><?php echo esc_html__('Content Structure (TOBIUO)', 'tobiuo-content-structure'); ?></h1>

            <p><?php echo esc_html__('This screen is read-only. TOBIUO is configured in the theme\'s PHP code, and nothing is stored in the database.', 'tobiuo-content-structure'); ?></p>

            <h2><?php echo esc_html__('Permalinks', 'tobiuo-content-structure'); ?></h2>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Custom permalinks', 'tobiuo-content-structure'); ?></th>
                        <td>
                            <?php if ($conflict !== null) : ?>
                                <?php
                                echo wp_kses(self::badgeHtml(self::TONE_WARNING, sprintf(
                                    /* translators: %s: name of the other permalink plugin */
                                    __('Handled by %s', 'tobiuo-content-structure'),
                                    $conflict
                                )), self::ALLOWED_HTML);
                                ?>
                            <?php elseif (!$usingPermalinks) : ?>
                                <?php echo wp_kses(self::badgeHtml(self::TONE_WARNING, __('Plain permalinks are in use', 'tobiuo-content-structure')), self::ALLOWED_HTML); ?>
                                <p class="description"><?php echo esc_html__('Post types keep their plain links until a permalink structure is chosen in Settings → Permalinks.', 'tobiuo-content-structure'); ?></p>
                            <?php else : ?>
                                <?php echo esc_html__('Built by TOBIUO', 'tobiuo-content-structure'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($conflict === null && $usingPermalinks) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Rewrite rules', 'tobiuo-content-structure'); ?></th>
                            <td>
                                <?php if ($missing === []) : ?>
                                    <?php echo esc_html__('Up to date', 'tobiuo-content-structure'); ?>
                                <?php else : ?>
                                    <?php
                                    echo wp_kses(self::badgeHtml(self::TONE_WARNING, sprintf(
                                        /* translators: %d: number of missing rewrite rules */
                                        _n('%d rule missing', '%d rules missing', count($missing), 'tobiuo-content-structure'),
                                        count($missing)
                                    )), self::ALLOWED_HTML);
                                    ?>
                                    <p class="description">
                                        <?php echo esc_html__('The stored rewrite rules do not match the current configuration, so some URLs return 404. Opening Settings → Permalinks regenerates them; there is no need to save.', 'tobiuo-content-structure'); ?>
                                        <a href="<?php echo esc_url(admin_url('options-permalink.php')); ?>"><?php echo esc_html__('Open Settings → Permalinks', 'tobiuo-content-structure'); ?></a>
                                    </p>
                                    <ul class="tobiuo-rules">
                                        <?php foreach ($missing as $regex) : ?>
                                            <li><code><?php echo esc_html($regex); ?></code></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php self::renderPostsSection($conflict === null); ?>

            <h2><?php echo esc_html__('Post types', 'tobiuo-content-structure'); ?></h2>

            <table class="wp-list-table widefat fixed striped tobiuo-table">
                <thead>
                    <tr>
                        <th scope="col" class="tobiuo-table__name"><?php echo esc_html__('Post type', 'tobiuo-content-structure'); ?></th>
                        <th scope="col"><?php echo esc_html__('Permalink structure', 'tobiuo-content-structure'); ?></th>
                        <th scope="col"><?php echo esc_html__('Latest post', 'tobiuo-content-structure'); ?></th>
                        <th scope="col"><?php echo esc_html__('Archive', 'tobiuo-content-structure'); ?></th>
                        <th scope="col"><?php echo esc_html__('Date archives', 'tobiuo-content-structure'); ?></th>
                        <th scope="col"><?php echo esc_html__('Author archives', 'tobiuo-content-structure'); ?></th>
                        <th scope="col"><?php echo esc_html__('Taxonomies', 'tobiuo-content-structure'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (Registry::getPostTypes() === []) : ?>
                        <tr>
                            <td colspan="7"><?php echo esc_html__('No post types are registered.', 'tobiuo-content-structure'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach (Registry::getPostTypes() as $config) : ?>
                            <?php self::renderPostTypeRow($config); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2><?php echo esc_html__('Taxonomies', 'tobiuo-content-structure'); ?></h2>

            <table class="wp-list-table widefat fixed striped tobiuo-table">
                <thead>
                    <tr>
                        <th scope="col" class="tobiuo-table__name"><?php echo esc_html__('Taxonomy', 'tobiuo-content-structure'); ?></th>
                        <th scope="col"><?php echo esc_html__('Post types', 'tobiuo-content-structure'); ?></th>
                        <th scope="col"><?php echo esc_html__('Rewrite slug', 'tobiuo-content-structure'); ?></th>
                        <th scope="col"><?php echo esc_html__('Example term', 'tobiuo-content-structure'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (Registry::getTaxonomies() === []) : ?>
                        <tr>
                            <td colspan="4"><?php echo esc_html__('No taxonomies are registered.', 'tobiuo-content-structure'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach (Registry::getTaxonomies() as $config) : ?>
                            <?php self::renderTaxonomyRow($config); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Core's `post`: its archive and permalink structure, and where they come from.
     *
     * @param bool $handled Whether TOBIUO applies the PostsConfig (no conflicting plugin).
     */
    protected static function renderPostsSection(bool $handled): void
    {
        $config = $handled ? Registry::getPosts() : null;
        $archive = get_post_type_archive_link('post');
        $structure = (string) get_option('permalink_structure');
        $fromTheme = $config !== null && $config->permalinkStructure() !== null;
        ?>
        <h2><?php echo esc_html__('Posts', 'tobiuo-content-structure'); ?></h2>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><?php echo esc_html__('Archive', 'tobiuo-content-structure'); ?></th>
                    <td class="tobiuo-table__url">
                        <?php if ($config === null || $config->archive === null) : ?>
                            <?php echo wp_kses(self::badgeHtml(self::TONE_INFO, __('Core default', 'tobiuo-content-structure')), self::ALLOWED_HTML); ?>
                        <?php endif; ?>
                        <?php if (is_string($archive) && $archive !== '') : ?>
                            <?php self::renderLink($archive); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Permalink structure', 'tobiuo-content-structure'); ?></th>
                    <td>
                        <?php if ($structure === '') : ?>
                            <?php echo wp_kses(self::badgeHtml(self::TONE_WARNING, __('Plain permalinks are in use', 'tobiuo-content-structure')), self::ALLOWED_HTML); ?>
                        <?php else : ?>
                            <code><?php echo esc_html($structure); ?></code>
                        <?php endif; ?>
                        <br>
                        <?php
                        echo wp_kses(self::badgeHtml(self::TONE_INFO, $fromTheme
                            ? __('From the theme (TOBIUO)', 'tobiuo-content-structure')
                            : __('From Settings → Permalinks', 'tobiuo-content-structure')), self::ALLOWED_HTML);
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * @param PostTypeConfig $config
     */
    protected static function renderPostTypeRow(PostTypeConfig $config): void
    {
        $object = get_post_type_object($config->name);
        if (!$object instanceof \WP_Post_Type) {
            return;
        }

        $latest = get_posts([
            'post_type'        => $config->name,
            'post_status'      => 'publish',
            'numberposts'      => 1,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'suppress_filters' => false,
        ]);
        $latest = $latest[0] ?? null;
        $latest = $latest instanceof \WP_Post ? $latest : null;

        $archive = get_post_type_archive_link($config->name);
        $permalink = $config->permalink;

        // Example archive links from the latest post, or from today
        $year = (int) ($latest !== null ? get_the_date('Y', $latest) : current_time('Y'));
        $month = (int) ($latest !== null ? get_the_date('n', $latest) : current_time('n'));
        $author = $latest !== null ? (int) $latest->post_author : get_current_user_id();
        ?>
        <tr>
            <td>
                <code><?php echo esc_html($config->name); ?></code><br>
                <?php echo esc_html((string) $object->label); ?>
            </td>
            <td>
                <?php if ($permalink === null) : ?>
                    <?php echo wp_kses(self::badgeHtml(self::TONE_INFO, __('Core default', 'tobiuo-content-structure')), self::ALLOWED_HTML); ?>
                <?php else : ?>
                    <code><?php echo esc_html(self::structureForDisplay($object)); ?></code>
                <?php endif; ?>
            </td>
            <td class="tobiuo-table__url">
                <?php if ($latest === null) : ?>
                    —
                <?php else : ?>
                    <?php self::renderLink((string) get_permalink($latest)); ?>
                <?php endif; ?>
            </td>
            <td class="tobiuo-table__url">
                <?php if (!is_string($archive) || $archive === '') : ?>
                    —
                <?php else : ?>
                    <?php self::renderLink($archive); ?>
                <?php endif; ?>
            </td>
            <td class="tobiuo-table__url">
                <?php self::renderArchiveCell($permalink !== null && $permalink->dateArchive, [
                    ArchiveLinks::dateLink($config->name, $year),
                    ArchiveLinks::dateLink($config->name, $year, $month),
                ]); ?>
            </td>
            <td class="tobiuo-table__url">
                <?php self::renderArchiveCell($permalink !== null && $permalink->authorArchive, [
                    ArchiveLinks::authorLink($config->name, $author),
                ]); ?>
            </td>
            <td><?php echo esc_html(self::displayList(get_object_taxonomies($config->name))); ?></td>
        </tr>
        <?php
    }

    /**
     * @param TaxonomyConfig $config
     */
    protected static function renderTaxonomyRow(TaxonomyConfig $config): void
    {
        $object = get_taxonomy($config->name);
        if (!$object instanceof \WP_Taxonomy) {
            return;
        }

        $terms = get_terms([
            'taxonomy'   => $config->name,
            'number'     => 1,
            'hide_empty' => false,
        ]);
        $term = is_array($terms) && ($terms[0] ?? null) instanceof \WP_Term ? $terms[0] : null;
        $link = $term !== null ? get_term_link($term) : '';
        ?>
        <tr>
            <td>
                <code><?php echo esc_html($config->name); ?></code><br>
                <?php echo esc_html((string) $object->label); ?>
            </td>
            <td><?php echo esc_html(self::displayList(array_map('strval', (array) $object->object_type))); ?></td>
            <td>
                <?php if (!is_array($object->rewrite)) : ?>
                    <?php echo wp_kses(self::badgeHtml(self::TONE_INFO, __('No rewrite rules', 'tobiuo-content-structure')), self::ALLOWED_HTML); ?>
                <?php else : ?>
                    <code><?php echo esc_html((string) ($object->rewrite['slug'] ?? '')); ?></code>
                    <?php if (!empty($object->rewrite['hierarchical'])) : ?>
                        <br><?php echo wp_kses(self::badgeHtml(self::TONE_INFO, __('Hierarchical URLs', 'tobiuo-content-structure')), self::ALLOWED_HTML); ?>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
            <td class="tobiuo-table__url">
                <?php if (!is_string($link) || $link === '') : ?>
                    —
                <?php else : ?>
                    <?php self::renderLink($link); ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Enabled + example links, or "Disabled".
     *
     * @param bool $enabled
     * @param string[] $links
     */
    protected static function renderArchiveCell(bool $enabled, array $links): void
    {
        if (!$enabled) {
            echo esc_html__('Disabled', 'tobiuo-content-structure');
            return;
        }

        echo esc_html__('Enabled', 'tobiuo-content-structure');

        foreach (array_filter($links) as $link) {
            echo '<br>';
            self::renderLink($link);
        }
    }

    /**
     * @param string $url
     */
    protected static function renderLink(string $url): void
    {
        printf('<a href="%s">%s</a>', esc_url($url), esc_html(urldecode($url)));
    }

    /**
     * The structure as it appears after the home URL, e.g. `/case/%case_category%/%postname%/`.
     *
     * @param \WP_Post_Type $object
     * @return string
     */
    public static function structureForDisplay(\WP_Post_Type $object): string
    {
        global $wp_rewrite;

        $permalink = Registry::getPermalink($object->name);
        if ($permalink === null) {
            return '';
        }

        $base = is_array($object->rewrite) && $wp_rewrite instanceof \WP_Rewrite
            ? Permalink::linkBase($object->rewrite, $wp_rewrite->front, $wp_rewrite->root)
            : $object->name;

        return '/' . ltrim($base . $permalink->structure, '/');
    }

    /**
     * @param string $tone One of the TONE_* constants.
     * @param string $label
     * @return string
     */
    public static function badgeHtml(string $tone, string $label): string
    {
        $tone = $tone === self::TONE_WARNING ? self::TONE_WARNING : self::TONE_INFO;

        return sprintf('<span class="tobiuo-badge tobiuo-badge--%s">%s</span>', esc_attr($tone), esc_html($label));
    }

    /**
     * @param string[] $values
     * @return string
     */
    protected static function displayList(array $values): string
    {
        return $values !== [] ? implode(', ', $values) : '—';
    }
}
