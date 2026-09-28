<?php

namespace ToroPlugin\Structure;

use ToroPlugin\Helpers\Url;

/**
 * Page configuration class.
 *
 * SEO values for one URL path. Register with `Seo::registerPage()`.
 *
 * Empty strings are accepted and treated as "not set" when values are
 * resolved, so a definition file can carry placeholders.
 *
 * @package ToroPlugin\Structure
 */
class PageConfig
{
    /**
     * Keys accepted by fromArray(), mapped to their expected type.
     *
     * @var array<string, string>
     */
    private const FIELDS = [
        'title'       => 'string',
        'description' => 'string',
        'ogImage'     => 'string',
        'noindex'     => 'bool',
        'nofollow'    => 'bool',
        'canonical'   => 'string',
    ];

    public function __construct(
        /**
         * Title part (the site name is appended by WordPress).
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
         * og:image (absolute, or root-relative).
         *
         * @var ?string
         */
        public readonly ?string $ogImage = null,

        /**
         * Add `noindex` to the robots meta, and drop the page from the sitemap.
         *
         * @var bool
         */
        public readonly bool $noindex = false,

        /**
         * Add `nofollow` to the robots meta.
         *
         * @var bool
         */
        public readonly bool $nofollow = false,

        /**
         * Canonical URL override (absolute, or root-relative).
         *
         * @var ?string
         */
        public readonly ?string $canonical = null,
    )
    {
        if ($this->ogImage !== null && $this->ogImage !== '' && !Url::isValid($this->ogImage)) {
            throw new \InvalidArgumentException("PageConfig: ogImage '{$this->ogImage}' is not a valid URL.");
        }

        if ($this->canonical !== null && $this->canonical !== '' && !Url::isValid($this->canonical)) {
            throw new \InvalidArgumentException("PageConfig: canonical '{$this->canonical}' is not a valid URL.");
        }
    }

    /**
     * Build from an array whose keys are the constructor's parameter names.
     *
     * For themes that keep their page definitions in a data file:
     *
     * <code>
     * // pages.php
     * return [
     *     'company' => ['title' => '会社概要', 'description' => '…'],
     *     'thanks'  => ['noindex' => true],
     * ];
     * </code>
     *
     * Unknown keys throw rather than being ignored, so a typo such as
     * `'no_index' => true` fails on `init` instead of silently leaving the
     * page indexable.
     *
     * @param array<array-key, mixed> $values
     * @param string $label Included in error messages, e.g. the page path.
     * @return self
     */
    public static function fromArray(array $values, string $label = ''): self
    {
        $prefix = $label !== '' ? "PageConfig '{$label}'" : 'PageConfig';

        $unknown = array_diff(array_map('strval', array_keys($values)), array_keys(self::FIELDS));
        if ($unknown !== []) {
            throw new \InvalidArgumentException(sprintf(
                '%s: unknown key(s) "%s". Allowed keys: %s.',
                $prefix,
                implode('", "', $unknown),
                implode(', ', array_keys(self::FIELDS))
            ));
        }

        foreach ($values as $key => $value) {
            $expected = self::FIELDS[$key];
            $valid = $expected === 'bool'
                ? is_bool($value)
                : ($value === null || is_string($value));

            if (!$valid) {
                throw new \InvalidArgumentException(sprintf(
                    '%s: "%s" must be %s, got %s.',
                    $prefix,
                    $key,
                    $expected === 'bool' ? 'a bool' : 'a string or null',
                    get_debug_type($value)
                ));
            }
        }

        return new self(...$values);
    }
}
