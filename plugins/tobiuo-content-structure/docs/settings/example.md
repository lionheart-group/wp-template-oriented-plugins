# Complete Example

A theme with WordPress's posts as news below `/news/`, case studies filed by category, press releases
by date, and events by ID.

```php
<?php
// functions.php

use TobiuoPlugin\Helpers\Registry;
use TobiuoPlugin\Structure\PermalinkConfig;
use TobiuoPlugin\Structure\PostsConfig;
use TobiuoPlugin\Structure\PostTypeConfig;
use TobiuoPlugin\Structure\TaxonomyConfig;

add_action('init', function () {
    // Keep the theme working while the plugin is deactivated.
    if (!class_exists('TobiuoPlugin\Helpers\Registry')) {
        return;
    }

    // WordPress's posts: archive at https://example.com/news/, posts at
    // https://example.com/news/my-post/ once Settings → Permalinks is set to
    // /news/%postname%/ (the Tools page warns until it is). The front /news/
    // also prefixes core's date, author, category and tag archives, and every
    // post type below with 'with_front' => true — so they all set it to false.
    Registry::registerPosts(new PostsConfig(
        archive: 'news',
        permalink: new PermalinkConfig(structure: '/%postname%/'),
    ));

    // Post types may come before their taxonomies: TOBIUO registers every
    // taxonomy first, at init 99.
    Registry::registerPostType(new PostTypeConfig(
        name: 'case',
        args: [
            'label'         => '事例紹介',
            'labels'        => ['singular_name' => '事例', 'add_new_item' => '事例を追加'],
            'public'        => true,
            'has_archive'   => true,
            'rewrite'       => ['slug' => 'case', 'with_front' => false],
            'menu_position' => 5,
            'menu_icon'     => 'dashicons-portfolio',
            'supports'      => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
            'show_in_rest'  => true,
        ],
        // https://example.com/case/consulting/strategy/my-case/
        // https://example.com/case/author/jane/
        permalink: new PermalinkConfig(
            structure: '/%case_category%/%postname%/',
            authorArchive: true,
        ),
    ));

    Registry::registerTaxonomy(new TaxonomyConfig(
        name: 'case_category',
        objectTypes: ['case'],
        args: [
            'label'             => '事例カテゴリー',
            'hierarchical'      => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            // Term archives at https://example.com/case/category/consulting/
            'rewrite'           => ['slug' => 'case/category', 'with_front' => false, 'hierarchical' => true],
            // Posts without a category link to /case/uncategorized/{post}/
            'default_term'      => ['name' => '未分類', 'slug' => 'uncategorized'],
        ],
    ));

    Registry::registerPostType(new PostTypeConfig(
        name: 'press',
        args: [
            'label'        => 'プレスリリース',
            'public'       => true,
            'has_archive'  => true,
            'rewrite'      => ['slug' => 'press', 'with_front' => false],
            'show_in_rest' => true,
        ],
        // https://example.com/press/2024/05/my-release/
        // https://example.com/press/2024/ and /press/2024/05/
        permalink: new PermalinkConfig(
            structure: '/%year%/%monthnum%/%postname%/',
            dateArchive: true,
        ),
    ));

    Registry::registerPostType(new PostTypeConfig(
        name: 'event',
        args: [
            'label'       => 'イベント',
            'public'      => true,
            'has_archive' => 'events',
            'rewrite'     => ['slug' => 'event', 'with_front' => false],
        ],
        // https://example.com/event/123/
        // https://example.com/events/date/2024/ — a structure of numbers only
        // moves the date archives below /date. The archive slug differs from
        // the rewrite slug here, so dateFront: '' would be safe too.
        permalink: new PermalinkConfig(
            structure: '/%post_id%/',
            dateArchive: true,
        ),
    ));
});

// Code that needs the post types to exist
add_action('tobiuo_registered', function () {
    register_post_meta('event', 'event_date', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
});

// Prefer a "featured" category in case study links when a post has one
add_filter('tobiuo_post_link_term', function ($term, $terms, $taxonomy, $post) {
    if ($taxonomy !== 'case_category') {
        return $term;
    }

    foreach ($terms as $candidate) {
        if (get_term_meta($candidate->term_id, 'featured', true)) {
            return $candidate;
        }
    }

    return $term;
}, 10, 4);
```

In a template:

```php
<?php if (function_exists('tobiuo_get_year_link')) : ?>
    <a href="<?php echo esc_url(tobiuo_get_year_link('press', (int) get_the_date('Y'))); ?>">
        <?php echo esc_html(get_the_date('Y')); ?>
    </a>
<?php endif; ?>

<ul>
    <?php wp_get_archives(['post_type' => 'press', 'type' => 'monthly']); ?>
</ul>
```
