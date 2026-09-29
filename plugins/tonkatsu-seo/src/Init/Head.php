<?php

namespace ToroPlugin\Init;

use ToroPlugin\Consts;
use ToroPlugin\Helpers\Seo;
use ToroPlugin\Helpers\Url;
use ToroPlugin\Models\Context;
use ToroPlugin\Models\Resolver;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Everything TORO puts in <head>.
 *
 * The title, the robots meta and the singular canonical go through core's
 * own filters, so they cooperate with the theme and other plugins; the rest
 * (description, canonical for non-singular pages, OGP, Twitter Card,
 * JSON-LD) is printed in one block on `wp_head`.
 */
class Head
{
    /**
     * Resolver for the current request, built on first use.
     *
     * @var ?Resolver
     */
    protected static ?Resolver $resolver = null;

    /**
     * Register the hooks. Call once from the plugin bootstrap.
     */
    public static function register(): void
    {
        if (is_admin()) {
            return;
        }

        add_filter('document_title_parts', [static::class, 'filterTitleParts']);
        add_filter('document_title_separator', [static::class, 'filterSeparator']);
        add_filter('get_canonical_url', [static::class, 'filterCanonicalUrl'], 10, 2);
        add_filter('wp_robots', [static::class, 'filterRobots']);
        add_action('wp_head', [static::class, 'render'], Consts::HEAD_PRIORITY);
    }

    /**
     * The Resolver for the current main query.
     *
     * @return Resolver
     */
    public static function resolver(): Resolver
    {
        return static::$resolver ??= Resolver::fromContext(Context::fromQuery());
    }

    /**
     * Replace the title part (and the site part, when SiteConfig names the
     * site) of `wp_get_document_title()`.
     *
     * On the front page WordPress uses the site name as the title and adds
     * the tagline; a configured front page title replaces both.
     *
     * @param mixed $parts
     * @return mixed
     */
    public static function filterTitleParts(mixed $parts): mixed
    {
        if (!is_array($parts)) {
            return $parts;
        }

        return self::titleParts(static::resolver(), $parts);
    }

    /**
     * The title parts for a given Resolver (see filterTitleParts()).
     *
     * @param Resolver             $resolver
     * @param array<string, mixed> $parts
     * @return array<string, mixed>
     */
    public static function titleParts(Resolver $resolver, array $parts): array
    {
        $title = $resolver->title();
        $siteName = $resolver->site->siteName;

        if ($resolver->context->isFront()) {
            if ($title !== null) {
                $parts['title'] = $title;
                unset($parts['tagline']);
            } elseif ($siteName !== null) {
                $parts['title'] = $siteName;
            }

            return $parts;
        }

        if ($title !== null) {
            $parts['title'] = $title;
        }
        if ($siteName !== null && isset($parts['site'])) {
            $parts['site'] = $siteName;
        }
        if ($resolver->site->includeParentTitles) {
            $parts = self::insertParentTitles($parts, self::parentTitles($resolver->context));
        }

        return $parts;
    }

    /**
     * Titles of a singular page's ancestors, nearest parent first.
     *
     * Read from the breadcrumb trail, which holds the ancestors followed by
     * the page itself.
     *
     * @param Context $context
     * @return string[]
     */
    public static function parentTitles(Context $context): array
    {
        if (!$context->isSingular() || $context->breadcrumbs === []) {
            return [];
        }

        $trail = $context->breadcrumbs;
        $last = end($trail);
        if ($context->url !== null && ($last['url'] ?? null) === $context->url) {
            array_pop($trail);
        }

        return array_reverse(array_column($trail, 'name'));
    }

    /**
     * Insert parent titles before the site part (after the page number).
     *
     * @param array<string, mixed> $parts
     * @param string[]             $parentTitles
     * @return array<string, mixed>
     */
    private static function insertParentTitles(array $parts, array $parentTitles): array
    {
        if ($parentTitles === []) {
            return $parts;
        }

        $parents = [];
        foreach (array_values($parentTitles) as $i => $parentTitle) {
            $parents['toro_parent_' . ($i + 1)] = $parentTitle;
        }

        $position = array_search('site', array_keys($parts), true);
        if ($position === false) {
            return $parts + $parents;
        }

        return array_slice($parts, 0, $position, true) + $parents + array_slice($parts, $position, null, true);
    }

