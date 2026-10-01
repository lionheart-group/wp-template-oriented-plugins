<?php

namespace TobiuoPlugin\Helpers;

use TobiuoPlugin\Consts;
use TobiuoPlugin\Structure\PermalinkConfig;
use TobiuoPlugin\Structure\PostsConfig;
use TobiuoPlugin\Structure\PostTypeConfig;
use TobiuoPlugin\Structure\TaxonomyConfig;

/**
 * The class themes call.
 *
 * An in-memory registry, filled from theme code on `init` for every request.
 * Nothing here is ever written to the database. Init\Registration hands the
 * contents to core at Consts::REGISTER_PRIORITY.
 */
class Registry
{
    /**
     * Post type configurations keyed by post type.
     *
     * @var array<string, PostTypeConfig>
     */
    protected static array $postTypes = [];

    /**
     * Taxonomy configurations keyed by taxonomy.
     *
     * @var array<string, TaxonomyConfig>
     */
    protected static array $taxonomies = [];

    /**
     * Configuration of the built-in `post` post type.
     *
     * @var ?PostsConfig
     */
    protected static ?PostsConfig $posts = null;

    /**
     * Whether the contents have been handed to core.
     *
     * @var bool
     */
    protected static bool $handedOver = false;

    /**
     * Register a post type.
     *
     * Call in the `init` action at a priority below Consts::REGISTER_PRIORITY
     * (the default 10 is fine):
     *
     * <code>
     * Registry::registerPostType(new PostTypeConfig(
     *     name: 'case',
     *     args: ['label' => '事例紹介', 'public' => true, 'has_archive' => true],
     *     permalink: new PermalinkConfig(structure: '/%case_category%/%postname%/'),
     * ));
     * </code>
     *
     * @param PostTypeConfig $config
     * @return void
     */
    public static function registerPostType(PostTypeConfig $config): void
    {
        self::assertOpen('registerPostType', $config->name);

        if (in_array($config->name, Consts::BUILTIN_POST_TYPES, true)) {
            self::refuseBuiltin($config->name);
        }

        if (isset(self::$postTypes[$config->name])) {
            wp_die(
                sprintf('Post type "%s" is already registered.', esc_html($config->name)),
                'TOBIUO Post Type Registration Error',
                ['response' => 500]
            );
        }

        self::$postTypes[$config->name] = $config;
    }

    /**
     * Register a taxonomy.
     *
     * Same timing as registerPostType(). The order of the two calls does not
     * matter: every taxonomy is handed to core before every post type.
     *
     * @param TaxonomyConfig $config
     * @return void
     */
    public static function registerTaxonomy(TaxonomyConfig $config): void
    {
        self::assertOpen('registerTaxonomy', $config->name);

        if (isset(self::$taxonomies[$config->name])) {
            wp_die(
                sprintf('Taxonomy "%s" is already registered.', esc_html($config->name)),
                'TOBIUO Taxonomy Registration Error',
                ['response' => 500]
            );
        }

        self::$taxonomies[$config->name] = $config;
    }

    /**
     * Configure the archive and permalink of core's `post` post type.
     *
     * `post` itself stays core's: it is not registered again. Call once,
     * with the same timing as registerPostType():
     *
     * <code>
     * Registry::registerPosts(new PostsConfig(
     *     archive: 'news',
     *     permalink: new PermalinkConfig(structure: '/%postname%/'),
     * ));
     * </code>
     *
     * @param PostsConfig $config
     * @return void
     */
    public static function registerPosts(PostsConfig $config): void
    {
        self::assertOpen('registerPosts', 'post');

        if (self::$posts !== null) {
            wp_die(
                'The posts configuration is already registered. Registry::registerPosts() may be called once.',
                'TOBIUO Posts Registration Error',
                ['response' => 500]
            );
        }

        self::$posts = $config;
    }

    /**
     * Get the configuration of core's `post` post type, if registered.
     *
     * @return ?PostsConfig
     */
    public static function getPosts(): ?PostsConfig
    {
        return self::$posts;
    }

    /**
     * Get the configuration for a post type.
     *
     * @param string $postType
     * @return ?PostTypeConfig
     */
    public static function getPostType(string $postType): ?PostTypeConfig
    {
        return self::$postTypes[$postType] ?? null;
    }

    /**
     * Get all registered post types, keyed by post type.
     *
     * @return array<string, PostTypeConfig>
     */
    public static function getPostTypes(): array
    {
        return self::$postTypes;
    }

    /**
     * Get the permalink configuration for a post type, if it has one.
     *
     * @param string $postType
     * @return ?PermalinkConfig
     */
    public static function getPermalink(string $postType): ?PermalinkConfig
    {
        return self::getPostType($postType)?->permalink;
    }

    /**
     * Get the configuration for a taxonomy.
     *
     * @param string $taxonomy
     * @return ?TaxonomyConfig
     */
    public static function getTaxonomy(string $taxonomy): ?TaxonomyConfig
    {
        return self::$taxonomies[$taxonomy] ?? null;
    }

    /**
     * Get all registered taxonomies, keyed by taxonomy.
     *
     * @return array<string, TaxonomyConfig>
     */
    public static function getTaxonomies(): array
    {
        return self::$taxonomies;
    }

    /**
     * Whether the registrations have been handed to core.
     *
     * @return bool
     */
    public static function isHandedOver(): bool
    {
        return self::$handedOver;
    }

    /**
     * Close the registry. Called by Init\Registration once it has registered everything.
     *
     * @return void
     * @internal
     */
    public static function markHandedOver(): void
    {
        self::$handedOver = true;
    }

    /**
     * Stop on a post type core registers itself: registering it again would
     * replace core's definition.
     *
     * @param string $name
     * @return void
     * @internal Also used by Init\Registration for post types core reports as built in.
     */
    public static function refuseBuiltin(string $name): void
    {
        wp_die(
            sprintf(
                'Post type "%s" is built into WordPress and cannot be registered with TOBIUO.%s',
                esc_html($name),
                $name === 'post' ? ' Use Registry::registerPosts(new PostsConfig(...)) to set its archive and permalink.' : ''
            ),
            'TOBIUO Post Type Registration Error',
            ['response' => 500]
        );
    }

    /**
     * A config registered after the hand-over would never reach core, so say so.
     *
     * @param string $method
     * @param string $name
     * @return void
     */
    private static function assertOpen(string $method, string $name): void
    {
        if (!self::$handedOver) {
            return;
        }

        wp_die(
            sprintf(
                'Registry::%s() was called for "%s" after TOBIUO registered everything (init priority %d). Register on init at a lower priority.',
                esc_html($method),
                esc_html($name),
                (int) Consts::REGISTER_PRIORITY
            ),
            'TOBIUO Registration Error',
            ['response' => 500]
        );
    }
}
