# TaxonomyConfig

One taxonomy. Register with `Registry::registerTaxonomy()`; TOBIUO passes it to `register_taxonomy()`
on `init` at priority 99, **before** every post type — so a theme can declare post types and
taxonomies in whatever order its files load.

## Usage

```php
use TobiuoPlugin\Helpers\Registry;
use TobiuoPlugin\Structure\TaxonomyConfig;

add_action('init', function () {
    if (!class_exists('TobiuoPlugin\Helpers\Registry')) {
        return;
    }

    Registry::registerTaxonomy(new TaxonomyConfig(
        name: 'case_category',
        objectTypes: ['case'],
        args: [
            'label'             => '事例カテゴリー',
            'hierarchical'      => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => ['slug' => 'case/category', 'with_front' => false, 'hierarchical' => true],
        ],
    ));
});
```

### From a definition file

```php
// settings/taxonomies/case_category.php
return [
    'name'        => 'case_category',
    'objectTypes' => ['case'],
    'args'        => ['label' => '事例カテゴリー', 'hierarchical' => true],
];
```

```php
\TobiuoPlugin\Helpers\Registry::registerTaxonomy(
    \TobiuoPlugin\Structure\TaxonomyConfig::fromArray(require $file, basename($file))
);
```

Unknown keys and wrong types throw `InvalidArgumentException`. `objectTypes` must be a list of
strings — `'objectTypes' => 'case'` is rejected.

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `name` | `string` | Yes | — | Taxonomy key: lowercase letters, digits, `_` and `-`, at most 32 characters (core's limit). |
| `objectTypes` | `string[]` | No | `[]` | Post types the taxonomy belongs to. May be empty when the post types attach it through their own `taxonomies` argument. |
| `args` | `array` | No | `[]` | Passed to `register_taxonomy()` as they are. |

## Notes

- The term archive URLs are core's own, from the `rewrite` argument. TOBIUO never changes term links.
  A slug inside the post type's (`case/category`) works: taxonomy rules are matched before the post
  type's rules.
- To use the taxonomy in a permalink structure (`/%case_category%/%postname%/`), it must be attached to
  the post type — through `objectTypes`, or the post type's `taxonomies` argument. Otherwise
  registration stops with `wp_die()`. The taxonomy may also be registered by something other than
  TOBIUO (`category`, another plugin).
- With `'default_term' => ['name' => '未分類', 'slug' => 'uncategorized']`, core creates a default
  term, and posts without a term use it in their permalink.
- Registering the same taxonomy twice calls `wp_die()`.
