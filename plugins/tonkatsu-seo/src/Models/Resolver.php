<?php

namespace TonkatsuPlugin\Models;

use TonkatsuPlugin\Consts;
use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Helpers\Url;
use TonkatsuPlugin\Structure\ArchiveConfig;
use TonkatsuPlugin\Structure\PageConfig;
use TonkatsuPlugin\Structure\SiteConfig;

/**
 * Computes the final SEO values for one Context.
 *
 * Priority, highest first — the first non-empty value wins:
 *
 *   1. `tonkatsu_post_values` filter (the queried post only)
 *   2. PageConfig registered for the context's path
 *   3. ArchiveConfig for the post type archive / taxonomy
 *   4. What WordPress already holds (excerpt, term description, featured
 *      image, permalink)
 *   5. SiteConfig defaults
 *
 * Because an empty value never wins, a `false` noindex at a higher level
 * cannot switch off a `true` one below it: noindex/nofollow can only be
 * added on the way up.
 *
 * Every input is passed to the constructor, so no method here touches
 * WordPress. fromContext() is where the inputs are gathered.
 */
class Resolver
{
    /**
     * Keys read from the `tonkatsu_post_values` filter, and their type.
     *
     * @var array<string, string>
     */
    public const POST_VALUE_KEYS = [
        'title'       => 'string',
        'description' => 'string',
        'og_image'    => 'url',
        'noindex'     => 'bool',
        'nofollow'    => 'bool',
        'canonical'   => 'url',
    ];

    /**
     * Sanitized `tonkatsu_post_values`; only valid, non-empty keys survive.
     *
     * @var array{title?: string, description?: string, og_image?: string, noindex?: true, nofollow?: true, canonical?: string}
     */
    public readonly array $postValues;

    /**
     * @param Context $context
     * @param SiteConfig $site
     * @param ?PageConfig $page Registered for the context's path.
     * @param ?ArchiveConfig $archive For the context's post type archive or taxonomy.
     * @param mixed $postValues Raw `tonkatsu_post_values` result; sanitized here.
     * @param string $homeUrl Makes root-relative URLs absolute.
     * @param string $fallbackSiteName `get_bloginfo('name')`.
     * @param string $fallbackLocale `get_locale()`.
     */
    public function __construct(
        public readonly Context $context,
        public readonly SiteConfig $site,
        public readonly ?PageConfig $page = null,
        public readonly ?ArchiveConfig $archive = null,
        mixed $postValues = [],
        public readonly string $homeUrl = '',
        public readonly string $fallbackSiteName = '',
        public readonly string $fallbackLocale = 'en_US',
    )
    {
        $this->postValues = self::sanitizePostValues($postValues);
    }

    /**
     * Gather every input for $context from the registry and WordPress.
     *
     * @param Context $context
     * @return self
     */
    public static function fromContext(Context $context): self
    {
        $noPageLookup = in_array($context->type, [Context::TYPE_SEARCH, Context::TYPE_404], true);

        $archive = match ($context->type) {
            Context::TYPE_POST_TYPE_ARCHIVE => $context->postType !== null ? Seo::getArchive($context->postType) : null,
            Context::TYPE_TAXONOMY          => $context->taxonomy !== null ? Seo::getTaxonomy($context->taxonomy) : null,
            // The posts page is the `post` type's archive.
            Context::TYPE_HOME              => Seo::getArchive('post'),
            default                         => null,
        };

        $post = $context->post();

        return new self(
            context: $context,
            site: Seo::getSite(),
            page: $noPageLookup ? null : Seo::getPage($context->path),
            archive: $archive,
            postValues: $post !== null ? self::filterPostValues($post) : [],
            homeUrl: home_url('/'),
            fallbackSiteName: (string) get_bloginfo('name'),
            fallbackLocale: get_locale(),
        );
    }

    /**
     * Run the `tonkatsu_post_values` filter for a post.
     *
     * @param \WP_Post $post
     * @return mixed Unsanitized; the constructor sanitizes it.
     */
    public static function filterPostValues(\WP_Post $post): mixed
    {
        /**
         * Filters per-post SEO values, e.g. from custom fields.
         *
         * Returned values take precedence over every configuration object.
         * Recognised keys: title, description, og_image, noindex, nofollow,
         * canonical. Empty, unknown or wrongly-typed values are ignored.
         *
         * @param array    $values Empty; add the keys to override.
         * @param \WP_Post $post
         */
        return apply_filters('tonkatsu_post_values', [], $post);
    }

    /**
     * Keep the recognised, correctly-typed, non-empty values.
     *
     * A filter returning something other than an array yields [], i.e. the
     * values the filter would have overridden stay in effect. Strings are
     * trimmed; URLs must pass Url::isValid(); booleans must be real bools,
     * and only `true` is kept, since `false` is an empty value.
     *
     * @param mixed $values
     * @return array{title?: string, description?: string, og_image?: string, noindex?: true, nofollow?: true, canonical?: string}
     */
    public static function sanitizePostValues(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $clean = [];
        foreach (self::POST_VALUE_KEYS as $key => $type) {
            $value = $values[$key] ?? null;

            if ($type === 'bool') {
                if ($value === true) {
                    $clean[$key] = true;
                }
                continue;
            }

            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $value = trim($value);
            if ($type === 'url' && !Url::isValid($value)) {
                continue;
            }

            $clean[$key] = $value;
        }

        /** @var array{title?: string, description?: string, og_image?: string, noindex?: true, nofollow?: true, canonical?: string} $clean */
        return $clean;
    }

