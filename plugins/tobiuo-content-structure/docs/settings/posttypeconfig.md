# PostTypeConfig

One post type. Register with `Registry::registerPostType()`; TOBIUO passes it to
`register_post_type()` on `init` at priority 99, after every taxonomy.

## Usage

```php
use TobiuoPlugin\Helpers\Registry;
use TobiuoPlugin\Structure\PermalinkConfig;
use TobiuoPlugin\Structure\PostTypeConfig;

add_action('init', function () {
    if (!class_exists('TobiuoPlugin\Helpers\Registry')) {
        return;
    }

    Registry::registerPostType(new PostTypeConfig(
        name: 'case',
        args: [
            'label'       => '事例紹介',
            'public'      => true,
            'has_archive' => true,
            'rewrite'     => ['slug' => 'case', 'with_front' => false],
            'supports'    => ['title', 'editor', 'thumbnail', 'excerpt'],
            'show_in_rest' => true,
        ],
        permalink: new PermalinkConfig(structure: '/%case_category%/%postname%/'),
    ));

    // No PermalinkConfig: core's own /news/{post name}/
    Registry::registerPostType(new PostTypeConfig(
        name: 'news',
        args: ['label' => 'お知らせ', 'public' => true, 'has_archive' => true],
    ));
});
```

### From a definition file

`PostTypeConfig::fromArray()` builds a `PostTypeConfig` from an array whose keys are the
constructor's parameter names. `permalink` may be an array for `PermalinkConfig::fromArray()`:

```php
// settings/post-types/case.php
return [
    'name' => 'case',
    'args' => [
        'label'       => '事例紹介',
        'public'      => true,
        'has_archive' => true,
        'rewrite'     => ['slug' => 'case', 'with_front' => false],
    ],
    'permalink' => [
        'structure'   => '/%case_category%/%postname%/',
        'dateArchive' => true,
    ],
];
```

```php
add_action('init', function () {
    if (!class_exists('TobiuoPlugin\Helpers\Registry')) {
        return;
    }

    foreach (glob(get_theme_file_path('settings/post-types/*.php')) as $file) {
        \TobiuoPlugin\Helpers\Registry::registerPostType(
            \TobiuoPlugin\Structure\PostTypeConfig::fromArray(require $file, basename($file))
        );
    }
});
```

Unknown keys throw `InvalidArgumentException`, naming the key and the label (here the file name) —
here and inside `permalink`. Values must have the right type too: `'args' => 'public=1'` and
`'dateArchive' => 'yes'` are rejected rather than coerced.

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `name` | `string` | Yes | — | Post type key: lowercase letters, digits, `_` and `-`, at most 20 characters (core's limit). |
| `args` | `array` | No | `[]` | Passed to `register_post_type()` as they are. |
| `permalink` | `?PermalinkConfig` | No | `null` | The post type's URL structure. `null` leaves the permalinks to core. See [PermalinkConfig](permalinkconfig.md). |

## Notes

- The `rewrite` argument decides the slug in front of the structure, `with_front`, `feeds` and
  `ep_mask`, as it does for core. A `PermalinkConfig` on a post type with `'rewrite' => false` stops
  with `wp_die()`.
- Registering the same post type twice calls `wp_die()`.
- The post type exists from `init` priority 99. Code that needs it earlier than that will not find it;
  use the [`tobiuo_registered`](../hooks/index.md#tobiuo_registered) action.
- There is no filter on `PostTypeConfig`: it lives in your own theme code. Use core's
  `register_post_type_args` to change another plugin's post types.
