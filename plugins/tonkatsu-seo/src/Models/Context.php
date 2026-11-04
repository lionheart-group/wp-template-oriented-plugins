<?php

namespace TonkatsuPlugin\Models;

use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Helpers\Url;

/**
 * What is being rendered: the request type, the queried object, and the
 * URL path used to look up a registered PageConfig.
 *
 * Everything WordPress-dependent happens in the static factories
 * (fromQuery(), forPost(), forPath()). The instance itself is a plain value,
 * so the Resolver can be unit-tested with a hand-built Context.
 */
class Context
{
    /**
     * postTypeArchiveCrumb() results for this request, keyed by post type
     * and home URL. Null results are kept too.
     *
     * @var array<string, ?array{name: string, url: string}>
     */
    private static array $archiveCrumbs = [];

    public const TYPE_FRONT = 'front';
    public const TYPE_HOME = 'home';
    public const TYPE_SINGULAR = 'singular';
    public const TYPE_POST_TYPE_ARCHIVE = 'post_type_archive';
    public const TYPE_TAXONOMY = 'taxonomy';
    public const TYPE_AUTHOR = 'author';
    public const TYPE_DATE = 'date';
    public const TYPE_SEARCH = 'search';
    public const TYPE_404 = '404';
    public const TYPE_OTHER = 'other';

    /**
     * @var string[]
     */
    public const TYPES = [
        self::TYPE_FRONT,
        self::TYPE_HOME,
        self::TYPE_SINGULAR,
        self::TYPE_POST_TYPE_ARCHIVE,
        self::TYPE_TAXONOMY,
        self::TYPE_AUTHOR,
        self::TYPE_DATE,
        self::TYPE_SEARCH,
        self::TYPE_404,
        self::TYPE_OTHER,
    ];

    public function __construct(
        /**
         * One of the TYPE_* constants.
         *
         * `front` is the front page whether it shows posts or a static page;
         * `home` is the posts page when a static front page is set.
         *
         * @var string
         */
        public readonly string $type,

        /**
         * Path relative to the home URL, normalized like Seo::normalizePath().
         *
         * @var string
         */
        public readonly string $path = '',

        /**
         * This request's own URL (permalink, archive link, …), including the
         * page number on paginated archives. Null where there is no single
         * canonical URL (search, 404, date archives).
         *
         * @var ?string
         */
        public readonly ?string $url = null,

        /**
         * The queried object.
         *
         * @var \WP_Post|\WP_Term|\WP_User|\WP_Post_Type|null
         */
        public readonly ?object $object = null,

        /**
         * Post type of a singular or post type archive.
         *
         * @var ?string
         */
        public readonly ?string $postType = null,

        /**
         * Taxonomy of a term archive.
         *
         * @var ?string
         */
        public readonly ?string $taxonomy = null,

        /**
         * The object's own name — post title, term name, archive label — in
         * plain text. Used where no title is configured (og:title, the last
         * breadcrumb).
         *
         * @var ?string
         */
        public readonly ?string $title = null,

        /**
         * A description WordPress already holds for the object: a post's
         * hand-written excerpt, or a term's description. Plain text.
         *
         * @var ?string
         */
        public readonly ?string $description = null,

        /**
         * The object's own image, i.e. a post's featured image URL.
         *
         * @var ?string
         */
        public readonly ?string $image = null,

        /**
         * Breadcrumb trail below the home page, current item last.
         *
         * @var list<array{name: string, url: string}>
         */
        public readonly array $breadcrumbs = [],

        /**
         * Page number of a paginated archive.
         *
         * @var int
         */
        public readonly int $paged = 1,
    )
    {
        if (!in_array($this->type, self::TYPES, true)) {
            throw new \InvalidArgumentException(sprintf("Context: unknown type '%s'.", esc_html($this->type)));
        }
    }

    /**
     * Whether WordPress treats this request as singular (is_singular()).
     *
     * A static front page is singular; the posts page is not.
     *
     * @return bool
     */
    public function isSingular(): bool
    {
        return $this->type === self::TYPE_SINGULAR
            || ($this->type === self::TYPE_FRONT && $this->object instanceof \WP_Post);
    }

    /**
     * @return bool
     */
    public function isFront(): bool
    {
        return $this->type === self::TYPE_FRONT;
    }

    /**
     * The queried post, for singulars, a static front page and the posts page.
     *
     * @return ?\WP_Post
     */
    public function post(): ?\WP_Post
    {
        return $this->object instanceof \WP_Post ? $this->object : null;
    }