    /**
     * The configured title part, or null to keep WordPress's own.
     *
     * @return ?string
     */
    public function title(): ?string
    {
        return self::first(
            $this->postValues['title'] ?? null,
            $this->page?->title,
            $this->archive?->title,
        );
    }

    /**
     * og:title: the configured title, else the object's name, else the site
     * name. The front page falls back to the site name, not the front
     * page's own post title.
     *
     * @return string
     */
    public function ogTitle(): string
    {
        return $this->title()
            ?? ($this->context->isFront() ? null : $this->context->title)
            ?? $this->siteName();
    }

    /**
     * @return ?string
     */
    public function description(): ?string
    {
        return self::first(
            $this->postValues['description'] ?? null,
            $this->page?->description,
            $this->archive?->description,
            $this->context->description,
            $this->site->defaultDescription,
        );
    }

    /**
     * A canonical URL explicitly configured for this context, if any.
     *
     * @return ?string
     */
    public function canonicalOverride(): ?string
    {
        $canonical = self::first(
            $this->postValues['canonical'] ?? null,
            $this->page?->canonical,
        );

        return $canonical !== null ? Url::absolute($canonical, $this->homeUrl) : null;
    }

    /**
     * The configured canonical, else the context's own URL.
     *
     * Null for search results and 404s, which have no canonical page.
     *
     * @return ?string
     */
    public function canonical(): ?string
    {
        if (in_array($this->context->type, [Context::TYPE_SEARCH, Context::TYPE_404], true)) {
            return null;
        }

        return $this->canonicalOverride() ?? $this->context->url;
    }

    /**
     * @return bool
     */
    public function noindex(): bool
    {
        return ($this->postValues['noindex'] ?? false)
            || $this->page?->noindex === true
            || $this->archive?->noindex === true
            || in_array($this->context->type, [Context::TYPE_SEARCH, Context::TYPE_404], true);
    }

    /**
     * @return bool
     */
    public function nofollow(): bool
    {
        return ($this->postValues['nofollow'] ?? false)
            || $this->page?->nofollow === true;
    }

    /**
     * @return ?string
     */
    public function ogImage(): ?string
    {
        $image = self::first(
            $this->postValues['og_image'] ?? null,
            $this->page?->ogImage,
            $this->context->image,
            $this->site->defaultOgImage,
        );

        return $image !== null ? Url::absolute($image, $this->homeUrl) : null;
    }

    /**
     * `article` for a post or page, `website` for everything else,
     * including a static front page.
     *
     * @return string
     */
    public function ogType(): string
    {
        return $this->context->type === Context::TYPE_SINGULAR ? 'article' : 'website';
    }

    /**
     * @return ?string
     */
    public function ogUrl(): ?string
    {
        return $this->canonical();
    }

    /**
     * @return string
     */
    public function siteName(): string
    {
        return $this->site->siteName ?? $this->fallbackSiteName;
    }

    /**
     * @return string
     */
    public function separator(): string
    {
        return $this->site->separator;
    }

    /**
     * og:locale, in `ll_TT` form where it can be derived.
     *
     * `de_DE_formal` becomes `de_DE`; region-less locales listed in
     * Consts::LOCALE_REGIONS (`ja`) are mapped; anything else is passed
     * through.
     *
     * @return string
     */
    public function locale(): string
    {
        if ($this->site->locale !== null) {
            return $this->site->locale;
        }

        if (preg_match('/^([a-z]{2,3}_[A-Z]{2})/', $this->fallbackLocale, $m) === 1) {
            return $m[1];
        }

        return Consts::LOCALE_REGIONS[$this->fallbackLocale] ?? $this->fallbackLocale;
    }

    /**
     * Every resolved value, keyed like `tonkatsu_post_values`.
     *
     * @return array{title: ?string, description: ?string, canonical: ?string, noindex: bool, nofollow: bool, og_image: ?string, og_type: string, og_url: ?string}
     */
    public function toArray(): array
    {
        return [
            'title'       => $this->title(),
            'description' => $this->description(),
            'canonical'   => $this->canonical(),
            'noindex'     => $this->noindex(),
            'nofollow'    => $this->nofollow(),
            'og_image'    => $this->ogImage(),
            'og_type'     => $this->ogType(),
            'og_url'      => $this->ogUrl(),
        ];
    }

    /**
     * The first candidate that is a non-empty string after trimming.
     *
     * @param ?string ...$candidates
     * @return ?string
     */
    private static function first(?string ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if ($candidate !== null && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }
}
