<?php

namespace ToroPlugin\Structure;

use ToroPlugin\Consts;
use ToroPlugin\Helpers\Url;

/**
 * Site configuration class.
 *
 * Site-wide defaults. Register once with `Seo::setSite()`, on `init`.
 *
 * @package ToroPlugin\Structure
 */
class SiteConfig
{
    public function __construct(
        /**
         * Site name, used for the title's site part, og:site_name and JSON-LD.
         *
         * Null falls back to `get_bloginfo('name')`, read when the values are
         * resolved rather than now, so a change in Settings → General still
         * takes effect.
         *
         * @var ?string
         */
        public readonly ?string $siteName = null,

        /**
         * Title separator, e.g. `Page title | Site name`.
         *
         * @var string
         */
        public readonly string $separator = Consts::DEFAULT_SEPARATOR,

        /**
         * Description used when nothing more specific is set.
         *
         * @var ?string
         */
        public readonly ?string $defaultDescription = null,

        /**
         * og:image used when nothing more specific is set (absolute, or
         * root-relative).
         *
         * @var ?string
         */
        public readonly ?string $defaultOgImage = null,

        /**
         * twitter:site account, including the leading `@`.
         *
         * @var ?string
         */
        public readonly ?string $twitterSite = null,

        /**
         * og:locale in `ll_TT` form, e.g. `ja_JP`.
         *
         * Null derives it from `get_locale()`.
         *
         * @var ?string
         */
        public readonly ?string $locale = null,

        /**
         * Organization published as JSON-LD. Null outputs no Organization node.
         *
         * @var ?OrganizationConfig
         */
        public readonly ?OrganizationConfig $organization = null,

        /**
         * Core sitemap adjustments.
         *
         * @var SitemapConfig
         */
        public readonly SitemapConfig $sitemap = new SitemapConfig(),

        /**
         * Add the parent pages' titles to a child page's `<title>`, nearest
         * parent first: `Child | Parent | Site`.
         *
         * @var bool
         */
        public readonly bool $includeParentTitles = false,
    )
    {
        if ($this->siteName !== null && trim($this->siteName) === '') {
            throw new \InvalidArgumentException(
                'SiteConfig: siteName must not be empty. Use null to fall back to the WordPress site title.'
            );
        }

        if (trim($this->separator) === '') {
            throw new \InvalidArgumentException('SiteConfig: separator must not be empty.');
        }

        if ($this->defaultOgImage !== null && !Url::isValid($this->defaultOgImage)) {
            throw new \InvalidArgumentException(
                "SiteConfig: defaultOgImage '{$this->defaultOgImage}' is not a valid URL."
            );
        }

        if ($this->twitterSite !== null && preg_match('/^@[A-Za-z0-9_]{1,15}$/', $this->twitterSite) !== 1) {
            throw new \InvalidArgumentException(
                "SiteConfig: twitterSite '{$this->twitterSite}' must be an account name starting with '@', e.g. '@example'."
            );
        }

        if ($this->locale !== null && preg_match('/^[a-z]{2,3}_[A-Z]{2}$/', $this->locale) !== 1) {
            throw new \InvalidArgumentException(
                "SiteConfig: locale '{$this->locale}' must be in 'll_TT' form, e.g. 'ja_JP'."
            );
        }
    }
}
