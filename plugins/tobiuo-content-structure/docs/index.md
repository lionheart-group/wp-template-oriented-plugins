# TOBIUO (Template-Oriented Builder of Items, URLs & Organization) documentation

## Settings

TOBIUO is configured in your WordPress theme's `functions.php` (on `init`) using the following settings:

- [PostTypeConfig](settings/posttypeconfig.md) — a post type, registered with `Registry::registerPostType()`
  - [PermalinkConfig](settings/permalinkconfig.md) — its URL structure and its date / author archives
- [TaxonomyConfig](settings/taxonomyconfig.md) — a taxonomy, registered with `Registry::registerTaxonomy()`
- [PostsConfig](settings/postsconfig.md) — the archive and permalink of WordPress's built-in posts, registered with `Registry::registerPosts()`

Here's a complete example showing all of them together:

[Example configuration](settings/example.md)

## When things happen

| When | What |
|---|---|
| `init`, priority below 99 (the default 10 is fine) | The theme calls `Registry::registerTaxonomy()` / `Registry::registerPostType()` / `Registry::registerPosts()`, in any order. Nothing reaches WordPress yet, except that `get_option('permalink_structure')` returns the `PostsConfig` structure from then on. |
| `init`, priority 99 | TOBIUO first applies the `PostsConfig` (re-reads the permalink structure into `WP_Rewrite`, sets the posts archive), then calls `register_taxonomy()` for every taxonomy, then `register_post_type()` for every post type, then checks each `PermalinkConfig` against what is now registered (a bad config stops with `wp_die()` naming the post type and the tag). Then the action [`tobiuo_registered`](hooks/index.md#tobiuo_registered) fires, and TOBIUO replaces the permastructs and adds the archive rules. |
| Any later request | `get_permalink()` builds the links from the structure; a post requested through a wrong term path is redirected to its permalink. |

Because TOBIUO registers everything at `init` 99, code that needs the post types or taxonomies to
exist must run after that — on `tobiuo_registered`, on `wp_loaded`, or at `init` 100 and later.
Registering a config after the hand-over calls `wp_die()`, so a late registration is noticed instead
of silently doing nothing. So does registering a post type built into WordPress (`post`, `page`, …)
with `registerPostType()`: it would replace core's definition. Posts are configured with
[PostsConfig](settings/postsconfig.md).

## Posts

WordPress's own posts keep core's post type. [PostsConfig](settings/postsconfig.md) gives them an
archive (`/news/`) and the permalink structure (`/news/%postname%/`) from theme code, replacing the
structure stored by Settings → Permalinks. Because that structure is the site's, its front also
prefixes core's date, author, category and tag archives — and every custom post type or taxonomy whose
`rewrite` has `with_front => true`.

## Permalinks

A post type with a `PermalinkConfig` gets links built from its structure, below its rewrite slug:

| Structure | Link |
|---|---|
| `/%postname%/` | `/case/my-case/` |
| `/%case_category%/%postname%/` | `/case/consulting/strategy/my-case/` |
| `/%year%/%monthnum%/%postname%/` | `/case/2024/05/my-case/` |
| `/%post_id%/` | `/case/123/` |
| `/%author%/%postname%` | `/case/jane/my-case` (no trailing slash: the structure has none) |

Tags: `%postname%` (for a hierarchical post type, the parent path: `parent/child`), `%post_id%`,
`%year%`, `%monthnum%`, `%day%`, `%hour%`, `%minute%`, `%second%` (from the post date, site time),
`%author%` (the author's `user_nicename`) and `%{taxonomy}%` for any taxonomy attached to the post
type.

The rewrite slug, `with_front`, `feeds` and `ep_mask` come from the post type's own `rewrite`
argument, exactly as core uses them. The structure's trailing slash decides the links' trailing
slash (Settings → Permalinks does not).

### Which term a link uses

For `%{taxonomy}%`, TOBIUO looks at the post's terms in that taxonomy:

1. Terms that are an ancestor of another of the post's terms are dropped (a post in *Consulting*
   and *Consulting › Strategy* is a *Strategy* post).
2. Of the rest, the one with the lowest term ID is chosen.
3. The [`tobiuo_post_link_term`](hooks/index.md#tobiuo_post_link_term) filter may replace it.
4. The term's ancestors are put in front: `consulting/strategy`.

A post with no term uses the taxonomy's default term (`default_term` of `register_taxonomy()`;
`default_category` for categories). With no default term either, the post gets WordPress's plain
link (`/?case=my-case`), which always works — a link never contains a literal `%tag%`.

Drafts, pending and scheduled posts get WordPress's plain preview link, as for core's post types.
The editor's permalink box still shows the pretty URL with an editable slug, once the post has a
term (or the taxonomy a default term).

### Canonical redirect

The rewrite rules find a post by its name (or ID). The term path in front of it is not part of the
lookup, so `/case/wrong-term/my-case/` finds the post too. TOBIUO answers such a request with a
301 to the permalink, keeping the page number, the comment page and the query string. Feeds, embeds,
trackbacks, previews, attachments and endpoints (`/my-case/amp/`) are left alone, as are requests by
query string (`/?case=my-case`), which are core's `redirect_canonical()`'s.

Turn it off with [`tobiuo_redirect_canonical`](hooks/index.md#tobiuo_redirect_canonical).

### Date and author archives

With `dateArchive: true`, the post type gets year, month and day archives below its archive URL:
`/case/2024/`, `/case/2024/05/`, `/case/2024/05/12/`, each with `/page/2/` and, when the post type
has feeds, `/feed/`. With `authorArchive: true`, it gets `/case/author/jane/`. Both need
`has_archive`, and use its slug when it is a string (`'has_archive' => 'works'` gives
`/works/2024/`).

When post URLs would look exactly like date archives — the structure is only numbers, such as
`/%post_id%/` or `/%year%/%post_id%/` — the date archives move below `/date`: `/case/date/2024/`.
`PermalinkConfig::$dateFront` sets this explicitly.

Links to them:

```php
tobiuo_get_year_link('case', 2024);             // https://example.com/case/2024/
tobiuo_get_month_link('case', 2024, 5);         // https://example.com/case/2024/05/
tobiuo_get_day_link('case', 2024, 5, 12);       // https://example.com/case/2024/05/12/
tobiuo_get_author_link('case', get_the_author_meta('ID')); // https://example.com/case/author/jane/
```

Each returns `''` when the post type has no such archive, so a template can test the result. For
`post` they return core's own links (`get_year_link()`, `get_month_link()`, `get_day_link()`,
`get_author_posts_url()`).

`wp_get_archives(['post_type' => 'case'])` links to these archives as well, instead of core's
`/2024/05/?post_type=case`. Only the daily, monthly and yearly types are rewritten; weekly archives
have no pretty URL and keep core's link.

### Rewrite rules

Nothing is stored and nothing is flushed automatically. WordPress regenerates its rewrite rules when
**Settings → Permalinks** is opened (no need to save) and when TOBIUO is activated or deactivated.
After changing a structure, an archive option or a slug, open that screen once on each environment.

**Tools → Content Structure (TOBIUO)** compares the rules the current configuration needs with the
stored ones and lists any that are missing.

### Rule order

Taxonomy rules are matched before post rules, so a taxonomy whose slug is inside the post type's
(`'rewrite' => ['slug' => 'case/category']`) keeps its term archives at `/case/category/consulting/`
even with a `/%case_category%/%postname%/` structure. The post type archive and the date and author
archives come before both.

### Limits

- A **hierarchical** post type with a taxonomy tag (`/%area%/%postname%/`) can only be matched when
  the term path has one segment: both the term path and the page path may contain slashes, and the
  rule cannot tell where one ends.
- A **post whose slug is a number** can be shadowed by a date archive (`/case/2024/`), as with core's
  `/%year%/%monthnum%/%postname%/`. A post or term slug equal to the author base (`author`) is
  shadowed by the author archives.
- The **trailing slash** of post links follows the structure, but core's `redirect_canonical()`
  adds or removes it on requests according to Settings → Permalinks. Keep the two the same.
- **Attachment** pages of posts keep core's handling.

## Hooks

[Actions and filters reference](hooks/index.md)

## Admin page

**Tools → Content Structure (TOBIUO)** shows the posts archive and the permalink structure in use (and
whether it comes from the theme or from Settings → Permalinks), and lists the post types and
taxonomies the theme registered, with
their structure, the URL of the latest post, the archive URL, example date and author archive links,
example term URLs, and whether the stored rewrite rules are complete. It is read-only. The capability
required to see it is `manage_options`, filterable with
[`tobiuo_admin_page_capability`](hooks/index.md#tobiuo_admin_page_capability).

## Custom Post Type Permalinks

While Custom Post Type Permalinks is active, TOBIUO still registers the post types and taxonomies but
builds no permalinks, adds no rules and redirects nothing, and shows an admin notice on the Plugins
screen and the TOBIUO page. Two plugins replacing the same permastructs would leave each post with
whichever URL ran last.

### Migrating

| Custom Post Type Permalinks | TOBIUO |
|---|---|
| `'cptp' => ['permalink_structure' => '/%postname%/']` (or `cptp_permalink_structure`) in the post type's arguments | `permalink: new PermalinkConfig(structure: '/%postname%/')` |
| A structure from Settings → Permalinks (stored in the DB) | Write it in the `PermalinkConfig` |
| Date and author archives, **on by default** (`'cptp' => ['date_archive' => …, 'author_archive' => …]`) | `dateArchive: true`, `authorArchive: true` — **off by default**, so set them to keep those URLs |
| `cptp_date_front` filter | `PermalinkConfig::$dateFront` |
| `cptp_post_link_term` filter | [`tobiuo_post_link_term`](hooks/index.md#tobiuo_post_link_term) — receives the taxonomy name rather than the taxonomy object, and only a `WP_Term` of that taxonomy is accepted |
| `cptp_post_link_category`, `post_link_category` | `tobiuo_post_link_term` with `$taxonomy === 'category'` |
| "Use custom permalink of custom taxonomy archive" / `no_taxonomy_structure` | Not needed: TOBIUO never changes term links. Put a prefix in the taxonomy's own `rewrite` slug (`'case/category'`). |
| `add_post_type_for_tax` | Not supported |

Differences in behaviour:

- Wrong term paths are redirected to the permalink.
- A post without a term gets the taxonomy's default term, or the plain link — never a URL with an empty segment.
- Feed rules follow the post type's `feeds` setting (CPTP always added them).
- Date archive rules use the site's pagination base and feed types (CPTP hard-coded `page` and the feed list).

Steps: deactivate CPTP, replace the `cptp` arguments with `PermalinkConfig`s, set `dateArchive` /
`authorArchive` where the old archives were in use, open Settings → Permalinks, and check the URLs on
**Tools → Content Structure (TOBIUO)**.