    /**
     * Build the context of the current main query.
     *
     * Call no earlier than `wp` — before that, the conditional tags all
     * return false.
     *
     * @return self
     */
    public static function fromQuery(): self
    {
        $home = home_url('/');
        $paged = max(1, (int) get_query_var('paged'));
        $object = get_queried_object();

        if (is_front_page() || is_home()) {
            if ($object instanceof \WP_Post) {
                return self::forPost($object, $paged);
            }

            return new self(
                type: is_front_page() ? self::TYPE_FRONT : self::TYPE_HOME,
                path: '',
                url: self::pagedUrl($home, $paged),
                paged: $paged,
            );
        }

        if (is_singular() && $object instanceof \WP_Post) {
            return self::forPost($object);
        }

        if (is_post_type_archive()) {
            $postType = get_query_var('post_type');
            if (is_array($postType)) {
                $postType = reset($postType);
            }
            $postType = is_string($postType) ? $postType : '';

            $link = get_post_type_archive_link($postType);
            $url = is_string($link) ? $link : null;
            $title = self::plainText(post_type_archive_title('', false));
            $postTypeObject = get_post_type_object($postType);

            return new self(
                type: self::TYPE_POST_TYPE_ARCHIVE,
                path: $url !== null ? self::relativePath($url, $home) : self::requestPath($home),
                url: self::pagedUrl($url, $paged),
                object: $postTypeObject,
                postType: $postType,
                title: $title,
                breadcrumbs: ($url !== null && $title !== null) ? [['name' => $title, 'url' => $url]] : [],
                paged: $paged,
            );
        }

        if ((is_category() || is_tag() || is_tax()) && $object instanceof \WP_Term) {
            $link = get_term_link($object);
            $url = is_string($link) ? $link : null;

            return new self(
                type: self::TYPE_TAXONOMY,
                path: $url !== null ? self::relativePath($url, $home) : self::requestPath($home),
                url: self::pagedUrl($url, $paged),
                object: $object,
                taxonomy: $object->taxonomy,
                title: self::plainText($object->name),
                description: self::plainText(term_description($object->term_id)),
                breadcrumbs: self::withArchiveCrumb(
                    self::termBreadcrumbs($object),
                    self::taxonomyArchiveCrumb($object->taxonomy, $home)
                ),
                paged: $paged,
            );
        }

        if (is_author() && $object instanceof \WP_User) {
            $url = get_author_posts_url($object->ID);

            return new self(
                type: self::TYPE_AUTHOR,
                path: self::relativePath($url, $home),
                url: self::pagedUrl($url, $paged),
                object: $object,
                title: self::plainText($object->display_name),
                paged: $paged,
            );
        }

        $type = match (true) {
            is_date()   => self::TYPE_DATE,
            is_search() => self::TYPE_SEARCH,
            is_404()    => self::TYPE_404,
            default     => self::TYPE_OTHER,
        };

        return new self(
            type: $type,
            path: self::requestPath($home),
            paged: $paged,
        );
    }

    /**
     * Build the context of a single post, independent of the main query.
     *
     * Used for the main query's own post, and wherever a post's resolved
     * values are needed outside of it (`get_canonical_url`, the sitemap, the
     * admin page).
     *
     * @param \WP_Post $post
     * @param int $paged Only meaningful for the posts page.
     * @return self
     */
    public static function forPost(\WP_Post $post, int $paged = 1): self
    {
        $home = home_url('/');
        $permalink = get_permalink($post);
        $url = is_string($permalink) ? $permalink : null;

        $type = self::TYPE_SINGULAR;
        if (get_option('show_on_front') === 'page') {
            if ((int) get_option('page_on_front') === $post->ID) {
                $type = self::TYPE_FRONT;
            } elseif ((int) get_option('page_for_posts') === $post->ID) {
                $type = self::TYPE_HOME;
            }
        }

        $title = self::plainText(get_the_title($post));

        $description = null;
        if ($post->post_password === '' && has_excerpt($post)) {
            $description = self::plainText($post->post_excerpt);
        }

        $image = get_the_post_thumbnail_url($post, 'full');

        $breadcrumbs = [];
        if ($type === self::TYPE_HOME && $url !== null && $title !== null) {
            $breadcrumbs = [['name' => $title, 'url' => $url]];
        } elseif ($type === self::TYPE_SINGULAR) {
            // Ancestors for hierarchical post types, behind the post type's
            // archive when it has one (Home > News > Post).
            $breadcrumbs = self::withArchiveCrumb(
                is_post_type_hierarchical($post->post_type)
                    ? self::postBreadcrumbs($post, $url, $title)
                    : (($url !== null && $title !== null) ? [['name' => $title, 'url' => $url]] : []),
                self::postTypeArchiveCrumb($post->post_type, $home)
            );
        }

        return new self(
            type: $type,
            path: ($type === self::TYPE_FRONT || $url === null) ? '' : self::relativePath($url, $home),
            url: $type === self::TYPE_HOME ? self::pagedUrl($url, $paged) : $url,
            object: $post,
            postType: $post->post_type,
            title: $title,
            description: $description,
            image: is_string($image) && $image !== '' ? $image : null,
            breadcrumbs: $breadcrumbs,
            paged: $paged,
        );
    }

