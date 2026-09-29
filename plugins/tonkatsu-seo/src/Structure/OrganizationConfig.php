<?php

namespace ToroPlugin\Structure;

use ToroPlugin\Helpers\Url;

/**
 * Organization configuration class.
 *
 * Output as the `Organization` node of the JSON-LD graph, and referenced as
 * the `WebSite` node's publisher.
 *
 * @package ToroPlugin\Structure
 */
class OrganizationConfig
{
    public function __construct(
        /**
         * Organization name.
         *
         * @var string
         */
        public readonly string $name,

        /**
         * Organization URL. Defaults to the site's home URL when output.
         *
         * @var ?string
         */
        public readonly ?string $url = null,

        /**
         * Logo image URL (absolute, or root-relative).
         *
         * @var ?string
         */
        public readonly ?string $logo = null,

        /**
         * Profile URLs elsewhere (social accounts, Wikipedia, …).
         *
         * @var string[]
         */
        public readonly array $sameAs = [],
    )
    {
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('OrganizationConfig: name must not be empty.');
        }

        if ($this->url !== null && !Url::isValid($this->url)) {
            throw new \InvalidArgumentException(sprintf("OrganizationConfig: url '%s' is not a valid URL.", esc_html($this->url)));
        }

        if ($this->logo !== null && !Url::isValid($this->logo)) {
            throw new \InvalidArgumentException(sprintf("OrganizationConfig: logo '%s' is not a valid URL.", esc_html($this->logo)));
        }

        foreach ($this->sameAs as $profile) {
            if (!is_string($profile) || !Url::isValid($profile)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'OrganizationConfig: every entry of sameAs must be a valid URL, got %s.',
                        esc_html(is_string($profile) ? "'{$profile}'" : get_debug_type($profile))
                    )
                );
            }
        }
    }
}
