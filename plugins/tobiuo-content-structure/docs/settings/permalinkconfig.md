# PermalinkConfig

The URL structure of one post type's posts, and the archives that go with it. Passed as
`PostTypeConfig::$permalink`.

## Usage

```php
use TobiuoPlugin\Structure\PermalinkConfig;

// https://example.com/case/consulting/strategy/my-case/
new PermalinkConfig(structure: '/%case_category%/%postname%/');

// https://example.com/news/2024/05/my-news/, with /news/2024/ and /news/2024/05/ archives
new PermalinkConfig(structure: '/%year%/%monthnum%/%postname%/', dateArchive: true);

// https://example.com/event/123/, with /event/date/2024/ archives and /event/author/jane/
new PermalinkConfig(structure: '/%post_id%/', dateArchive: true, authorArchive: true);
```

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `structure` | `string` | Yes | — | Path of a post below the post type's rewrite slug. See [Structure](#structure). |
| `dateArchive` | `bool` | No | `false` | Year, month and day archives below the post type archive (`/case/2024/05/`). Needs `has_archive`. |
| `authorArchive` | `bool` | No | `false` | Per-author archives below the post type archive (`/case/author/jane/`). Needs `has_archive`. |
| `dateFront` | `?string` | No | `null` | Path between the archive and the year, e.g. `/date`. `null` chooses (see below); `''` forces none. |

### Structure

- Starts with `/`. A trailing `/` gives the links a trailing slash; without one they have none.
- Contains `%postname%` or `%post_id%` — the post is looked up by these.
- May contain `%year%` `%monthnum%` `%day%` `%hour%` `%minute%` `%second%` `%author%`, and
  `%{taxonomy}%` for any taxonomy attached to the post type. Each tag at most once.
- Literal text is allowed: `/news-%post_id%.html`.
- No `//`, `?`, `#`, whitespace or stray `%`.

The constructor checks all of this and throws `InvalidArgumentException`. Whether a `%{taxonomy}%`
tag names a registered taxonomy attached to the post type is checked once everything is registered
(`init` 99), since taxonomies may be registered in any order; a mistake stops with `wp_die()`
naming the post type and the tag. `dateArchive` or `authorArchive` on a post type without
`has_archive` stops the same way.

### dateFront

With `dateFront: null`, the date archives move below `/date` when every segment of the structure is
numeric and there are at most three (`/%post_id%/`, `/%year%/%post_id%/`,
`/%year%/%monthnum%/%post_id%/`): their posts' URLs would otherwise be indistinguishable from
`/case/2024/`, `/case/2024/05/` or `/case/2024/05/12/`, and the date archives, matched first, would
hide them. Any other structure gets no prefix.

## From a definition file

`PermalinkConfig::fromArray()` takes the constructor's parameter names as keys, and is what
`PostTypeConfig::fromArray()` uses for `'permalink' => [...]`:

```php
PermalinkConfig::fromArray(['structure' => '/%postname%/', 'dateArchive' => true], 'case');
```

Unknown keys (`'date_archive'`) and wrong types (`'dateArchive' => 'yes'`) throw, naming the label.

## Notes

- Changing any of these changes the rewrite rules: open **Settings → Permalinks** once afterwards.
- See [Permalinks](../index.md#permalinks) for how the links are built, which term is used, and the
  canonical redirect.
