# PostsConfig

The archive and permalink of WordPress's built-in **posts** (`post`). Register once with
`Registry::registerPosts()`. TOBIUO does not register `post` again — core's post type stays as it is;
only its archive and its permalink structure come from the theme.

## Usage

```php
use TobiuoPlugin\Helpers\Registry;
use TobiuoPlugin\Structure\PermalinkConfig;
use TobiuoPlugin\Structure\PostsConfig;

add_action('init', function () {
    if (!class_exists('TobiuoPlugin\Helpers\Registry')) {
        return;
    }

    Registry::registerPosts(new PostsConfig(
        archive: 'news',                                           // https://example.com/news/
        permalink: new PermalinkConfig(structure: '/%postname%/'), // https://example.com/news/my-post/
    ));
});
```

What the site then has:

| URL | What |
|---|---|
| `/news/` | The posts archive (`is_post_type_archive('post')`), with `/news/page/2/` and `/news/feed/` |
| `/news/my-post/` | A post |
| `/news/2026/`, `/news/2026/05/` | Core's date archives |
| `/news/author/jane/` | Core's author archive |
| `/news/category/info/`, `/news/tag/event/` | Core's category and tag archives (unless Settings → Permalinks sets a category or tag base) |

`get_post_type_archive_link('post')` returns `https://example.com/news/`.

### From a definition file

```php
// settings/posts.php
return [
    'archive'   => 'news',
    'permalink' => ['structure' => '/%postname%/'],
];
```

```php
\TobiuoPlugin\Helpers\Registry::registerPosts(
    \TobiuoPlugin\Structure\PostsConfig::fromArray(require get_theme_file_path('settings/posts.php'), 'posts.php')
);
```

Unknown keys (`'has_archive'`) and wrong types (`'archive' => true`) throw `InvalidArgumentException`,
here and inside `permalink`.

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `archive` | `?string` | No | `null` | Path of the posts archive: lowercase letters, digits and `-`, segments joined by `/`, no slash at either end (`news`, `info/news`). `null`: no archive of their own (core's posts page / home page, as without TOBIUO). |
| `permalink` | `?PermalinkConfig` | No | `null` | Path of a post **below the archive**. `null` leaves the structure to Settings → Permalinks. |

### permalink

The structure becomes the site's permalink structure — what Settings → Permalinks would otherwise
store — as `'/' . archive . structure`: `archive: 'news'` with `/%postname%/` gives
`/news/%postname%/`. Without an archive the structure is used as written.

Since this is core's own structure, only the tags core supports in it are allowed: `%postname%`
`%post_id%` `%year%` `%monthnum%` `%day%` `%hour%` `%minute%` `%second%` `%author%` `%category%`.
`dateArchive`, `authorArchive` and `dateFront` must keep their defaults: core already provides date
and author archives for posts, below the same front (with `/date` in front of the year when the
structure starts with a number, as core decides).

## How it is applied

- `pre_option_permalink_structure` returns the structure, so `get_option('permalink_structure')`,
  Settings → Permalinks and everything in core see it. **Settings → Permalinks** gets a section
  ("Permalinks set by the theme (TOBIUO)") saying the structure comes from the theme; choosing
  another one there has no effect.
- `WP_Rewrite` is built before the theme loads, from the stored option. At the hand-over (`init` 99)
  TOBIUO re-reads the structure into it — keeping the endpoints and rules added since — and moves
  what was already built on the old front to the new one: other post types' and taxonomies'
  permastructs and core's archive rules for post types with `with_front`.
- Core registers `post` on `init` 0, before the theme's config exists, so the archive is set on the
  registered post type object (`has_archive`), and the archive rules are added — core never adds
  them for `post`, which it registers without rewrite rules.
- **Open Settings → Permalinks** once after changing the config, as for any structure change.
  **Tools → Content Structure (TOBIUO)** shows the archive, the structure in use and whether the
  archive rules are stored.

## Notes

- The front (`/news/`) also prefixes every custom post type and taxonomy whose `rewrite` has
  `with_front => true` (the default) — `/news/event/my-event/`. Set `'with_front' => false` for those
  that should not sit below the posts.
- A category or tag base set on Settings → Permalinks (`category_base`, `tag_base`) is a separate,
  stored setting and is not changed. With one set, categories and tags keep that URL.
- `Registry::registerPosts()` may be called once; a second call calls `wp_die()`.
- `Registry::registerPostType()` with `post`, `page` or any other post type built into WordPress
  calls `wp_die()` — registering it again would replace core's definition. Use this class for `post`.
- `tobiuo_get_year_link('post', …)` and the other template functions return core's own links
  (`get_year_link()`, `get_author_posts_url()`, …) for posts.
- While Custom Post Type Permalinks is active, none of this is applied.
- Code that read `$wp_rewrite->front` before `init` 99 (to build its own rules) saw the stored
  structure's front.
