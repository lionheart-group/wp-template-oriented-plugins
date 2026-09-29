<?php

namespace TonkatsuPlugin\Structure;

/**
 * Archive configuration class.
 *
 * SEO values for a post type archive (`Seo::registerArchive()`) or for every
 * term archive of a taxonomy (`Seo::registerTaxonomy()`).
 *
 * @package TonkatsuPlugin\Structure
 */
class ArchiveConfig
{
    public function __construct(
        /**
         * Title part (the site name is appended by WordPress).
         *
         * For a taxonomy this applies to every term, so it usually stays
         * null — the term name is already the title.
         *
         * @var ?string
         */
        public readonly ?string $title = null,

        /**
         * Meta description and og:description.
         *
         * @var ?string
         */
        public readonly ?string $description = null,

        /**
         * Add `noindex` to the robots meta.
         *
         * @var bool
         */
        public readonly bool $noindex = false,
    ) {}
}