    /**
     * Build the context a registered path would have when visited.
     *
     * Used by the admin page, which is not rendering that path.
     *
     * @param string $path
     * @return self
     */
    public static function forPath(string $path): self
    {
        $post = self::findPost($path);
        if ($post !== null) {
            return self::forPost($post);
        }

        $path = Seo::normalizePath($path);
        if ($path === '') {
            return new self(type: self::TYPE_FRONT, path: '', url: home_url('/'));
        }

        return new self(
            type: self::TYPE_OTHER,
            path: $path,
            url: home_url(user_trailingslashit(Url::encodePath($path))),
        );
    }

    /**
     * Find the post a path belongs to, if any.
     *
     * '' is the static front page (null when the front page lists posts).
     *
     * @param string $path
     * @return ?\WP_Post
     */
    public static function findPost(string $path): ?\WP_Post
    {
        $path = Seo::normalizePath($path);

        if ($path === '') {
            if (get_option('show_on_front') !== 'page') {
                return null;
            }
            $post = get_post((int) get_option('page_on_front'));
            return $post instanceof \WP_Post ? $post : null;
        }

        $id = url_to_postid(home_url(user_trailingslashit(Url::encodePath($path))));
        if ($id > 0) {
            $post = get_post($id);
            if ($post instanceof \WP_Post) {
                return $post;
            }
        }

        $post = get_page_by_path($path, OBJECT, array_values(get_post_types(['public' => true])));

        return $post instanceof \WP_Post ? $post : null;
    }

    /**
     * The path of $url relative to $homeUrl, normalized.
     *
     * On a site installed at https://example.com/wp/, the URL
     * https://example.com/wp/company/ is the path 'company'.
     *
     * @param string $url A URL or a path such as REQUEST_URI.
     * @param string $homeUrl
     * @return string
     */
    public static function relativePath(string $url, string $homeUrl): string
    {
        $path = Seo::normalizePath($url);
        $base = Seo::normalizePath($homeUrl);

        if ($base !== '') {
            if ($path === $base) {
                return '';
            }
            if (str_starts_with($path, $base . '/')) {
                return substr($path, strlen($base) + 1);
            }
        }

        return $path;
    }

    /**
     * The sanitized REQUEST_URI (path and query string), or ''.
     *
     * @return string
     */
    public static function requestUri(): string
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';

