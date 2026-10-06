<?php

namespace TonkatsuPlugin\Init;

use TonkatsuPlugin\Consts;
use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Helpers\Visibility;
use TonkatsuPlugin\Models\Context;
use TonkatsuPlugin\Models\RedirectLog;
use TonkatsuPlugin\Models\Resolver;
use TonkatsuPlugin\Structure\ArchiveConfig;
use TonkatsuPlugin\Structure\RedirectConfig;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Read-only view of the configuration the theme registered.
 *
 * There is nothing to save: configuration lives in theme code. The page
 * exists so that someone without access to the code can check what a page
 * will output.
 */
class AdminPage
{
    /**
     * `view` of the redirect log (tools.php?page=tonkatsu-seo&view=redirect-log).
     */
    public const VIEW_REDIRECT_LOG = 'redirect-log';

    /**
     * Redirect log rows per page.
     */
    public const LOG_PER_PAGE = 25;

    /**
     * Register the admin_menu action. Call once from the plugin bootstrap.
     */
    public static function register(): void
    {
        add_action('admin_menu', [static::class, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [AdminColumns::class, 'enqueueStyles']);
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
         * Filters the capability required to view the TONKATSU admin page.
         *
         * @param string $capability Defaults to 'manage_options'.
         */
        $capability = apply_filters('tonkatsu_admin_page_capability', Consts::DEFAULT_CAPABILITY);

        return is_string($capability) && $capability !== '' ? $capability : Consts::DEFAULT_CAPABILITY;
    }

    /**
     * Register the page under Tools.
     */
    public static function addMenuPage(): void
    {
        add_management_page(
            page_title: __('SEO Settings (TONKATSU)', 'tonkatsu-seo'),
            menu_title: __('SEO (TONKATSU)', 'tonkatsu-seo'),
            capability: self::capability(),
            menu_slug:  Consts::ADMIN_PAGE_SLUG,
            callback:   [static::class, 'renderPage'],
        );
    }

    /**
     * Render the page.
     */
    public static function renderPage(): void
    {
        if (!current_user_can(self::capability())) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'tonkatsu-seo'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selection, no state change
        $view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
        if ($view === self::VIEW_REDIRECT_LOG) {
            self::renderRedirectLogPage();
            return;
        }

        $site = Seo::getSite();
        $front = Resolver::fromContext(new Context(type: Context::TYPE_FRONT, url: home_url('/')));
        $searchEnginesDiscouraged = Visibility::searchEnginesDiscouraged();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('SEO Settings (TONKATSU)', 'tonkatsu-seo'); ?></h1>

            <?php if ($site->logRedirects) : ?>
                <p><?php echo esc_html__('This screen is read-only. TONKATSU is configured in the theme\'s PHP code, and no settings are stored in the database; only the redirect log is.', 'tonkatsu-seo'); ?></p>
            <?php else : ?>
                <p><?php echo esc_html__('This screen is read-only. TONKATSU is configured in the theme\'s PHP code, and nothing is stored in the database.', 'tonkatsu-seo'); ?></p>
            <?php endif; ?>

            <h2><?php echo esc_html__('Site', 'tonkatsu-seo'); ?></h2>

            <?php if (!Seo::hasSite()) : ?>
                <p><?php echo esc_html__('The theme has not registered a SiteConfig, so the defaults below are in use.', 'tonkatsu-seo'); ?></p>
            <?php endif; ?>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Site name', 'tonkatsu-seo'); ?></th>
                        <td>
                            <?php echo esc_html($front->siteName()); ?>
                            <?php if ($site->siteName === null) : ?>
                                <span class="description"><?php echo esc_html__('(WordPress site title)', 'tonkatsu-seo'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Separator', 'tonkatsu-seo'); ?></th>
                        <td><code><?php echo esc_html($site->separator); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Parent titles in child page titles', 'tonkatsu-seo'); ?></th>
                        <td>
                            <?php
                            echo esc_html($site->includeParentTitles
                                ? __('Enabled', 'tonkatsu-seo')
                                : __('Disabled', 'tonkatsu-seo'));
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Default description', 'tonkatsu-seo'); ?></th>
                        <td><?php echo esc_html(self::display($site->defaultDescription)); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Default OG image', 'tonkatsu-seo'); ?></th>
                        <td><?php echo esc_html(self::display($site->defaultOgImage)); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Twitter account', 'tonkatsu-seo'); ?></th>
                        <td><?php echo esc_html(self::display($site->twitterSite)); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Locale', 'tonkatsu-seo'); ?></th>
                        <td><code><?php echo esc_html($front->locale()); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Organization', 'tonkatsu-seo'); ?></th>
                        <td>
                            <?php if ($site->organization === null) : ?>
                                —
                            <?php else : ?>
                                <?php echo esc_html($site->organization->name); ?><br>
                                <?php echo esc_html(self::display($site->organization->url)); ?><br>
                                <?php echo esc_html(self::display($site->organization->logo)); ?>
                                <?php foreach ($site->organization->sameAs as $profile) : ?>
                                    <br><?php echo esc_html($profile); ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Sitemap', 'tonkatsu-seo'); ?></th>
                        <td>
                            <?php
                            echo esc_html($site->sitemap->enabled
                                ? __('Enabled', 'tonkatsu-seo')
                                : __('Disabled', 'tonkatsu-seo'));
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Excluded providers', 'tonkatsu-seo'); ?></th>
                        <td><?php echo esc_html(self::displayList($site->sitemap->excludeProviders)); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Excluded post types', 'tonkatsu-seo'); ?></th>
                        <td><?php echo esc_html(self::displayList($site->sitemap->excludePostTypes)); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Excluded taxonomies', 'tonkatsu-seo'); ?></th>
                        <td><?php echo esc_html(self::displayList($site->sitemap->excludeTaxonomies)); ?></td>
                    </tr>
                </tbody>
            </table>

            <h2><?php echo esc_html__('Registered pages', 'tonkatsu-seo'); ?></h2>
            <p class="description"><?php echo esc_html__('Values as each page outputs them: the tonkatsu_post_values filter, the page configuration and the fallbacks, already resolved.', 'tonkatsu-seo'); ?></p>
            <p class="description">
                <?php echo esc_html__('Pages without a registration are listed with their values on the Pages screen.', 'tonkatsu-seo'); ?>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=page')); ?>"><?php echo esc_html__('Pages', 'tonkatsu-seo'); ?></a>
            </p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" style="width:160px"><?php echo esc_html__('Path', 'tonkatsu-seo'); ?></th>
                        <th scope="col"><?php echo esc_html__('Title', 'tonkatsu-seo'); ?></th>
                        <th scope="col"><?php echo esc_html__('Description', 'tonkatsu-seo'); ?></th>
                        <th scope="col"><?php echo esc_html__('Canonical', 'tonkatsu-seo'); ?></th>
                        <th scope="col" style="width:140px"><?php echo esc_html__('Robots', 'tonkatsu-seo'); ?></th>
                        <th scope="col"><?php echo esc_html__('OG image', 'tonkatsu-seo'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (Seo::getPages() === []) : ?>
                        <tr>
                            <td colspan="6"><?php echo esc_html__('No pages are registered.', 'tonkatsu-seo'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach (array_keys(Seo::getPages()) as $path) :
                            $path = (string) $path;
                            $context = Context::forPath($path);
                            $resolver = Resolver::fromContext($context);
                            $values = $resolver->toArray();
                            $cells = AdminColumns::cells($resolver, $context, $searchEnginesDiscouraged);
                        ?>
                            <tr>
                                <td>
                                    <code><?php echo esc_html('/' . $path . ($path !== '' ? '/' : '')); ?></code>
                                    <?php if ($path === '') : ?>
                                        <br><span class="description"><?php echo esc_html__('(front page)', 'tonkatsu-seo'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo wp_kses(AdminColumns::cellHtml($cells[AdminColumns::COLUMN_TITLE]), AdminColumns::ALLOWED_HTML); ?></td>
                                <td><?php echo wp_kses(AdminColumns::cellHtml($cells[AdminColumns::COLUMN_DESCRIPTION]), AdminColumns::ALLOWED_HTML); ?></td>
                                <td style="word-break:break-all;"><?php echo esc_html(self::display($values['canonical'])); ?></td>
                                <td><?php echo wp_kses(AdminColumns::cellHtml($cells[AdminColumns::COLUMN_ROBOTS]), AdminColumns::ALLOWED_HTML); ?></td>
                                <td style="word-break:break-all;"><?php echo esc_html(self::display($values['og_image'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2><?php echo esc_html__('Post type archives', 'tonkatsu-seo'); ?></h2>
            <?php
            self::renderArchiveTable(
                Seo::getArchives(),
                __('Post type', 'tonkatsu-seo'),
                __('No post type archives are registered.', 'tonkatsu-seo')
            );
            ?>

            <h2><?php echo esc_html__('Taxonomies', 'tonkatsu-seo'); ?></h2>
            <?php
            self::renderArchiveTable(
                Seo::getTaxonomies(),
                __('Taxonomy', 'tonkatsu-seo'),
                __('No taxonomies are registered.', 'tonkatsu-seo')
            );
            ?>

            <h2><?php echo esc_html__('Redirects', 'tonkatsu-seo'); ?></h2>
            <?php self::renderRedirectTable(Seo::getRedirects(), $site->logRedirects); ?>
        </div>
        <?php
    }

    /**
     * @param array<string, ArchiveConfig> $configs
     * @param string $keyLabel
     * @param string $emptyMessage
     */
    protected static function renderArchiveTable(array $configs, string $keyLabel, string $emptyMessage): void
    {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" style="width:160px"><?php echo esc_html($keyLabel); ?></th>
                    <th scope="col"><?php echo esc_html__('Title', 'tonkatsu-seo'); ?></th>
                    <th scope="col"><?php echo esc_html__('Description', 'tonkatsu-seo'); ?></th>
                    <th scope="col" style="width:140px"><?php echo esc_html__('Robots', 'tonkatsu-seo'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($configs === []) : ?>
                    <tr>
                        <td colspan="4"><?php echo esc_html($emptyMessage); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($configs as $name => $config) : ?>
                        <tr>
                            <td><code><?php echo esc_html($name); ?></code></td>
                            <td><?php echo esc_html(self::display($config->title)); ?></td>
                            <td><?php echo esc_html(self::display($config->description)); ?></td>
                            <td>
                                <?php if (Visibility::searchEnginesDiscouraged()) : ?>
                                    <?php echo esc_html(self::robots(true, true)); ?><br><?php echo wp_kses(AdminColumns::badgeHtml(AdminColumns::searchEnginesDiscouragedBadge()), AdminColumns::ALLOWED_HTML); ?>
                                <?php else : ?>
                                    <?php echo esc_html(self::robots($config->noindex, false)); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * @param list<RedirectConfig> $redirects
     * @param bool $logging SiteConfig::$logRedirects: adds the Hits and Last hit columns.
     */
    protected static function renderRedirectTable(array $redirects, bool $logging): void
    {
        $home = home_url('/');
        $stats = $logging ? RedirectLog::getStats() : [];
        $columns = $logging ? 6 : 4;
        ?>
        <p class="description"><?php echo esc_html__('Checked in this order: exact paths, then prefixes (longest first), then regular expressions in registration order.', 'tonkatsu-seo'); ?></p>
        <?php if ($logging) : ?>
            <p class="description">
                <?php echo esc_html__('Every redirect is logged (SiteConfig logRedirects).', 'tonkatsu-seo'); ?>
                <a href="<?php echo esc_url(self::redirectLogUrl()); ?>"><?php echo esc_html__('Redirect log', 'tonkatsu-seo'); ?></a>
            </p>
        <?php endif; ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" style="width:100px"><?php echo esc_html__('Type', 'tonkatsu-seo'); ?></th>
                    <th scope="col"><?php echo esc_html__('From', 'tonkatsu-seo'); ?></th>
                    <th scope="col"><?php echo esc_html__('To', 'tonkatsu-seo'); ?></th>
                    <th scope="col" style="width:100px"><?php echo esc_html__('Status', 'tonkatsu-seo'); ?></th>
                    <?php if ($logging) : ?>
                        <th scope="col" style="width:80px"><?php echo esc_html__('Hits', 'tonkatsu-seo'); ?></th>
                        <th scope="col" style="width:160px"><?php echo esc_html__('Last hit', 'tonkatsu-seo'); ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($redirects === []) : ?>
                    <tr>
                        <td colspan="<?php echo esc_attr((string) $columns); ?>"><?php echo esc_html__('No redirects are registered.', 'tonkatsu-seo'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($redirects as $redirect) :
                        $ruleKey = RedirectLog::ruleKey($redirect);
                        $stat = $stats[$ruleKey] ?? null;
                    ?>
                        <tr>
                            <td><?php echo esc_html(self::redirectType($redirect->type)); ?></td>
                            <td style="word-break:break-all;">
                                <code><?php echo esc_html(self::redirectSource($redirect)); ?></code>
                                <?php if ($redirect->type === RedirectConfig::TYPE_EXACT && $redirect->path === '') : ?>
                                    <br><span class="description"><?php echo esc_html__('(front page)', 'tonkatsu-seo'); ?></span>
                                <?php endif; ?>
                                <?php if (Redirects::hidesRegisteredPage($redirect)) : ?>
                                    <br><?php echo wp_kses(AdminColumns::badgeHtml(AdminColumns::badge(AdminColumns::TONE_WARNING, __('Registered page is unreachable', 'tonkatsu-seo'))), AdminColumns::ALLOWED_HTML); ?>
                                <?php endif; ?>
                            </td>
                            <td style="word-break:break-all;">
                                <?php echo esc_html(self::display($redirect->to)); ?>
                                <?php if (Redirects::isChained($redirect, $redirects, $home)) : ?>
                                    <br><?php echo wp_kses(AdminColumns::badgeHtml(AdminColumns::badge(AdminColumns::TONE_WARNING, __('Redirected again', 'tonkatsu-seo'))), AdminColumns::ALLOWED_HTML); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html((string) $redirect->status); ?><?php echo $redirect->status === 410 ? ' ' . esc_html__('(Gone)', 'tonkatsu-seo') : ''; ?></td>
                            <?php if ($logging) : ?>
                                <td>
                                    <?php if ($stat === null) : ?>
                                        0
                                    <?php else : ?>
                                        <a href="<?php echo esc_url(self::redirectLogUrl($ruleKey)); ?>"><?php echo esc_html(number_format_i18n((int) $stat->hits)); ?></a>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($stat === null ? '—' : self::localTime((string) $stat->last_hit_at)); ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render the redirect log (`&view=redirect-log`): newest first, filterable by rule.
     */
    protected static function renderRedirectLogPage(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter, no state change
        $ruleKey = isset($_GET['rule']) ? sanitize_key(wp_unslash($_GET['rule'])) : '';
        if (preg_match('/^[0-9a-f]{32}$/', $ruleKey) !== 1) {
            $ruleKey = '';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination, no state change
        $page = max(1, absint(wp_unslash($_GET['paged'] ?? 1)));

        $stats = RedirectLog::getStats();
        $result = RedirectLog::getEntries($ruleKey !== '' ? $ruleKey : null, self::LOG_PER_PAGE, $page);
        $pages = (int) ceil($result['total'] / self::LOG_PER_PAGE);
        $pagination = $pages > 1
            ? paginate_links([
                'base'      => add_query_arg('paged', '%#%', self::redirectLogUrl($ruleKey !== '' ? $ruleKey : null)),
                'format'    => '',
                'current'   => $page,
                'total'     => $pages,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
            ])
            : '';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Redirect log', 'tonkatsu-seo'); ?></h1>

            <p>
                <a href="<?php echo esc_url(admin_url('tools.php?page=' . Consts::ADMIN_PAGE_SLUG)); ?>">&larr; <?php echo esc_html__('SEO Settings (TONKATSU)', 'tonkatsu-seo'); ?></a>
            </p>

            <?php if (!Seo::getSite()->logRedirects) : ?>
                <p><?php echo esc_html__('Redirects are not being logged. Set logRedirects in the theme\'s SiteConfig to start.', 'tonkatsu-seo'); ?></p>
            <?php endif; ?>

            <p class="description">
                <?php
                echo esc_html(sprintf(
                    /* translators: %d: number of days */
                    _n(
                        'Each redirect is kept for %d day; the hit counts on the previous screen are kept. No IP addresses or user agents are stored.',
                        'Each redirect is kept for %d days; the hit counts on the previous screen are kept. No IP addresses or user agents are stored.',
                        Seo::getSite()->redirectLogDays,
                        'tonkatsu-seo'
                    ),
                    Seo::getSite()->redirectLogDays
                ));
                ?>
            </p>

            <form method="get" action="<?php echo esc_url(admin_url('tools.php')); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr(Consts::ADMIN_PAGE_SLUG); ?>">
                <input type="hidden" name="view" value="<?php echo esc_attr(self::VIEW_REDIRECT_LOG); ?>">
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <label for="tonkatsu-filter-rule" class="screen-reader-text"><?php echo esc_html__('Filter by rule', 'tonkatsu-seo'); ?></label>
                        <select id="tonkatsu-filter-rule" name="rule">
                            <option value=""><?php echo esc_html__('All rules', 'tonkatsu-seo'); ?></option>
                            <?php foreach ($stats as $key => $stat) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($ruleKey, $key); ?>><?php echo esc_html(self::statLabel($stat)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php submit_button(__('Filter', 'tonkatsu-seo'), 'button', 'filter_action', false); ?>
                    </div>
                    <?php if ($pagination !== '') : ?>
                        <div class="tablenav-pages"><?php echo wp_kses_post((string) $pagination); ?></div>
                    <?php endif; ?>
                    <br class="clear">
                </div>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" style="width:160px"><?php echo esc_html__('Time', 'tonkatsu-seo'); ?></th>
                        <th scope="col"><?php echo esc_html__('Rule', 'tonkatsu-seo'); ?></th>
                        <th scope="col"><?php echo esc_html__('Requested', 'tonkatsu-seo'); ?></th>
                        <th scope="col"><?php echo esc_html__('To', 'tonkatsu-seo'); ?></th>
                        <th scope="col"><?php echo esc_html__('Referrer', 'tonkatsu-seo'); ?></th>
                        <th scope="col" style="width:60px"><?php echo esc_html__('Bot', 'tonkatsu-seo'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result['items'] === []) : ?>
                        <tr>
                            <td colspan="6"><?php echo esc_html__('No redirects have been logged.', 'tonkatsu-seo'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($result['items'] as $row) :
                            $stat = $stats[(string) $row->rule_key] ?? null;
                        ?>
                            <tr>
                                <td><?php echo esc_html(self::localTime((string) $row->created_at)); ?></td>
                                <td style="word-break:break-all;"><code><?php echo esc_html($stat !== null ? self::statLabel($stat) : (string) $row->rule_key); ?></code></td>
                                <td style="word-break:break-all;"><?php echo esc_html((string) $row->requested); ?></td>
                                <td style="word-break:break-all;">
                                    <?php echo esc_html((string) $row->status); ?>
                                    <?php if ($row->location !== null && $row->location !== '') : ?>
                                        &rarr; <?php echo esc_html((string) $row->location); ?>
                                    <?php else : ?>
                                        <?php echo esc_html__('(Gone)', 'tonkatsu-seo'); ?>
                                    <?php endif; ?>
                                </td>
                                <td style="word-break:break-all;"><?php echo esc_html(self::display((string) $row->referrer)); ?></td>
                                <td><?php echo (int) $row->is_bot === 1 ? esc_html__('Yes', 'tonkatsu-seo') : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($pagination !== '') : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages"><?php echo wp_kses_post((string) $pagination); ?></div>
                    <br class="clear">
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * URL of the redirect log, optionally filtered by rule.
     *
     * @param ?string $ruleKey
     * @return string
     */
    protected static function redirectLogUrl(?string $ruleKey = null): string
    {
        $args = ['page' => Consts::ADMIN_PAGE_SLUG, 'view' => self::VIEW_REDIRECT_LOG];
        if ($ruleKey !== null) {
            $args['rule'] = $ruleKey;
        }

        return add_query_arg($args, admin_url('tools.php'));
    }

    /**
     * A rule as the log knows it: "Exact /old-page/".
     *
     * @param \stdClass $stat A RedirectLog::getStats() row.
     * @return string
     */
    protected static function statLabel(\stdClass $stat): string
    {
        $type = (string) $stat->type;
        $source = (string) $stat->source;

        return self::redirectType($type) . ' ' . ($type === RedirectConfig::TYPE_REGEX ? $source : '/' . $source . ($source !== '' ? '/' : ''));
    }

    /**
     * A UTC datetime from the log, in the site's timezone and date/time formats.
     *
     * @param string $utc 'Y-m-d H:i:s' in UTC.
     * @return string
     */
    protected static function localTime(string $utc): string
    {
        $timestamp = strtotime($utc . ' UTC');
        if ($timestamp === false) {
            return $utc;
        }

        $date = wp_date((string) get_option('date_format') . ' ' . (string) get_option('time_format'), $timestamp);

        return is_string($date) ? $date : $utc;
    }

    /**
     * @param string $type One of the RedirectConfig::TYPE_* constants.
     * @return string
     */
    protected static function redirectType(string $type): string
    {
        return match ($type) {
            RedirectConfig::TYPE_PREFIX => __('Prefix', 'tonkatsu-seo'),
            RedirectConfig::TYPE_REGEX  => __('Regex', 'tonkatsu-seo'),
            default                     => __('Exact', 'tonkatsu-seo'),
        };
    }

    /**
     * The source as written for a regex, or as a /path/ otherwise.
     *
     * @param RedirectConfig $redirect
     * @return string
     */
    protected static function redirectSource(RedirectConfig $redirect): string
    {
        if ($redirect->type === RedirectConfig::TYPE_REGEX) {
            return $redirect->path;
        }

        return '/' . $redirect->path . ($redirect->path !== '' ? '/' : '');
    }

    /**
     * @param ?string $value
     * @return string The value, or "—" when unset.
     */
    protected static function display(?string $value): string
    {
        return $value !== null && trim($value) !== '' ? $value : '—';
    }

    /**
     * @param string[] $values
     * @return string
     */
    protected static function displayList(array $values): string
    {
        return $values !== [] ? implode(', ', $values) : '—';
    }

    /**
     * @param bool $noindex
     * @param bool $nofollow
     * @return string
     */
    public static function robots(bool $noindex, bool $nofollow): string
    {
        return ($noindex ? 'noindex' : 'index') . ', ' . ($nofollow ? 'nofollow' : 'follow');
    }
}
