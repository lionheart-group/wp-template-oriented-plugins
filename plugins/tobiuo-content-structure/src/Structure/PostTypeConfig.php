<?php

namespace TobiuoPlugin\Structure;

use TobiuoPlugin\Helpers\Fields;

/**
 * Post type configuration class.
 *
 * One post type, registered with `Registry::registerPostType()`. TOBIUO
 * hands it to register_post_type() on `init` (Consts::REGISTER_PRIORITY),
 * after every taxonomy.
 *
 * @package TobiuoPlugin\Structure
 */
class PostTypeConfig
{
    /**
     * Keys accepted by fromArray(), mapped to their expected type.
     *
     * @var array<string, string>
     */
    private const FIELDS = [
        'name'      => 'string',
        'args'      => 'array',
        'permalink' => 'mixed',
    ];

    public function __construct(
        /**
         * Post type key: lowercase letters, digits, `_` and `-`, at most
         * 20 characters (core's limit).
         *
         * @var string
         */
        public readonly string $name,

        /**
         * Arguments passed to register_post_type() as they are.
         *
         * @var array<string, mixed>
         */
        public readonly array $args = [],

        /**
         * Custom permalink structure. Null leaves the permalinks to core
         * (`/{rewrite slug}/{post name}/`).
         *
         * @var ?PermalinkConfig
         */
        public readonly ?PermalinkConfig $permalink = null,
    )
    {
        if (preg_match('/^[a-z0-9_-]{1,20}$/', $this->name) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                "PostTypeConfig: name '%s' must be 1 to 20 characters of lowercase letters, digits, '_' and '-'.",
                esc_html($this->name)
            ));
        }
    }

    /**
     * Build from an array whose keys are the constructor's parameter names.
     *
     * For themes that keep one definition per file:
     *
     * <code>
     * // settings/post-types/case.php
     * return [
     *     'name'      => 'case',
     *     'args'      => ['label' => '事例紹介', 'public' => true, 'has_archive' => true],
     *     'permalink' => ['structure' => '/%case_category%/%postname%/'],
     * ];
     * </code>
     *
     * `permalink` may be a PermalinkConfig, an array for
     * PermalinkConfig::fromArray(), or null. Unknown keys and wrong types
     * throw, here and in `permalink`.
     *
     * @param array<array-key, mixed> $values
     * @param string $label Included in error messages, e.g. the file name.
     * @return self
     */
    public static function fromArray(array $values, string $label = ''): self
    {
        $prefix = $label !== '' ? "PostTypeConfig '{$label}'" : 'PostTypeConfig';

        Fields::assert($values, self::FIELDS, $prefix);
        Fields::assertRequired($values, ['name'], $prefix);

        $permalink = $values['permalink'] ?? null;
        if (is_array($permalink)) {
            $permalink = PermalinkConfig::fromArray($permalink, $label !== '' ? $label : (string) $values['name']);
        }

        if ($permalink !== null && !$permalink instanceof PermalinkConfig) {
            throw new \InvalidArgumentException(sprintf(
                '%s: "permalink" must be a PermalinkConfig, an array or null, got %s.',
                esc_html($prefix),
                esc_html(get_debug_type($permalink))
            ));
        }

        return new self(
            name: $values['name'],
            args: $values['args'] ?? [],
            permalink: $permalink,
        );
    }
}
