# Hooks

TORO applies a small number of WordPress filters so that code outside the theme's configuration — a
custom-field plugin, an mu-plugin, a theme's `functions.php` — can supply values that live in post
data, and extend the output.

These complement, and do not replace, the configuration objects. The dividing line is:

> **Config describes what the site *is*; a hook describes what the site *does*.**
>
> If it is fixed per site, per path, per archive or per taxonomy, and whoever writes the theme would
> set it, it belongs in `SiteConfig`, `PageConfig` or `ArchiveConfig`. If it depends on content that
> editors change — a custom field on a post — or an unrelated plugin wants to add to the output, it
> is a hook.

## Compatibility

Hook names are permanent. They are never renamed or removed once released, because a site's
callback would silently stop running after an update.

Hooks are also purely additive: with no callbacks registered, the plugin behaves exactly as it would
without them.

## Rules every filter follows

- **Filters pass only scalars and arrays.** The configuration objects are `readonly`, and there is
  deliberately no filter on them — see [What is *not* a hook](#what-is-not-a-hook).
- **A callback returning the wrong type is ignored.** The value falls back to what it was before
  the filter ran, so a faulty callback cannot take the site's `<head>` down. Where a filter returns
  an array, invalid entries are dropped individually.

---

## Filters

### `toro_post_values`

Supplies per-post SEO values, typically read from custom fields. Values returned here take
precedence over every configuration object.

```php
apply_filters( 'toro_post_values', array $values, WP_Post $post );
```

`$values` starts empty; return the keys you want to set.

| Key | Type | Description |
|---|---|---|
| `title` | `string` | Title part. |
| `description` | `string` | Meta description and `og:description`. |
| `og_image` | `string` | `og:image`. Absolute http(s) URL, or root-relative path. |
| `noindex` | `bool` | `true` adds `noindex`. |
| `nofollow` | `bool` | `true` adds `nofollow`. |
| `canonical` | `string` | Canonical URL. Absolute http(s) URL, or root-relative path. |

Empty values (`''`, `null`, `false`) are ignored, so lower levels apply — which means `false` cannot
switch off a `noindex` set by a `PageConfig`. Unknown keys, wrongly-typed values (`'noindex' =>
'yes'`, `'title' => 123`) and invalid URLs are ignored one by one. A non-array return value is
ignored altogether.

It runs for the queried post on singular requests, a static front page and the posts page. It also
runs, outside the main query, for `get_canonical_url`, for each **registered** page when a sitemap is
built, and on the admin page. Keep it cheap and free of side effects.

```php
add_filter( 'toro_post_values', function ( $values, $post ) {
    $description = get_post_meta( $post->ID, 'seo_description', true );
    if ( is_string( $description ) ) {
        $values['description'] = $description;
    }

    $values['noindex'] = (bool) get_post_meta( $post->ID, 'seo_noindex', true );

    return $values;
}, 10, 2 );
```

Reading values Rank Math left behind, while migrating:

```php
add_filter( 'toro_post_values', function ( $values, $post ) {
    $robots = (array) get_post_meta( $post->ID, 'rank_math_robots', true );

    return array_merge( $values, [
        // Rank Math stores templates such as "%title% %sep% %sitename%" here.
        // Only carry over values without variables.
        'description' => (string) get_post_meta( $post->ID, 'rank_math_description', true ),
        'canonical'   => (string) get_post_meta( $post->ID, 'rank_math_canonical_url', true ),
        'noindex'     => in_array( 'noindex', $robots, true ),
        'nofollow'    => in_array( 'nofollow', $robots, true ),
    ] );
}, 10, 2 );
```

### `toro_og_tags`

Filters the Open Graph and Twitter Card tags just before they are printed.

```php
apply_filters( 'toro_og_tags', array $tags, Context $context );
```

`$tags` is keyed by property. Keys starting with `twitter:` are printed with `name="…"`, everything
else with `property="…"`. A value may be a string, or a list of strings to print the tag once per
entry. Empty and non-scalar values are skipped. A non-array return value keeps the defaults.

```php
add_filter( 'toro_og_tags', function ( $tags, $context ) {
    $tags['fb:app_id'] = '1234567890';

    if ( $context->type === \ToroPlugin\Models\Context::TYPE_SINGULAR ) {
        $tags['article:published_time'] = get_the_date( 'c', $context->post() );
    }

    return $tags;
}, 10, 2 );
```

`Context` is read-only; use `$context->type` (one of the `Context::TYPE_*` constants),
`$context->path`, `$context->post()` and so on to decide what to add.

### `toro_json_ld`

Filters the nodes of the JSON-LD `@graph`.

```php
apply_filters( 'toro_json_ld', array $graph, Context $context );
```

`$graph` is a list of nodes: `WebSite` always, `Organization` when `SiteConfig::$organization` is
set, and `BreadcrumbList` for pages of a hierarchical post type and for archives. Append, alter or
remove nodes. Return `[]` to print no JSON-LD at all; a non-array return value keeps the defaults.

```php
add_filter( 'toro_json_ld', function ( $graph, $context ) {
    if ( $context->path === 'access' ) {
        $graph[] = [
            '@type'   => 'LocalBusiness',
            'name'    => '株式会社サンプル 本社',
            'address' => [
                '@type'           => 'PostalAddress',
                'addressRegion'   => '東京都',
                'addressLocality' => '千代田区',
            ],
        ];
    }

    return $graph;
}, 10, 2 );
```

The graph is encoded with `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG`, so a
value containing `</script>` cannot end the script element early.

### `toro_sitemap_excluded_post_ids`

Filters the post IDs removed from a post type's sitemap.

```php
apply_filters( 'toro_sitemap_excluded_post_ids', int[] $ids, string $postType );
```

`$ids` already holds the registered pages that resolve to `noindex`. Only registered pages are
checked automatically — resolving every post on each sitemap request would be too expensive — so
add others here. Non-positive and non-integer entries are dropped; a non-array return value keeps
the defaults.

```php
add_filter( 'toro_sitemap_excluded_post_ids', function ( $ids, $post_type ) {
    if ( 'news' !== $post_type ) {
        return $ids;
    }

    return array_merge( $ids, get_posts( [
        'post_type'  => 'news',
        'fields'     => 'ids',
        'nopaging'   => true,
        'meta_key'   => 'seo_noindex',
        'meta_value' => '1',
    ] ) );
}, 10, 2 );
```

### `toro_admin_page_capability`

Filters the capability required to view **Tools → SEO (TORO)**.

```php
apply_filters( 'toro_admin_page_capability', string $capability );
```

Defaults to `manage_options`. A non-string or empty return value falls back to that default. The
filter governs both the menu entry and the page itself.

```php
add_filter( 'toro_admin_page_capability', fn () => 'edit_pages' );
```

---

## Core filters TORO uses

TORO integrates through core's own hooks rather than replacing its output, so these still work for
the theme and other plugins, and run alongside TORO's callbacks (at priority 10 unless noted):

| Core hook | What TORO does |
|---|---|
| `document_title_parts` | Replaces `title` (and `site`, when `SiteConfig::$siteName` is set). |
| `document_title_separator` | Returns `SiteConfig::$separator`. |
| `get_canonical_url` | Returns the configured canonical for a post, if any. |
| `wp_robots` | Adds `noindex` / `nofollow`. |
| `wp_head` (priority 1) | Prints description, non-singular canonical, OGP, Twitter Card, JSON-LD. |
| `wp_sitemaps_*` | See [SitemapConfig](../settings/sitemapconfig.md). |

---

## What is *not* a hook

Deliberate omissions, so that the boundary stays predictable:

| | Why |
|---|---|
| Filtering `SiteConfig` / `PageConfig` / `ArchiveConfig` | The configuration already lives in your own theme code; a filter would let unrelated code rewrite it invisibly. Change the code. |
| Filtering the final title string | Use core's `document_title_parts` / `pre_get_document_title`, which TORO already cooperates with. |
| Filtering robots directives | Use core's `wp_robots`. |
| Turning off the conflict check | Two SEO plugins printing two canonicals is never what anyone wants. Deactivate the other one. |

## See also

- [How values are resolved](../index.md#how-values-are-resolved)
- [PageConfig](../settings/pageconfig.md) — the per-path configuration surface.
