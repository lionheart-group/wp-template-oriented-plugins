# PostsConfig

The archive of WordPress's built-in **posts** (`post`), and the permalink structure the theme expects for them. Register once with
`Registry::registerPosts()`. TOBIUO does not register `post` again — core's post type stays as it is.
The archive comes from the theme; the permalink structure stays core's setting (Settings →
Permalinks), and the theme only states which one it expects.

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
        archive: 'news', // TOBIUO builds the archive: https://example.com/news/

        // Optional: the post URL structure the theme expects, below the archive (/news/%postname%/).
        // TOBIUO does not apply it: set it on Settings → Permalinks. The Tools page compares the two.
        permalink: new PermalinkConfig(structure: '/%postname%/'),
    ));
});
```

What the site then has, with Settings → Permalinks set to `/news/%postname%/`:

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
| `permalink` | `?PermalinkConfig` | No | `null` | Path of a post **below the archive** that the theme expects. `null`: the theme expects no particular structure. |

### permalink

The structure the site's permalink structure (Settings → Permalinks) should have, as
`'/' . archive . structure`: `archive: 'news'` with `/%postname%/` expects `/news/%postname%/`.
Without an archive the structure is expected as written. TOBIUO never writes or overrides the
setting: set it on Settings → Permalinks (Custom Structure) on every environment.
**Tools → Content Structure (TOBIUO)** compares the two and warns when they differ.

Since this is core's own structure, only the tags core supports in it are allowed: `%postname%`
`%post_id%` `%year%` `%monthnum%` `%day%` `%hour%` `%minute%` `%second%` `%author%` `%category%`.
`dateArchive`, `authorArchive` and `dateFront` must keep their defaults: core already provides date
and author archives for posts, below the same front (with `/date` in front of the year when the
structure starts with a number, as core decides).

## How it is applied

- The permalink structure is not touched: post URLs, and the front below which core puts the date,
  author, category and tag archives, come from Settings → Permalinks as usual.
  **Tools → Content Structure (TOBIUO)** shows the stored structure and, when `permalink` is set,
  "Matches the theme" or "Differs from the theme" with the expected structure and a link to
  Settings → Permalinks.
- Core registers `post` on `init` 0, before the theme's config exists, so the archive is set on the
  registered post type object (`has_archive`), and the archive rules are added — core never adds
  them for `post`, which it registers without rewrite rules.
- **Open Settings → Permalinks** once after changing the config, as for any structure change.
  **Tools → Content Structure (TOBIUO)** shows the archive, the structure in use and whether the
  archive rules are stored.

## Notes

- The front of the structure (`/news/`) also prefixes every custom post type and taxonomy whose `rewrite` has
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
