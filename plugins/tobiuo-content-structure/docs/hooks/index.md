# Hooks

TOBIUO fires one action and applies a small number of filters, so that code outside the theme's
configuration — an mu-plugin, a custom-field plugin, the theme's `functions.php` — can react to the
registration and adjust decisions that depend on content.

These complement, and do not replace, the configuration objects. The dividing line is:

> **Config describes what the site *is*; a hook describes what the site *does*.**
>
> If it is fixed per post type or per taxonomy, and whoever writes the theme would set it, it belongs
> in `PostTypeConfig`, `TaxonomyConfig` or `PermalinkConfig`. If it depends on content that editors
> change — which of a post's terms is its main one — or an unrelated plugin wants a say, it is a hook.

## Compatibility

Hook names are permanent. They are never renamed or removed once released, because a site's
callback would silently stop running after an update.

Hooks are also purely additive: with no callbacks registered, the plugin behaves exactly as it would
without them.

## Rules every filter follows

- **Filters pass only scalars, arrays and core's own objects** (`WP_Post`, `WP_Term`). The
  configuration objects are `readonly`, and there is deliberately no filter on them — see
  [What is *not* a hook](#what-is-not-a-hook).
- **A callback returning the wrong type is ignored.** The value falls back to what it was before
  the filter ran, so a faulty callback cannot break every permalink on the site.

---

## Actions

### `tobiuo_registered`

Fires once TOBIUO has registered every taxonomy and post type, during `init` at priority 99.

```php
do_action( 'tobiuo_registered' );
```

Hook here for code that needs the post types or taxonomies to exist — they do not exist yet at the
default `init` priority.

```php
add_action( 'tobiuo_registered', function () {
    register_post_meta( 'event', 'event_date', [ 'type' => 'string', 'single' => true, 'show_in_rest' => true ] );
} );
```

TOBIUO itself replaces the permastructs and adds the date and author archive rules on this action, at
the default priority 10.

---

## Filters

### `tobiuo_post_link_term`

Filters the term whose path a post's permalink uses for a `%{taxonomy}%` tag.

```php
apply_filters( 'tobiuo_post_link_term', ?WP_Term $term, WP_Term[] $terms, string $taxonomy, WP_Post $post );
```

`$term` is TOBIUO's choice: of the post's `$terms` in `$taxonomy`, those that are not an ancestor of
another assigned term, the one with the lowest term ID. It is `null` when the post has no term
(TOBIUO then falls back to the taxonomy's default term, and then to the plain link).

Return a `WP_Term` of `$taxonomy` — one of `$terms` or any other term of the taxonomy. Its ancestors
are added in front (`parent/child`). Anything else (`null`, a slug, an ID, a term of another
taxonomy) keeps TOBIUO's choice.

```php
// Use the term marked as primary in a custom field
add_filter( 'tobiuo_post_link_term', function ( $term, $terms, $taxonomy, $post ) {
    $primary = (int) get_post_meta( $post->ID, "primary_{$taxonomy}", true );

    foreach ( $terms as $candidate ) {
        if ( $candidate->term_id === $primary ) {
            return $candidate;
        }
    }

    return $term;
}, 10, 4 );
```

The filter runs for every link, so keep it cheap — `$terms` is already loaded.

### `tobiuo_redirect_canonical`

Filters whether a post requested through a URL other than its permalink is redirected (301) to it.

```php
apply_filters( 'tobiuo_redirect_canonical', bool $redirect, WP_Post $post );
```

Defaults to `true`. Only `false` turns the redirect off; any other non-bool value keeps it on. Only
requests matched by a rewrite rule, for post types whose structure has taxonomy, date or author tags,
reach the filter.

```php
add_filter( 'tobiuo_redirect_canonical', function ( $redirect, $post ) {
    return 'case' !== $post->post_type ? $redirect : false;
}, 10, 2 );
```

### `tobiuo_admin_page_capability`

Filters the capability required to view **Tools → Content Structure (TOBIUO)**.

```php
apply_filters( 'tobiuo_admin_page_capability', string $capability );
```

Defaults to `manage_options`. A non-string or empty return value falls back to that default. The
filter governs both the menu entry and the page itself.

```php
add_filter( 'tobiuo_admin_page_capability', fn () => 'edit_pages' );
```

---

## Core hooks TOBIUO uses

TOBIUO integrates through core's own hooks rather than replacing its output, so these still work for
the theme and other plugins, and run alongside TOBIUO's callbacks (at priority 10 unless noted):

| Core hook | What TOBIUO does |
|---|---|
| `init` (priority 99) | Registers the taxonomies, then the post types, then fires `tobiuo_registered`. |
| `post_type_link` | Builds the link of a post whose post type has a `PermalinkConfig`. A callback at a later priority sees TOBIUO's link. |
| `template_redirect` (priority 9) | The canonical redirect, before core's `redirect_canonical()`. |
| `get_archives_link` | Points `wp_get_archives( [ 'post_type' => … ] )` date links at the post type's date archives. |
| `{$post_type}_rewrite_rules` | Not hooked. Core still applies it to the rules of TOBIUO's permastruct, and the admin page's rule check applies it too, so adjusted rules are not reported as missing. |

---

## What is *not* a hook

Deliberate omissions, so that the boundary stays predictable:

| | Why |
|---|---|
| Filtering `PostTypeConfig` / `TaxonomyConfig` / `PermalinkConfig` | The configuration already lives in your own theme code; a filter would let unrelated code rewrite it invisibly. Change the code. Core's `register_post_type_args` / `register_taxonomy_args` still apply to the arguments. |
| Filtering the whole permalink | Use core's `post_type_link` at a priority after 10. |
| Filtering the rewrite rules | Use core's `{$post_type}_rewrite_rules` or `rewrite_rules_array`. |
| Turning off the conflict check | Two plugins building the same permalinks is never what anyone wants. Deactivate the other one. |

## See also

- [Permalinks](../index.md#permalinks)
- [PermalinkConfig](../settings/permalinkconfig.md)
