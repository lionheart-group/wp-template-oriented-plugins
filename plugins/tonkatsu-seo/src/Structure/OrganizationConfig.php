<?php

namespace TonkatsuPlugin\Structure;

use TonkatsuPlugin\Helpers\Url;

/**
 * Organization configuration class.
 *
 * Output as the `Organization` node of the JSON-LD graph, and referenced as
 * the `WebSite` node's publisher.
 *
 * @package TonkatsuPlugin\Structure
 */
class OrganizationConfig
{
    /**
     * Keys accepted in $address, as the schema.org PostalAddress properties.
     *
     * @var string[]
     */
    public const ADDRESS_KEYS = [
        'postalCode',
        'addressCountry',
        'addressRegion',
        'addressLocality',
        'streetAddress',
    ];

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

        /**
         * schema.org type of the node: `Organization` or a subtype such as
         * `Corporation`, `LocalBusiness` or `NGO`.
         *
         * @var string
         */
        public readonly string $type = 'Organization',

        /**
         * Another name, e.g. the name in English.
         *
         * @var ?string
         */
        public readonly ?string $alternateName = null,

        /**
         * Postal address, keyed by the PostalAddress properties in
         * ADDRESS_KEYS (`postalCode`, `addressCountry`, `addressRegion`,
         * `addressLocality`, `streetAddress`).
         *
         * @var array<string, string>
         */
        public readonly array $address = [],

        /**
         * Phone number, preferably in international format (+81-52-000-0000).
         *
         * @var ?string
         */
        public readonly ?string $telephone = null,

        /**
         * Fax number, preferably in international format.
         *
         * @var ?string
         */
        public readonly ?string $faxNumber = null,

        /**
         * Date the organization was founded: `YYYY`, `YYYY-MM` or `YYYY-MM-DD`.
         *
         * @var ?string
         */
        public readonly ?string $foundingDate = null,
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

        if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $this->type) !== 1) {
            throw new \InvalidArgumentException(
                sprintf("OrganizationConfig: type '%s' must be a schema.org type name such as 'Corporation'.", esc_html($this->type))
            );
        }

        foreach ($this->address as $key => $value) {
            if (!in_array($key, self::ADDRESS_KEYS, true)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        "OrganizationConfig: unknown address key '%s'. Use %s.",
                        esc_html((string) $key),
                        esc_html(implode(', ', self::ADDRESS_KEYS))
                    )
                );
            }
            if (!is_string($value) || trim($value) === '') {
                throw new \InvalidArgumentException(
                    sprintf("OrganizationConfig: address '%s' must be a non-empty string.", esc_html($key))
                );
            }
        }

        if ($this->foundingDate !== null && preg_match('/^\d{4}(-(0[1-9]|1[0-2])(-(0[1-9]|[12]\d|3[01]))?)?$/', $this->foundingDate) !== 1) {
            throw new \InvalidArgumentException(
                sprintf("OrganizationConfig: foundingDate '%s' must be YYYY, YYYY-MM or YYYY-MM-DD.", esc_html($this->foundingDate))
            );
        }
    }
}
