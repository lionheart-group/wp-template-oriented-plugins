<?php

namespace TobiuoPlugin\Structure;

use TobiuoPlugin\Helpers\Fields;

/**
 * Posts configuration class.
 *
 * The archive and permalink of core's built-in `post` post type, which
 * TOBIUO does not register again. Register with `Registry::registerPosts()`.
 *
 * <code>
 * new PostsConfig(
 *     archive: 'news',                                         // https://example.com/news/
 *     permalink: new PermalinkConfig(structure: '/%postname%/'), // https://example.com/news/my-post/
 * );
 * </code>
 *
 * The permalink is the structure the theme expects the site's permalink
 * structure (Settings → Permalinks) to be — `'/' . archive . structure`.
 * TOBIUO does not set it; the admin page warns when the two differ.
 *
 * @package TobiuoPlugin\Structure
 */
class PostsConfig
{
    /**
     * Taxonomy tags core's permalink structure understands, besides PermalinkConfig::CORE_TAGS.
     *
     * @var string[]
     */
    public const TAXONOMY_TAGS = ['category'];

    /**
     * Keys accepted by fromArray(), mapped to their expected type.
     *
     * @var array<string, string>
     */
    private const FIELDS = [
        'archive'   => '?string',
        'permalink' => 'mixed',
    ];

    public function __construct(
        /**
         * Path of the posts archive, e.g. `news` or `info/news`: lowercase
         * letters, digits and `-`, segments joined by `/`, no slash at
         * either end. Null leaves posts without an archive of their own.
         *
         * @var ?string
         */
        public readonly ?string $archive = null,

        /**
         * Path of a post below the archive that the theme expects, e.g.
         * `/%postname%/`. Null: no particular structure is expected.
         *
         * Only the tags core's permalink structure supports:
         * PermalinkConfig::CORE_TAGS and `%category%`. `dateArchive` and
         * `authorArchive` must stay false — core already provides those
         * archives for posts.
         *
         * @var ?PermalinkConfig
         */
        public readonly ?PermalinkConfig $permalink = null,
    )
    {
        if ($this->archive !== null && preg_match('#^[a-z0-9-]+(/[a-z0-9-]+)*$#', $this->archive) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                "PostsConfig: archive '%s' must be lowercase letters, digits and '-', in segments joined by '/', with no slash at either end (e.g. 'news').",
                esc_html($this->archive)
            ));
        }

        if ($this->permalink === null) {
            return;
        }

        $unsupported = array_diff($this->permalink->taxonomyTags(), self::TAXONOMY_TAGS);
        if ($unsupported !== []) {
            throw new \InvalidArgumentException(sprintf(
                "PostsConfig: structure '%s' contains '%s'. Posts support %s and %%category%% only.",
                esc_html($this->permalink->structure),
                esc_html('%' . implode("%', '%", $unsupported) . '%'),
                esc_html(implode(' ', PermalinkConfig::CORE_TAGS))
            ));
        }

        foreach (['dateArchive', 'authorArchive'] as $property) {
            if ($this->permalink->$property) {
                throw new \InvalidArgumentException(sprintf(
                    'PostsConfig: %s must be false. Core already provides date and author archives for posts, below the archive.',
                    esc_html($property)
                ));
            }
        }

        if ($this->permalink->dateFront !== null) {
            throw new \InvalidArgumentException(
                'PostsConfig: dateFront must be null. Core decides where the date archives of posts go.'
            );
        }
    }

    /**
     * Build from an array whose keys are the constructor's parameter names.
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
        $prefix = $label !== '' ? "PostsConfig '{$label}'" : 'PostsConfig';

        Fields::assert($values, self::FIELDS, $prefix);

        $permalink = $values['permalink'] ?? null;
        if (is_array($permalink)) {
            $permalink = PermalinkConfig::fromArray($permalink, $label !== '' ? $label : 'post');
        }

        if ($permalink !== null && !$permalink instanceof PermalinkConfig) {
            throw new \InvalidArgumentException(sprintf(
                '%s: "permalink" must be a PermalinkConfig, an array or null, got %s.',
                esc_html($prefix),
                esc_html(get_debug_type($permalink))
            ));
        }

        return new self(
            archive: $values['archive'] ?? null,
            permalink: $permalink,
        );
    }

    /**
     * The site-wide permalink structure this config expects, e.g. `/news/%postname%/`.
     *
     * @return ?string Null when no particular structure is expected.
     */
    public function permalinkStructure(): ?string
    {
        if ($this->permalink === null) {
            return null;
        }

        return ($this->archive !== null ? '/' . $this->archive : '') . $this->permalink->structure;
    }
}