        return is_string($uri) ? $uri : '';
    }

    /**
     * @param string $home
     * @return string
     */
    private static function requestPath(string $home): string
    {
        return self::relativePath(self::requestUri(), $home);
    }

    /**
     * @param ?string $url
     * @param int $paged
     * @return ?string
     */
    private static function pagedUrl(?string $url, int $paged): ?string
    {
        if ($url === null || $paged <= 1) {
            return $url;
        }

        return get_pagenum_link($paged);
    }

    /**
     * The crumb for a post type's archive, which goes before its posts and
     * the terms of its taxonomies.
     *
     * The title registered with Seo::registerArchive() names it, as it names
     * the archive's own last crumb; otherwise the post type's label does.
     * Null when there is no archive URL, or it is the home URL (the `post`
     * type when the front page lists the posts).
     *
     * @param string $postType
     * @param ?string $url get_post_type_archive_link()
     * @param ?string $label The post type's plain-text plural label.
     * @param string $homeUrl
     * @return ?array{name: string, url: string}
     */
    public static function archiveCrumb(string $postType, ?string $url, ?string $label, string $homeUrl): ?array
    {
        if ($url === null || $url === '' || rtrim($url, '/') === rtrim($homeUrl, '/')) {
            return null;
        }

        $title = Seo::getArchive($postType)?->title;
        $name = ($title !== null && trim($title) !== '') ? $title : $label;
        if ($name === null || trim($name) === '') {
            return null;
        }

        return ['name' => $name, 'url' => $url];
    }

    /**
     * The one post type a taxonomy belongs to, or null when it is shared by
     * several (no single archive to put before its terms) or by none.
     *
     * @param array<mixed> $objectTypes The taxonomy's `object_type`.
     * @return ?string
     */
    public static function taxonomyPostType(array $objectTypes): ?string
    {
        $objectTypes = array_values(array_unique(array_filter($objectTypes, 'is_string')));

        return count($objectTypes) === 1 ? $objectTypes[0] : null;
    }

    /**
     * $trail with $archive in front, unless the trail is empty or already
     * starts with that URL.
     *
     * @param list<array{name: string, url: string}> $trail
     * @param ?array{name: string, url: string} $archive
     * @return list<array{name: string, url: string}>
     */
    public static function withArchiveCrumb(array $trail, ?array $archive): array
    {
        if ($archive === null || $trail === [] || $trail[0]['url'] === $archive['url']) {
            return $trail;
        }

        return array_merge([$archive], $trail);
    }

    /**
     * The name an archive crumb falls back to when no title is registered
     * for the archive with Seo::registerArchive().
     *
     * For `post`, the posts page's title when one is set (its archive URL is
     * that page, which calls itself by its own title); otherwise the post
     * type's plural label.
     *
     * @param string $postType
     * @param ?string $label The post type's plain-text plural label.
     * @param ?string $postsPageTitle The posts page's plain-text title, or null when none is set.
     * @return ?string
     */
    public static function archiveLabel(string $postType, ?string $label, ?string $postsPageTitle): ?string
    {
        if ($postType === 'post' && $postsPageTitle !== null && trim($postsPageTitle) !== '') {
            return $postsPageTitle;
        }

        return $label;
    }

    /**
     * The crumb for a post type's archive, see archiveCrumb().
     *
     * Computed once per post type and request: it is the same for every post
     * of the type, and Context::forPost() runs for many posts at a time in
     * the sitemap and the admin lists. The archives it reads are registered
     * on `init`, before any Context is built.
     *
     * @param string $postType
     * @param string $home
     * @return ?array{name: string, url: string}
     */
    public static function postTypeArchiveCrumb(string $postType, string $home): ?array
    {
        $key = $postType . "\0" . $home;
        if (array_key_exists($key, self::$archiveCrumbs)) {
            return self::$archiveCrumbs[$key];
        }

        $link = get_post_type_archive_link($postType);
        $object = get_post_type_object($postType);

        $postsPageTitle = null;
        $postsPage = (int) get_option('page_for_posts');
        if ($postType === 'post' && get_option('show_on_front') === 'page' && $postsPage > 0) {
            $postsPageTitle = self::plainText(get_the_title($postsPage));
        }

        return self::$archiveCrumbs[$key] = self::archiveCrumb(
            $postType,
            is_string($link) ? $link : null,
            self::archiveLabel(
                $postType,
                $object instanceof \WP_Post_Type ? self::plainText($object->labels->name ?? null) : null,
                $postsPageTitle
            ),
            $home
        );
    }

    /**
     * @param string $taxonomy
     * @param string $home
     * @return ?array{name: string, url: string}
     */
    private static function taxonomyArchiveCrumb(string $taxonomy, string $home): ?array
    {
        $object = get_taxonomy($taxonomy);
        if (!$object instanceof \WP_Taxonomy) {
            return null;
        }

        $postType = self::taxonomyPostType((array) $object->object_type);

        return $postType !== null ? self::postTypeArchiveCrumb($postType, $home) : null;
    }

    /**
     * @param \WP_Post $post
     * @param ?string $url
     * @param ?string $title
     * @return list<array{name: string, url: string}>
     */
    private static function postBreadcrumbs(\WP_Post $post, ?string $url, ?string $title): array
    {
        $trail = [];

        foreach (array_reverse(get_post_ancestors($post)) as $ancestorId) {
            $name = self::plainText(get_the_title($ancestorId));
            $link = get_permalink($ancestorId);
            if ($name !== null && is_string($link)) {
                $trail[] = ['name' => $name, 'url' => $link];
            }
        }

        if ($url !== null && $title !== null) {
            $trail[] = ['name' => $title, 'url' => $url];
        }

        return $trail;
    }

    /**
     * @param \WP_Term $term
     * @return list<array{name: string, url: string}>
     */
    private static function termBreadcrumbs(\WP_Term $term): array
    {
        $trail = [];
        $ids = array_reverse(get_ancestors($term->term_id, $term->taxonomy, 'taxonomy'));
        $ids[] = $term->term_id;

        foreach ($ids as $id) {
            $ancestor = get_term((int) $id, $term->taxonomy);
            if (!$ancestor instanceof \WP_Term) {
                continue;
            }
            $name = self::plainText($ancestor->name);
            $link = get_term_link($ancestor);
            if ($name !== null && is_string($link)) {
                $trail[] = ['name' => $name, 'url' => $link];
            }
        }

        return $trail;
    }

    /**
     * Tags stripped, entities decoded (get_the_title() returns texturized
     * HTML, and the output is escaped again later), whitespace collapsed.
     *
     * @param mixed $text
     * @return ?string Null when nothing is left.
     */
    private static function plainText(mixed $text): ?string
    {
        if (!is_string($text)) {
            return null;
        }

        $text = html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $text !== '' ? $text : null;
    }
}
