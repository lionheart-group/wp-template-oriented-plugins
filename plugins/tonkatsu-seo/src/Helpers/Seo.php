<?php

namespace TonkatsuPlugin\Helpers;

use TonkatsuPlugin\Structure\ArchiveConfig;
use TonkatsuPlugin\Structure\PageConfig;
use TonkatsuPlugin\Structure\SiteConfig;

/**
 * The class themes call.
 *
 * An in-memory registry, filled from theme code on `init` for every request.
 * Nothing here is ever written to the database.
 */
class Seo
{
    /**
     * Site configuration.
     *
     * @var ?SiteConfig
     */
    protected static ?SiteConfig $site = null;

    /**
     * Page configurations keyed by normalized path.
     *
     * PHP turns numeric-string keys such as '2024' into integers, hence
     * array-key rather than string.
     *
     * @var array<array-key, PageConfig>
     */
    protected static array $pages = [];

    /**
     * Archive configurations keyed by post type.
     *
     * @var array<string, ArchiveConfig>
     */
    protected static array $archives = [];

    /**
     * Taxonomy configurations keyed by taxonomy.
     *
     * @var array<string, ArchiveConfig>
     */
    protected static array $taxonomies = [];

    /**
     * Register the site configuration.
     *
     * Call once in the `init` action, at a priority below 20 (the default 10
     * is fine) so the sitemap adjustments see it.
     *
     * @param SiteConfig $config
     * @return void
     */
    public static function setSite(SiteConfig $config): void
    {
        self::$site = $config;
    }

    /**
     * Get the site configuration, or the defaults when none is set.
     *
     * @return SiteConfig
     */
    public static function getSite(): SiteConfig
    {
        return self::$site ?? new SiteConfig();
    }

    /**
     * Whether setSite() has been called.
     *
     * @return bool
     */
    public static function hasSite(): bool
    {
        return self::$site !== null;
    }

    /**
     * Register SEO values for a URL path.
     *
     * The path is relative to the home URL; leading/trailing slashes do not
     * matter, and '' (or '/') is the front page:
     *
     * <code>
     * Seo::registerPage('/company/about/', new PageConfig(title: '会社概要'));
     * </code>
     *
     * @param string $path
     * @param PageConfig $config
     * @return void
     */
    public static function registerPage(string $path, PageConfig $config): void
    {
        $key = self::normalizePath($path);

        if (array_key_exists($key, self::$pages)) {
            wp_die(
                sprintf('Page with path "%s" is already registered.', esc_html($key === '' ? '/' : $key)),
                'TONKATSU Page Registration Error',
                ['response' => 500]
            );
        }

        self::$pages[$key] = $config;
    }

    /**
     * Register several pages at once, e.g. from a definition file.
     *
     * Values may be PageConfig instances or arrays accepted by
     * PageConfig::fromArray().
     *
     * @param array<array-key, PageConfig|array<array-key, mixed>> $pages Keyed by path.
     * @return void
     */
    public static function registerPages(array $pages): void
    {
        foreach ($pages as $path => $config) {
            $path = (string) $path;

            if (is_array($config)) {
                $config = PageConfig::fromArray($config, $path);
            }

            if (!$config instanceof PageConfig) {
                throw new \InvalidArgumentException(sprintf(
                    'Seo::registerPages(): "%s" must be a PageConfig or an array, got %s.',
                    esc_html((string) $path),
                    esc_html(get_debug_type($config))
                ));
            }

            self::registerPage($path, $config);
        }
    }

    /**
     * Get the page configuration for a path.
     *
     * @param string $path Normalized the same way as registerPage().
     * @return ?PageConfig
     */
    public static function getPage(string $path): ?PageConfig
    {
        return self::$pages[self::normalizePath($path)] ?? null;
    }

    /**
     * Get all registered pages, keyed by normalized path.
     *
     * @return array<array-key, PageConfig>
     */
    public static function getPages(): array
    {
        return self::$pages;
    }

    /**
     * Register SEO values for a post type archive.
     *
     * `post` covers the posts page (Settings → Reading) as well.
     *
     * @param string $postType
     * @param ArchiveConfig $config
     * @return void
     */
    public static function registerArchive(string $postType, ArchiveConfig $config): void
    {
        $postType = self::assertName($postType, 'post type');

        if (isset(self::$archives[$postType])) {
            wp_die(
                sprintf('Archive for post type "%s" is already registered.', esc_html($postType)),
                'TONKATSU Archive Registration Error',
                ['response' => 500]
            );
        }

        self::$archives[$postType] = $config;
    }

    /**
     * Get the archive configuration for a post type.
     *
     * @param string $postType
     * @return ?ArchiveConfig
     */
    public static function getArchive(string $postType): ?ArchiveConfig
    {
        return self::$archives[$postType] ?? null;
    }

    /**
     * Get all registered archives, keyed by post type.
     *
     * @return array<string, ArchiveConfig>
     */
    public static function getArchives(): array
    {
        return self::$archives;
    }

    /**
     * Register SEO values for every term archive of a taxonomy.
     *
     * @param string $taxonomy
     * @param ArchiveConfig $config
     * @return void
     */
    public static function registerTaxonomy(string $taxonomy, ArchiveConfig $config): void
    {
        $taxonomy = self::assertName($taxonomy, 'taxonomy');

        if (isset(self::$taxonomies[$taxonomy])) {
            wp_die(
                sprintf('Taxonomy "%s" is already registered.', esc_html($taxonomy)),
                'TONKATSU Taxonomy Registration Error',
                ['response' => 500]
            );
        }

        self::$taxonomies[$taxonomy] = $config;
    }

    /**
     * Get the configuration for a taxonomy's term archives.
     *
     * @param string $taxonomy
     * @return ?ArchiveConfig
     */
    public static function getTaxonomy(string $taxonomy): ?ArchiveConfig
    {
        return self::$taxonomies[$taxonomy] ?? null;
    }

    /**
     * Get all registered taxonomies, keyed by taxonomy.
     *
     * @return array<string, ArchiveConfig>
     */
    public static function getTaxonomies(): array
    {
        return self::$taxonomies;
    }

    /**
     * Normalize a path (or URL) into a registry key.
     *
     * Takes the path component of a full URL, drops the query string and
     * fragment, percent-decodes (so `/%E4%BC%9A%E7%A4%BE/` and `/会社/`
     * are the same page), collapses repeated slashes and trims slashes at
     * both ends. The front page is ''.
     *
     * @param string $path
     * @return string
     */
    public static function normalizePath(string $path): string
    {
        $path = trim($path);

        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $path) === 1) {
            $path = (string) wp_parse_url($path, PHP_URL_PATH);
        }

        $path = explode('#', $path, 2)[0];
        $path = explode('?', $path, 2)[0];
        $path = rawurldecode($path);
        $path = (string) preg_replace('#/+#', '/', $path);

        return trim($path, '/');
    }

    /**
     * @param string $name
     * @param string $kind For the error message.
     * @return string
     */
    private static function assertName(string $name, string $kind): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new \InvalidArgumentException(sprintf('Seo: %s name must not be empty.', esc_html($kind)));
        }

        return $name;
    }
}
