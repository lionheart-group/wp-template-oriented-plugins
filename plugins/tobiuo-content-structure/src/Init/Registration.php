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
 * Hands the registry to core: every taxonomy, then every post type.
 *
 * Runs whether or not another permalink plugin is active — the post types
 * and taxonomies are the site's content model, which the theme expects
 * either way. Only the permalink handling (Init\Rewrite and friends) stands
 * down, and it hangs off the `tobiuo_registered` action fired here.
 */
class Registration
{
    /**
     * Register the init action. Call once from the plugin bootstrap.
     */
    public static function register(): void
    {
        add_action('init', [static::class, 'handOver'], Consts::REGISTER_PRIORITY);
    }

    /**
     * Register everything with core, check the permalink configs against
     * the result, and fire `tobiuo_registered`.
     */
    public static function handOver(): void
    {
        if (Registry::isHandedOver()) {
            return;
        }

        // The posts archive first, as core registers `post` before the
        // other post types.
        Posts::apply();

        foreach (Registry::getPostTypes() as $config) {
            $existing = get_post_type_object($config->name);
            if ($existing instanceof \WP_Post_Type && $existing->_builtin) {
                Registry::refuseBuiltin($config->name);
            }
        }

        foreach (Registry::getTaxonomies() as $config) {
            $existing = get_taxonomy($config->name);
            if ($existing instanceof \WP_Taxonomy && $existing->_builtin) {
                Registry::refuseBuiltinTaxonomy($config->name);
            }
        }

        foreach (Registry::getTaxonomies() as $config) {
            $result = register_taxonomy($config->name, $config->objectTypes, $config->args);

            if (is_wp_error($result)) {
                wp_die(
                    sprintf('Taxonomy "%s" could not be registered: %s', esc_html($config->name), esc_html($result->get_error_message())),
                    'TOBIUO Taxonomy Registration Error',
                    ['response' => 500]
                );
            }
        }

        foreach (Registry::getPostTypes() as $config) {
            $result = register_post_type($config->name, $config->args);

            if (is_wp_error($result)) {
                wp_die(
                    sprintf('Post type "%s" could not be registered: %s', esc_html($config->name), esc_html($result->get_error_message())),
                    'TOBIUO Post Type Registration Error',
                    ['response' => 500]
                );
            }
        }

        Registry::markHandedOver();

        $errors = [];
        foreach (Registry::getPostTypes() as $config) {
            $postType = get_post_type_object($config->name);

            if ($config->permalink === null || !$postType instanceof \WP_Post_Type) {
                continue;
            }

            $errors = array_merge($errors, self::permalinkErrors(
                $config->name,
                $config->permalink,
                is_array($postType->rewrite),
                (bool) $postType->has_archive,
                array_values(get_taxonomies()),
                get_object_taxonomies($config->name)
            ));
        }

        if ($errors !== []) {
            wp_die(
                implode('<br>', array_map('esc_html', $errors)),
                'TOBIUO Permalink Configuration Error',
                ['response' => 500]
            );
        }

        /**
         * Fires once TOBIUO has registered every taxonomy and post type.
         *
         * Hook here for code that needs the post types to exist. Runs during
         * `init` at Consts::REGISTER_PRIORITY.
         */
        do_action('tobiuo_registered');
    }

    /**
     * What is wrong with a post type's PermalinkConfig, given what core registered.
     *
     * Taxonomy tags are checked here rather than in PermalinkConfig because
     * taxonomies may be registered in any order, by TOBIUO or by anyone else.
     *
     * @param string $postType
     * @param PermalinkConfig $permalink
     * @param bool $rewrite Whether the post type has rewrite rules (`rewrite` is not false).
     * @param bool $hasArchive Whether the post type has an archive.
     * @param string[] $taxonomies Every registered taxonomy.
     * @param string[] $attached The taxonomies attached to the post type.
     * @return string[] Plain-text messages, empty when the config is usable.
     */
    public static function permalinkErrors(
        string $postType,
        PermalinkConfig $permalink,
        bool $rewrite,
        bool $hasArchive,
        array $taxonomies,
        array $attached
    ): array {
        $errors = [];

        if (!$rewrite) {
            $errors[] = sprintf('Post type "%s" has a PermalinkConfig but its "rewrite" argument is false, so it has no permalinks to customize.', $postType);
        }

        foreach ($permalink->taxonomyTags() as $taxonomy) {
            if (!in_array($taxonomy, $taxonomies, true)) {
                $errors[] = sprintf('Post type "%s": the permalink tag %%%s%% is not a core tag and no taxonomy "%s" is registered.', $postType, $taxonomy, $taxonomy);
            } elseif (!in_array($taxonomy, $attached, true)) {
                $errors[] = sprintf('Post type "%s": the permalink tag %%%s%% names the taxonomy "%s", which is not attached to the post type (TaxonomyConfig::$objectTypes or the post type\'s "taxonomies" argument).', $postType, $taxonomy, $taxonomy);
            }
        }

        if (!$hasArchive && $permalink->dateArchive) {
            $errors[] = sprintf('Post type "%s": dateArchive needs the post type\'s "has_archive" argument, as the date archives sit below the archive URL.', $postType);
        }

        if (!$hasArchive && $permalink->authorArchive) {
            $errors[] = sprintf('Post type "%s": authorArchive needs the post type\'s "has_archive" argument, as the author archives sit below the archive URL.', $postType);
        }

        return $errors;
    }
}
