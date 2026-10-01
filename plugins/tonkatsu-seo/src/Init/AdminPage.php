<?php

namespace TonkatsuPlugin\Init;

use TonkatsuPlugin\Consts;
use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Helpers\Visibility;
use TonkatsuPlugin\Models\Context;
use TonkatsuPlugin\Models\Resolver;
use TonkatsuPlugin\Structure\ArchiveConfig;

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

        $site = Seo::getSite();
        $front = Resolver::fromContext(new Context(type: Context::TYPE_FRONT, url: home_url('/')));
        $searchEnginesDiscouraged = Visibility::searchEnginesDiscouraged();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('SEO Settings (TONKATSU)', 'tonkatsu-seo'); ?></h1>

            <p><?php echo esc_html__('This screen is read-only. TONKATSU is configured in the theme\'s PHP code, and nothing is stored in the database.', 'tonkatsu-seo'); ?></p>

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
