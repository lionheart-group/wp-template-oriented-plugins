<?php

namespace TonkatsuPlugin\Structure;

/**
 * Sitemap configuration class.
 *
 * Adjusts WordPress core's own sitemaps (`/wp-sitemap.xml`); TONKATSU does not
 * generate a sitemap of its own.
 *
 * @package TonkatsuPlugin\Structure
 */
class SitemapConfig
{
    public function __construct(
        /**
         * Whether core sitemaps are served at all.
         *
         * @var bool
         */
        public readonly bool $enabled = true,

        /**
         * Core sitemap providers to remove: `posts`, `taxonomies`, `users`.
         *
         * `users` is excluded by default. Author archives are rarely worth
         * indexing on a corporate site, and the user sitemap publishes every
         * author's login-derived slug.
         *
         * @var string[]
         */
        public readonly array $excludeProviders = ['users'],

        /**
         * Post type names to remove from the `posts` provider.
         *
         * @var string[]
         */
        public readonly array $excludePostTypes = [],

        /**
         * Taxonomy names to remove from the `taxonomies` provider.
         *
         * @var string[]
         */
        public readonly array $excludeTaxonomies = [],
    )
    {
        foreach ([
            'excludeProviders'  => $this->excludeProviders,
            'excludePostTypes'  => $this->excludePostTypes,
            'excludeTaxonomies' => $this->excludeTaxonomies,
        ] as $property => $values) {
            foreach ($values as $value) {
                if (!is_string($value) || trim($value) === '') {
                    throw new \InvalidArgumentException(
                        sprintf('SitemapConfig: every entry of %s must be a non-empty string.', esc_html($property))
                    );
                }
            }
        }
    }
}
