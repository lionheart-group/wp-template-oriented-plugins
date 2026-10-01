<?php

namespace TobiuoPlugin\Structure;

use TobiuoPlugin\Helpers\Fields;

/**
 * Permalink configuration class.
 *
 * The URL structure of one post type's posts, below its rewrite slug, and
 * the archives that go with it. Passed to `PostTypeConfig`.
 *
 * <code>
 * new PermalinkConfig(structure: '/%case_category%/%postname%/', dateArchive: true);
 * // https://example.com/case/consulting/strategy/my-case/
 * // https://example.com/case/2024/05/
 * </code>
 *
 * @package TobiuoPlugin\Structure
 */
class PermalinkConfig
{
    /**
     * Tags core fills in for every post, besides the post type's taxonomies.
     *
     * @var string[]
     */
    public const CORE_TAGS = [
        '%postname%',
        '%post_id%',
        '%year%',
        '%monthnum%',
        '%day%',
        '%hour%',
        '%minute%',
        '%second%',
        '%author%',
    ];

    /**
     * Tags that take their value from the post date.
     *
     * @var string[]
     */
    public const DATE_TAGS = ['%year%', '%monthnum%', '%day%', '%hour%', '%minute%', '%second%'];

    /**
     * Keys accepted by fromArray(), mapped to their expected type.
     *
     * @var array<string, string>
     */
    private const FIELDS = [
        'structure'     => 'string',
        'dateArchive'   => 'bool',
        'authorArchive' => 'bool',
        'dateFront'     => '?string',
    ];

    public function __construct(
        /**
         * Path of a post below the post type's rewrite slug, e.g.
         * `/%case_category%/%postname%/`.
         *
         * Starts with `/`; a trailing `/` gives the links a trailing slash.
         * Must contain `%postname%` or `%post_id%`. Allowed tags: those in
         * CORE_TAGS and `%{taxonomy}%` for a taxonomy attached to the post
         * type, each at most once.
         *
         * @var string
         */
        public readonly string $structure,

        /**
         * Add year, month and day archives of this post type below its
         * archive URL (`/case/2024/`, `/case/2024/05/`, …). Needs `has_archive`.
         *
         * @var bool
         */
        public readonly bool $dateArchive = false,

        /**
         * Add per-author archives of this post type below its archive URL
         * (`/case/author/jane/`). Needs `has_archive`.
         *
         * @var bool
         */
        public readonly bool $authorArchive = false,

        /**
         * Path segment between the archive URL and the year, e.g. `/date`.
         *
         * Null picks one: `/date` when a post URL could look like a date
         * archive (see Rewrite::dateFront()), otherwise none. `''` forces none.
         *
         * @var ?string
         */
        public readonly ?string $dateFront = null,
    )
    {
        if (!str_starts_with($this->structure, '/')) {
            throw new \InvalidArgumentException(sprintf(
                "PermalinkConfig: structure '%s' must start with '/'.",
                esc_html($this->structure)
            ));
        }

        if (preg_match('#//|[?\#\s]#', $this->structure) === 1) {
            throw new \InvalidArgumentException(sprintf(
                "PermalinkConfig: structure '%s' must not contain '//', '?', '#' or whitespace.",
                esc_html($this->structure)
            ));
        }

        preg_match_all('/%[^%\/]*%/', $this->structure, $matches);
        $tags = $matches[0];

        // A lone '%' (or a '%' pair spanning a '/') is left over once the tags are taken out
        if (str_contains((string) preg_replace('/%[^%\/]*%/', '', $this->structure), '%')) {
            throw new \InvalidArgumentException(sprintf(
                "PermalinkConfig: structure '%s' contains a '%%' that is not part of a tag.",
                esc_html($this->structure)
            ));
        }

        foreach ($tags as $tag) {
            if (!in_array($tag, self::CORE_TAGS, true) && preg_match('/^%[a-z0-9_-]{1,32}%$/', $tag) !== 1) {
                throw new \InvalidArgumentException(sprintf(
                    "PermalinkConfig: structure '%s' contains '%s', which is neither one of %s nor a taxonomy name.",
                    esc_html($this->structure),
                    esc_html($tag),
                    esc_html(implode(' ', self::CORE_TAGS))
                ));
            }
        }

        $duplicates = array_unique(array_diff_assoc($tags, array_unique($tags)));
        if ($duplicates !== []) {
            throw new \InvalidArgumentException(sprintf(
                "PermalinkConfig: structure '%s' contains '%s' more than once.",
                esc_html($this->structure),
                esc_html(implode("', '", $duplicates))
            ));
        }

        if (!in_array('%postname%', $tags, true) && !in_array('%post_id%', $tags, true)) {
            throw new \InvalidArgumentException(sprintf(
                "PermalinkConfig: structure '%s' must contain %%postname%% or %%post_id%%.",
                esc_html($this->structure)
            ));
        }

        if ($this->dateFront !== null && $this->dateFront !== '' && preg_match('#^(/[^/%?\#\s]+)+$#', $this->dateFront) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                "PermalinkConfig: dateFront '%s' must be '' or start with '/' and not end with one, e.g. '/date'.",
                esc_html($this->dateFront)
            ));
        }
    }

    /**
     * Build from an array whose keys are the constructor's parameter names.
     *
     * Unknown keys throw rather than being ignored, so a typo such as
     * `'date_archive' => true` fails on `init` instead of silently leaving
     * the archives out.
     *
     * @param array<array-key, mixed> $values
     * @param string $label Included in error messages, e.g. the post type.
     * @return self
     */
    public static function fromArray(array $values, string $label = ''): self
    {
        $prefix = $label !== '' ? "PermalinkConfig '{$label}'" : 'PermalinkConfig';

        Fields::assert($values, self::FIELDS, $prefix);
        Fields::assertRequired($values, ['structure'], $prefix);

        return new self(...$values);
    }

    /**
     * Taxonomy names used as tags, in the order they appear.
     *
     * @return string[]
     */
    public function taxonomyTags(): array
    {
        preg_match_all('/%([a-z0-9_-]+)%/', $this->structure, $matches);

        return array_values(array_filter(
            $matches[1],
            fn (string $name) => !in_array('%' . $name . '%', self::CORE_TAGS, true)
        ));
    }

    /**
     * Whether a post's URL can differ from its permalink and still find it.
     *
     * The post is looked up by `%postname%` / `%post_id%`; taxonomy tags are
     * not part of the query, and date and author tags are but only narrow it,
     * so these are the requests the canonical redirect corrects.
     *
     * @return bool
     */
    public function hasContextTags(): bool
    {
        if ($this->taxonomyTags() !== [] || str_contains($this->structure, '%author%')) {
            return true;
        }

        foreach (self::DATE_TAGS as $tag) {
            if (str_contains($this->structure, $tag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether links end with a slash, as the structure does.
     *
     * @return bool
     */
    public function hasTrailingSlash(): bool
    {
        return str_ends_with($this->structure, '/');
    }
}
