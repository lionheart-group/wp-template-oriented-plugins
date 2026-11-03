<?php

namespace TonkatsuPlugin\Structure;

use TonkatsuPlugin\Helpers\Url;

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

        /**
         * URLs outside WordPress (static landing pages and the like) to list
         * in a sitemap of their own, `/wp-sitemap-static-1.xml`.
         *
         * Each entry is a URL string, or `['url' => ..., 'lastmod' => ...]`
         * to write the last modified date instead of reading it from the
         * file. URLs are root-relative (`/lp/campaign/`) or absolute http(s)
         * URLs on the site's own host; absolute URLs on another host are left
         * out of the sitemap, as the sitemap protocol does not allow them.
         *
         * @var array<int, string|array{url: string, lastmod?: string}>
         */
        public readonly array $extraUrls = [],
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

        foreach ($this->extraUrls as $index => $entry) {
            self::validateExtraUrl($index, $entry);
        }
    }

    /**
     * extraUrls as `url` / `lastmod` pairs, in the order written.
     *
     * @return list<array{url: string, lastmod: ?string}>
     */
    public function extraUrlEntries(): array
    {
        $entries = [];
        foreach ($this->extraUrls as $entry) {
            $entries[] = is_string($entry)
                ? ['url' => $entry, 'lastmod' => null]
                : ['url' => $entry['url'], 'lastmod' => $entry['lastmod'] ?? null];
        }

        return $entries;
    }

    /**
     * Whether the value is a sitemap date: `Y-m-d`, or a W3C datetime
     * (ISO 8601 with a time zone, such as `2026-10-01T09:00:00+09:00`).
     *
     * @param string $value
     * @return bool
     */
    public static function isLastmod(string $value): bool
    {
        $pattern = '/^(\d{4})-(\d{2})-(\d{2})'
            . '(?:T(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:\.\d+)?)?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d))?$/';

        if (preg_match($pattern, $value, $m) !== 1) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /**
     * @param int|string $index
     * @param mixed      $entry
     */
    private static function validateExtraUrl(int|string $index, mixed $entry): void
    {
        if (is_array($entry)) {
            $unknown = array_diff(array_map('strval', array_keys($entry)), ['url', 'lastmod']);
            if ($unknown !== []) {
                throw new \InvalidArgumentException(sprintf(
                    'SitemapConfig: unknown key "%s" in extraUrls[%s]. Use url and lastmod.',
                    esc_html((string) reset($unknown)),
                    esc_html((string) $index)
                ));
            }

            if (!array_key_exists('url', $entry)) {
                throw new \InvalidArgumentException(
                    sprintf('SitemapConfig: extraUrls[%s] needs a url.', esc_html((string) $index))
                );
            }

            if (array_key_exists('lastmod', $entry)
                && (!is_string($entry['lastmod']) || !self::isLastmod($entry['lastmod']))) {
                throw new \InvalidArgumentException(sprintf(
                    'SitemapConfig: lastmod of extraUrls[%s] must be Y-m-d or a W3C datetime such as 2026-10-01T09:00:00+09:00.',
                    esc_html((string) $index)
                ));
            }

            $url = $entry['url'];
        } else {
            $url = $entry;
        }

        if (!is_string($url) || !Url::isValid($url) || str_contains($url, '#')) {
            throw new \InvalidArgumentException(sprintf(
                'SitemapConfig: extraUrls[%s] must be a root-relative path (/lp/campaign/) or an absolute http(s) URL without a fragment.',
                esc_html((string) $index)
            ));
        }
    }
}
