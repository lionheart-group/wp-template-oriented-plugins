<?php

namespace TobiuoPlugin\Structure;

use TobiuoPlugin\Helpers\Fields;

/**
 * Taxonomy configuration class.
 *
 * One taxonomy, registered with `Registry::registerTaxonomy()`. TOBIUO hands
 * it to register_taxonomy() on `init` (Consts::REGISTER_PRIORITY), before
 * every post type, so the order in which a theme registers them does not
 * matter.
 *
 * @package TobiuoPlugin\Structure
 */
class TaxonomyConfig
{
    /**
     * Keys accepted by fromArray(), mapped to their expected type.
     *
     * @var array<string, string>
     */
    private const FIELDS = [
        'name'        => 'string',
        'objectTypes' => 'string[]',
        'args'        => 'array',
    ];

    public function __construct(
        /**
         * Taxonomy key: lowercase letters, digits, `_` and `-`, at most
         * 32 characters (core's limit).
         *
         * @var string
         */
        public readonly string $name,

        /**
         * Post types the taxonomy is attached to. May be empty when the post
         * types attach it themselves through their `taxonomies` argument.
         *
         * @var string[]
         */
        public readonly array $objectTypes = [],

        /**
         * Arguments passed to register_taxonomy() as they are.
         *
         * @var array<string, mixed>
         */
        public readonly array $args = [],
    )
    {
        if (preg_match('/^[a-z0-9_-]{1,32}$/', $this->name) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                "TaxonomyConfig: name '%s' must be 1 to 32 characters of lowercase letters, digits, '_' and '-'.",
                esc_html($this->name)
            ));
        }

        foreach ($this->objectTypes as $objectType) {
            if (!is_string($objectType) || preg_match('/^[a-z0-9_-]{1,20}$/', $objectType) !== 1) {
                throw new \InvalidArgumentException(sprintf(
                    "TaxonomyConfig '%s': objectTypes must hold post type names, got '%s'.",
                    esc_html($this->name),
                    esc_html(is_string($objectType) ? $objectType : get_debug_type($objectType))
                ));
            }
        }

        if (!array_is_list($this->objectTypes)) {
            throw new \InvalidArgumentException(sprintf(
                "TaxonomyConfig '%s': objectTypes must be a list.",
                esc_html($this->name)
            ));
        }
    }

    /**
     * Build from an array whose keys are the constructor's parameter names.
     *
     * Unknown keys and wrong types throw.
     *
     * @param array<array-key, mixed> $values
     * @param string $label Included in error messages, e.g. the file name.
     * @return self
     */
    public static function fromArray(array $values, string $label = ''): self
    {
        $prefix = $label !== '' ? "TaxonomyConfig '{$label}'" : 'TaxonomyConfig';

        Fields::assert($values, self::FIELDS, $prefix);
        Fields::assertRequired($values, ['name'], $prefix);

        return new self(...$values);
    }
}
