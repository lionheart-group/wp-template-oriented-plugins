<?php

namespace ToroPlugin\Init;

use ToroPlugin\Consts;
use ToroPlugin\Helpers\Seo;
use ToroPlugin\Models\Context;
use ToroPlugin\Models\Resolver;
use ToroPlugin\Structure\ArchiveConfig;

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
    }

    /**
     * Resolve the capability required to view the page.
     *
     * Used both to register the menu and to guard the page itself — filtering only
     * one of the two would show a menu entry that dies on click, or hide a page that
     * is still reachable by URL.
     */
    private static function capability(): string
    {
        /**
         * Filters the capability required to view the TORO admin page.
         *
         * @param string $capability Defaults to 'manage_options'.
         */
        $capability = apply_filters('toro_admin_page_capability', Consts::DEFAULT_CAPABILITY);

        return is_string($capability) && $capability !== '' ? $capability : Consts::DEFAULT_CAPABILITY;
    }

    /**
     * Register the page under Tools.
     */
    public static function addMenuPage(): void
    {
        add_management_page(
            page_title: __('SEO Settings (TORO)', 'template-oriented-rank-optimizer'),
            menu_title: __('SEO (TORO)', 'template-oriented-rank-optimizer'),
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
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'template-oriented-rank-optimizer'));
        }

        $site = Seo::getSite();
        $front = Resolver::fromContext(new Context(type: Context::TYPE_FRONT, url: home_url('/')));
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('SEO Settings (TORO)', 'template-oriented-rank-optimizer'); ?></h1>

            <p><?php echo esc_html__('This screen is read-only. TORO is configured in the theme\'s PHP code, and nothing is stored in the database.', 'template-oriented-rank-optimizer'); ?></p>

            <h2><?php echo esc_html__('Site', 'template-oriented-rank-optimizer'); ?></h2>

            <?php if (!Seo::hasSite()) : ?>
                <p><?php echo esc_html__('The theme has not registered a SiteConfig, so the defaults below are in use.', 'template-oriented-rank-optimizer'); ?></p>
            <?php endif; ?>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Site name', 'template-oriented-rank-optimizer'); ?></th>
                        <td>
                            <?php echo esc_html($front->siteName()); ?>
                            <?php if ($site->siteName === null) : ?>
                                <span class="description"><?php echo esc_html__('(WordPress site title)', 'template-oriented-rank-optimizer'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Separator', 'template-oriented-rank-optimizer'); ?></th>
                        <td><code><?php echo esc_html($site->separator); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Default description', 'template-oriented-rank-optimizer'); ?></th>
                        <td><?php echo esc_html(self::display($site->defaultDescription)); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Default OG image', 'template-oriented-rank-optimizer'); ?></th>
                        <td><?php echo esc_html(self::display($site->defaultOgImage)); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Twitter account', 'template-oriented-rank-optimizer'); ?></th>
                        <td><?php echo esc_html(self::display($site->twitterSite)); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Locale', 'template-oriented-rank-optimizer'); ?></th>
                        <td><code><?php echo esc_html($front->locale()); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Organization', 'template-oriented-rank-optimizer'); ?></th>
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
                        <th scope="row"><?php echo esc_html__('Sitemap', 'template-oriented-rank-optimizer'); ?></th>
                        <td>
                            <?php
                            echo esc_html($site->sitemap->enabled
                                ? __('Enabled', 'template-oriented-rank-optimizer')
                                : __('Disabled', 'template-oriented-rank-optimizer'));
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Excluded providers', 'template-oriented-rank-optimizer'); ?></th>
                        <td><?php echo esc_html(self::displayList($site->sitemap->excludeProviders)); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Excluded post types', 'template-oriented-rank-optimizer'); ?></th>
                        <td><?php echo esc_html(self::displayList($site->sitemap->excludePostTypes)); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Excluded taxonomies', 'template-oriented-rank-optimizer'); ?></th>
                        <td><?php echo esc_html(self::displayList($site->sitemap->excludeTaxonomies)); ?></td>
                    </tr>
                </tbody>
            </table>

            <h2><?php echo esc_html__('Registered pages', 'template-oriented-rank-optimizer'); ?></h2>
            <p class="description"><?php echo esc_html__('Values as each page outputs them: the toro_post_values filter, the page configuration and the fallbacks, already resolved.', 'template-oriented-rank-optimizer'); ?></p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" style="width:160px"><?php echo esc_html__('Path', 'template-oriented-rank-optimizer'); ?></th>
                        <th scope="col"><?php echo esc_html__('Title', 'template-oriented-rank-optimizer'); ?></th>
                        <th scope="col"><?php echo esc_html__('Description', 'template-oriented-rank-optimizer'); ?></th>
                        <th scope="col"><?php echo esc_html__('Canonical', 'template-oriented-rank-optimizer'); ?></th>
                        <th scope="col" style="width:140px"><?php echo esc_html__('Robots', 'template-oriented-rank-optimizer'); ?></th>
                        <th scope="col"><?php echo esc_html__('OG image', 'template-oriented-rank-optimizer'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (Seo::getPages() === []) : ?>
                        <tr>
                            <td colspan="6"><?php echo esc_html__('No pages are registered.', 'template-oriented-rank-optimizer'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach (array_keys(Seo::getPages()) as $path) :
                            $path = (string) $path;
                            $values = Resolver::fromContext(Context::forPath($path))->toArray();
                        ?>
                            <tr>
                                <td>
                                    <code><?php echo esc_html('/' . $path . ($path !== '' ? '/' : '')); ?></code>
                                    <?php if ($path === '') : ?>
                                        <br><span class="description"><?php echo esc_html__('(front page)', 'template-oriented-rank-optimizer'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(self::display($values['title'])); ?></td>
                                <td><?php echo esc_html(self::display($values['description'])); ?></td>
                                <td style="word-break:break-all;"><?php echo esc_html(self::display($values['canonical'])); ?></td>
                                <td><code><?php echo esc_html(self::robots($values['noindex'], $values['nofollow'])); ?></code></td>
                                <td style="word-break:break-all;"><?php echo esc_html(self::display($values['og_image'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2><?php echo esc_html__('Post type archives', 'template-oriented-rank-optimizer'); ?></h2>
            <?php
            self::renderArchiveTable(
                Seo::getArchives(),
                __('Post type', 'template-oriented-rank-optimizer'),
                __('No post type archives are registered.', 'template-oriented-rank-optimizer')
            );
            ?>

            <h2><?php echo esc_html__('Taxonomies', 'template-oriented-rank-optimizer'); ?></h2>
            <?php
            self::renderArchiveTable(
                Seo::getTaxonomies(),
                __('Taxonomy', 'template-oriented-rank-optimizer'),
                __('No taxonomies are registered.', 'template-oriented-rank-optimizer')
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
                    <th scope="col"><?php echo esc_html__('Title', 'template-oriented-rank-optimizer'); ?></th>
                    <th scope="col"><?php echo esc_html__('Description', 'template-oriented-rank-optimizer'); ?></th>
                    <th scope="col" style="width:140px"><?php echo esc_html__('Robots', 'template-oriented-rank-optimizer'); ?></th>
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
                            <td><code><?php echo esc_html(self::robots($config->noindex, false)); ?></code></td>
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
    protected static function robots(bool $noindex, bool $nofollow): string
    {
        return ($noindex ? 'noindex' : 'index') . ', ' . ($nofollow ? 'nofollow' : 'follow');
    }
}