    /**
     * The `<title>` a singular page or the front page outputs, as text.
     *
     * Builds the same parts as `wp_get_document_title()` and runs them
     * through TORO's title logic and core's `document_title` filters, so the
     * admin screens show the finished string without loading the page. Other
     * code on `document_title_parts` is not applied: it reads the current
     * request, which on an admin screen is not the page being shown.
     *
     * @param Resolver $resolver
     * @return string
     */
    public static function documentTitle(Resolver $resolver): string
    {
        $context = $resolver->context;
        $siteName = get_bloginfo('name', 'display');

        if ($context->isFront()) {
            $parts = ['title' => $siteName, 'tagline' => get_bloginfo('description', 'display')];
        } else {
            $parts = ['title' => (string) $context->title, 'site' => $siteName];
        }

        $title = implode(' ' . Seo::getSite()->separator . ' ', array_filter(self::titleParts($resolver, $parts)));

        /** This filter is documented in wp-includes/general-template.php */
        $filtered = apply_filters('document_title', $title); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter

        // Core's own callbacks escape the title for HTML; decode it back to text.
        return html_entity_decode(is_string($filtered) ? $filtered : $title, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param mixed $separator
     * @return mixed
     */
    public static function filterSeparator(mixed $separator): mixed
    {
        return Seo::getSite()->separator;
    }

    /**
     * Apply a configured canonical to core's singular `<link rel="canonical">`.
     *
     * Only an explicit override is returned. Otherwise core's value stands,
     * as it already accounts for paginated posts and comment pages.
     *
     * @param mixed $canonical
     * @param mixed $post
     * @return mixed
     */
    public static function filterCanonicalUrl(mixed $canonical, mixed $post = null): mixed
    {
        if (!$post instanceof \WP_Post) {
            return $canonical;
        }

        $override = Resolver::fromContext(Context::forPost($post))->canonicalOverride();

        return $override ?? $canonical;
    }

    /**
     * Add noindex / nofollow to core's robots meta.
     *
     * @param mixed $robots
     * @return mixed
     */
    public static function filterRobots(mixed $robots): mixed
    {
        if (!is_array($robots)) {
            return $robots;
        }

        $resolver = static::resolver();

        if ($resolver->noindex()) {
            unset($robots['index']);
            $robots['noindex'] = true;
        }
        if ($resolver->nofollow()) {
            unset($robots['follow']);
            $robots['nofollow'] = true;
        }

        return $robots;
    }

    /**
     * Print the description, canonical, OGP/Twitter and JSON-LD block.
     */
    public static function render(): void
    {
        $resolver = static::resolver();

        $description = $resolver->description();

        // Core prints the canonical of singular requests itself (rel_canonical()),
        // with filterCanonicalUrl() applied; everything else is left to us.
        $canonical = $resolver->context->isSingular() ? null : $resolver->canonical();

        $metaTags = self::metaTags($resolver);
        $jsonLd = self::jsonLdScript($resolver);

        if ($description === null && $canonical === null && $metaTags === [] && $jsonLd === null) {
            return;
        }

        // Every value is escaped where it is printed.
        echo "<!-- TORO -->\n";

        if ($description !== null) {
            printf('<meta name="description" content="%s" />' . "\n", esc_attr($description));
        }

        if ($canonical !== null) {
            printf('<link rel="canonical" href="%s" />' . "\n", esc_url($canonical));
        }

        foreach ($metaTags as [$attribute, $property, $value]) {
            printf(
                '<meta %s="%s" content="%s" />' . "\n",
                esc_attr($attribute),
                esc_attr($property),
                self::isUrlProperty($property) ? esc_url($value) : esc_attr($value)
            );
        }

        if ($jsonLd !== null) {
            wp_print_inline_script_tag($jsonLd, ['type' => 'application/ld+json']);
        }

        echo "<!-- /TORO -->\n";
    }

    /**
     * The OGP / Twitter Card meta tags to print, unescaped.
     *
     * @param Resolver $resolver
     * @return list<array{0: string, 1: string, 2: string}> [attribute, property, value]
     */
    private static function metaTags(Resolver $resolver): array
    {
        $tags = [];

        foreach (static::ogTags($resolver) as $property => $values) {
            if (!is_string($property) || $property === '') {
                continue;
            }

            // Twitter reads `name`; Open Graph is specified with `property`.
            $attribute = str_starts_with($property, 'twitter:') ? 'name' : 'property';

            foreach ((array) $values as $value) {
                if (!is_scalar($value) || (string) $value === '') {
                    continue;
                }
                $tags[] = [$attribute, $property, (string) $value];
            }
        }

        return $tags;
    }

    /**
     * The JSON-LD document, or null when there is nothing to print.
     *
     * Consts::JSON_LD_FLAGS includes JSON_HEX_TAG, so no value can close the
     * script element early — wp_print_inline_script_tag() would print
     * nothing at all if one did.
     *
     * @param Resolver $resolver
     * @return ?string
     */
    private static function jsonLdScript(Resolver $resolver): ?string
    {
        $graph = static::jsonLd($resolver);
        if ($graph === []) {
            return null;
        }

        $json = wp_json_encode(['@context' => 'https://schema.org', '@graph' => $graph], Consts::JSON_LD_FLAGS);

        return is_string($json) ? $json : null;
    }

    /**
     * The OGP and Twitter Card tags, after the `toro_og_tags` filter.
     *
     * @param Resolver $resolver
     * @return array<array-key, mixed> Property => content, or a list of contents.
     */
    public static function ogTags(Resolver $resolver): array
    {
        $image = $resolver->ogImage();

        $tags = array_filter([
            'og:site_name'   => $resolver->siteName(),
            'og:title'       => $resolver->ogTitle(),
            'og:description' => $resolver->description(),
            'og:type'        => $resolver->ogType(),
            'og:url'         => $resolver->ogUrl(),
            'og:image'       => $image,
            'og:locale'      => $resolver->locale(),
            'twitter:card'   => $image !== null ? 'summary_large_image' : 'summary',
            'twitter:site'   => $resolver->site->twitterSite,
        ], static fn ($value) => $value !== null && $value !== '');

        /**
         * Filters the OGP and Twitter Card tags.
         *
         * Keys are the property (`og:*`, `twitter:*`, or any other); a value
         * may be a string, or a list of strings to print the tag more than
         * once (e.g. several `og:image`). Return a non-array to keep the
         * defaults.
         *
         * @param array   $tags
         * @param Context $context
         */
        $filtered = apply_filters('toro_og_tags', $tags, $resolver->context);

        return is_array($filtered) ? $filtered : $tags;
    }

    /**
     * The JSON-LD `@graph`, after the `toro_json_ld` filter.
     *
     * @param Resolver $resolver
     * @return array<array-key, mixed>
     */
    public static function jsonLd(Resolver $resolver): array
    {
        $home = $resolver->homeUrl;
        $siteName = $resolver->siteName();
        $organization = $resolver->site->organization;
        $graph = [];

        $website = [
            '@type'      => 'WebSite',
            '@id'        => $home . '#website',
            'url'        => $home,
            'name'       => $siteName,
            'inLanguage' => str_replace('_', '-', $resolver->locale()),
        ];
        if ($resolver->site->defaultDescription !== null) {
            $website['description'] = $resolver->site->defaultDescription;
        }
        if ($organization !== null) {
            $website['publisher'] = ['@id' => $home . '#organization'];
        }
        $website['potentialAction'] = [
            '@type'       => 'SearchAction',
            'target'      => [
                '@type'       => 'EntryPoint',
                'urlTemplate' => $home . '?s={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ];
        $graph[] = $website;

        if ($organization !== null) {
            $node = [
                '@type' => 'Organization',
                '@id'   => $home . '#organization',
                'name'  => $organization->name,
                'url'   => $organization->url !== null
                    ? Url::absolute($organization->url, $home)
                    : $home,
            ];
            if ($organization->logo !== null) {
                $node['logo'] = [
                    '@type' => 'ImageObject',
                    'url'   => Url::absolute($organization->logo, $home),
                ];
            }
            if ($organization->sameAs !== []) {
                $node['sameAs'] = array_values($organization->sameAs);
            }
            $graph[] = $node;
        }

        $breadcrumbs = $resolver->context->breadcrumbs;
        if ($breadcrumbs !== []) {
            // The configured title is what the page calls itself, so it names
            // the last crumb too.
            $last = count($breadcrumbs) - 1;
            $title = $resolver->title();
            if ($title !== null) {
                $breadcrumbs[$last]['name'] = $title;
            }

            $items = [];
            foreach (array_merge([['name' => $siteName, 'url' => $home]], $breadcrumbs) as $i => $crumb) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $i + 1,
                    'name'     => $crumb['name'],
                    'item'     => $crumb['url'],
                ];
            }

            $graph[] = [
                '@type'           => 'BreadcrumbList',
                '@id'             => ($resolver->canonical() ?? $breadcrumbs[$last]['url']) . '#breadcrumb',
                'itemListElement' => $items,
            ];
        }

        /**
         * Filters the JSON-LD `@graph` nodes.
         *
         * Append, alter or remove nodes. Return an empty array to print no
         * JSON-LD at all, or a non-array to keep the defaults.
         *
         * @param array   $graph
         * @param Context $context
         */
        $filtered = apply_filters('toro_json_ld', $graph, $resolver->context);

        return is_array($filtered) ? array_values($filtered) : $graph;
    }

    /**
     * Properties whose content is a URL, and so is escaped with esc_url().
     *
     * @param string $property
     * @return bool
     */
    private static function isUrlProperty(string $property): bool
    {
        return in_array($property, ['og:url', 'og:image', 'og:image:url', 'og:image:secure_url', 'twitter:image'], true);
    }
}
