# Embedding One Form on Many Pages

[`Form::setTemplate()`](../settings/templateconfig.md#dynamic-overrides-per-page-embeds) lets a
theme override a form's `inputPath` / `confirmPath` / `resultPath` per visitor session — so a
single registered form (e.g. `contact`) can be embedded on many pages whose URL is only known at
render time, such as a contact form embedded inside every post at `/news/{slug}/contact/`.

That solves **where the redirect should point**. It does not solve **how a URL like
`/news/{slug}/confirm/` actually renders anything** — WordPress has no real Page/Post object at
that address, so by default the browser gets a 404 the moment the plugin redirects there. Routing
that URL to real content is entirely a theme concern; this page walks through the three common ways
to do it.

## The Problem, Concretely

A traditional TOFU setup registers three *real* WordPress pages — `/contact/`, `/contact/confirm/`,
`/contact/result/` — each with its own template file (see [Input](input.md), [Confirm](confirm.md),
[Result](result.md)). That works because those three pages actually exist.

Once the same form is embedded inside every post, you can't create three real pages per post ahead
of time. The confirm/result "pages" have to be synthesized from the *same* post's permalink at
request time. Below are three ways to make that URL resolve to content.

## Technique 1 — Query-String Step Switch (Recommended Default)

One physical template (e.g. the post type's `single.php`) handles all three steps for its own
permalink, branching on a query var:

```php
<?php
use TofuPlugin\Helpers\Form;
use TofuPlugin\Structure\TemplateConfig;

$formKey = 'contact';
$step = $_GET['tofu_step'] ?? 'input'; // 'input' | 'confirm' | 'result'

// Point all three steps back at this same post, distinguished only by a query var.
Form::setTemplate($formKey, new TemplateConfig(
    inputPath:   get_permalink(),
    confirmPath: add_query_arg('tofu_step', 'confirm', get_permalink()),
    resultPath:  add_query_arg('tofu_step', 'result', get_permalink()),
));

get_header();

switch ($step) {
    case 'confirm':
        if (!Form::verifySession($formKey)) {
            Form::redirect($formKey, 'input');
        }
        // ... render confirm content ...
        break;

    case 'result':
        if (!Form::verifySubmit($formKey)) {
            Form::redirect($formKey, 'input');
        }
        // ... render result content ...
        break;

    default:
        Form::embedScript($formKey);
        // ... render the input form, e.g. Form::formOpen()/formClose() ...
        break;
}

get_footer();
```

**Setup cost:** none — `add_query_arg()` is core WordPress, no rewrite rules involved.

**Tradeoff:** the URL carries a query string (`?tofu_step=confirm`). Full-page-cache layers and CDNs
sometimes vary — or entirely bypass — their cache based on query strings, so check your caching
setup before relying on this in front of a busy site.

Start here unless a pretty, query-string-free URL is a hard requirement.

## Technique 2 — WP Rewrite Endpoint Attached to Permalinks

```php
add_action('init', function () {
    add_rewrite_endpoint('confirm', EP_PERMALINK);
    add_rewrite_endpoint('thanks', EP_PERMALINK);
});
```

With this registered, `get_permalink() . 'confirm/'` becomes a real, routable URL, and
`get_query_var('confirm')` is set whenever that endpoint segment is present in the request. Since a
value-less endpoint segment (`/confirm/`) captures an empty string rather than being absent, a plain
truthy check on the return value is unreliable — detect presence with a sentinel default instead:
`get_query_var('confirm', null) !== null`.

### Which Template File Actually Gets Selected

Registering the endpoint does **not** add any new template-selection rule — verified against
WordPress core (`wp-includes/class-wp-rewrite.php`, `wp-includes/class-wp.php`,
`wp-includes/template-loader.php`, `wp-includes/template.php`). The endpoint's rewrite rule appends
its capture group onto the *same* rule that already matches the base permalink
(`class-wp-rewrite.php:1042-1050`), so `/news/hello-world/confirm/` resolves to
`index.php?news=hello-world&confirm=` — the original post-identifying query vars are untouched, with
`confirm` just carried alongside them into the same `WP_Query`. `is_single()`/`is_singular()`/
`get_queried_object()` all resolve to the exact same post they would without the `/confirm/` suffix.

`template-loader.php` picks a template purely by which conditional tag matches (`is_single`,
`is_page`, `is_singular`, etc. — `template-loader.php:67-105`) and has no notion of endpoints at all
(zero references to "endpoint" anywhere in that file or in `template.php`). So `get_single_template()`
still walks `single-{post_type}-{slug}.php` → `single-{post_type}.php` → `single.php`
(`template.php:553-576`), and `get_page_template()` still walks `page-{slug}.php` → `page-{id}.php` →
`page.php` (`template.php:471-503`) — **exactly the same file** WordPress would have loaded for the
plain permalink. There is no auto-selected "confirm template."

In practice this means: whichever single template file the post normally uses is the one place you
branch — the same way Technique 1's `switch ($step)` branches inside one `single.php`, just driven
here by `get_query_var('confirm')` instead of a query string. If you'd rather swap in an entirely
separate file instead of branching inline, that's exactly what the `template_include` filter
(Technique 3, below) is for.

### Exactly What `EP_PERMALINK` Attaches To

`EP_PERMALINK` is not "every permalink" — it's one specific bit (value `1`) that WordPress checks
against each rewrite structure's own mask. Verified against WordPress core
(`wp-includes/class-wp-rewrite.php`, `wp-includes/class-wp-post-type.php`):

| Registered with default settings | Included? |
|---|---|
| `post` (standard posts) | Yes — the main permastruct is generated with exactly `EP_PERMALINK` |
| Custom post types with default `rewrite` settings | Yes — `ep_mask` defaults to `EP_PERMALINK` when not set explicitly |
| Custom post types with an explicit `ep_mask` override | Only if that mask includes the `EP_PERMALINK` bit |
| **Pages** (`page` post type) | **No** — pages are generated via a separate `EP_PAGES` mask (value `4096`), a different code path entirely |
| Taxonomy/author/date archives, search, attachments | No — each uses its own separate mask (`EP_CATEGORIES`, `EP_AUTHORS`, `EP_DATE`, `EP_SEARCH`, a hardcoded attachment check) |

The practical trap: if you embed the form on a WordPress **Page** rather than a post, the snippet
above silently does nothing for it — `EP_PAGES & EP_PERMALINK === 0` — so `/some-page/confirm/`
still 404s. To cover Pages too, combine the bits:

```php
add_rewrite_endpoint('confirm', EP_PERMALINK | EP_PAGES);
add_rewrite_endpoint('thanks', EP_PERMALINK | EP_PAGES);
```

Conversely, a specific custom post type can opt out entirely via
`register_post_type(..., ['rewrite' => ['ep_mask' => EP_NONE]])`.

**Setup cost:** registering an endpoint changes the rewrite rules table for every structure whose
mask matches, and WordPress only picks up new rewrite rules after `flush_rewrite_rules()` runs
(typically called once on theme/plugin activation, or triggered by re-saving Settings → Permalinks).
Forgetting this step is a classic WordPress trap — it works immediately in local dev (where rewrite
rules get flushed often) and then silently 404s in production until someone thinks to flush.

**Why doesn't the plugin do this for you?** The plugin's own submit endpoint
(`src/Init/Endpoint.php`, `_tofu_key`) deliberately registers with `EP_NONE` — root-only — precisely
to avoid attaching to `post` and every default-`ep_mask` custom post type. Doing the same for
`confirm`/`thanks` would be a much larger blast radius that only your theme can decide is worth
taking on.

**Tradeoff:** pretty URLs (`/news/hello-world/confirm/`), at the cost of the flush requirement and a
rewrite-rule change affecting every post type whose mask includes the bit(s) you registered with.

### Restricting to a Specific Post Type

Techniques 1 (query string) and 3 (`template_include`) are already scoped to wherever you call them
— Technique 1 by which template calls `Form::setTemplate()`, Technique 3 by its `is_singular()`
check. Technique 2 is the odd one out: `EP_PERMALINK` alone attaches to *every* post type that kept
the default `ep_mask`, not just the one you have in mind.

To scope the endpoint to a single custom post type (e.g. `news`) and nothing else, give that CPT its
own mask bit and register the endpoint against only that bit:

```php
// WordPress core's built-in EP_* constants top out at EP_PAGES = 4096
// (i.e. 1 << 12), so 1 << 13 and above are safe, unused bits.
if (!defined('EP_TOFU_NEWS')) {
    define('EP_TOFU_NEWS', 1 << 13); // 8192
}

add_action('init', function () {
    // Give only the "news" CPT this bit. OR it with EP_PERMALINK if
    // this post type still needs its normal permalink behavior too.
    register_post_type('news', [
        // ...
        'rewrite' => ['slug' => 'news', 'ep_mask' => EP_PERMALINK | EP_TOFU_NEWS],
    ]);

    // Register the endpoint against ONLY the custom bit — not
    // EP_PERMALINK — so it never attaches to `post` or any other CPT
    // that kept the default mask.
    add_rewrite_endpoint('confirm', EP_TOFU_NEWS);
    add_rewrite_endpoint('thanks', EP_TOFU_NEWS);
});
```

Notes:

- Changing `ep_mask` still requires a `flush_rewrite_rules()`, same as above.
- A lighter-weight alternative that avoids touching `ep_mask`/`register_post_type()` at all: keep the
  endpoint registered broadly with `EP_PERMALINK`, but gate the actual confirm/result *content* in
  your template on `get_post_type() === 'news'`. This is simpler, but the URL itself still resolves
  (200, showing the same content as the normal page) for every other post type that kept the default
  mask — harmless in practice, but it does mean unintended URLs exist rather than 404ing.

#### When the Post Type Is Registered by a Different Plugin

The snippet above assumes you own the `register_post_type()` call. If the post type instead comes
from another plugin — including UI-driven ones like [Custom Post Type UI](https://wordpress.org/plugins/custom-post-type-ui/)
or ACF / [Secure Custom Fields](https://wordpress.org/plugins/secure-custom-fields/) — you can still
inject your mask bit, without touching that plugin's code, via the `register_post_type_args` filter.
It fires inside `WP_Post_Type::set_props()` *before* WordPress fills in the `ep_mask` default, so a
value you set there wins over the default:

```php
if (!defined('EP_TOFU_NEWS')) {
    define('EP_TOFU_NEWS', 1 << 13);
}

// Runs before any register_post_type() call as long as this add_filter()
// executes first — which a top-level call in functions.php always does,
// regardless of which action/priority the other plugin registers on.
add_filter('register_post_type_args', function ($args, $post_type) {
    if ($post_type !== 'some_plugins_cpt') {
        return $args;
    }

    // Respect an explicit rewrite => false (rewrite intentionally
    // disabled) instead of accidentally turning rewrites back on.
    if ($args['rewrite'] === false) {
        return $args;
    }

    if (!is_array($args['rewrite'])) {
        $args['rewrite'] = [];
    }

    // Preserve whatever ep_mask the plugin already set (mirrors core's
    // own fallback order) and just add our bit.
    $existingMask = $args['rewrite']['ep_mask'] ?? $args['permalink_epmask'] ?? EP_PERMALINK;
    $args['rewrite']['ep_mask'] = $existingMask | EP_TOFU_NEWS;

    return $args;
}, 10, 2);

add_action('init', function () {
    add_rewrite_endpoint('confirm', EP_TOFU_NEWS);
    add_rewrite_endpoint('thanks', EP_TOFU_NEWS);
});
```

WordPress 6.0+ also offers `register_{$post_type}_post_type_args` — the same filter, scoped to one
post type by name, so you can skip the `if ($post_type !== ...)` check.

**Custom Post Type UI** and **ACF / Secure Custom Fields** both register their post types through
this same core `register_post_type()` path (verified against both plugins' source) and never set
`ep_mask` themselves, so the filter above applies to them exactly as written — you only need the
right `$post_type` string:

- **Custom Post Type UI**: use the **Post Type Slug** field from *CPT UI → Add/Edit Post Types →
  Basics*. (Don't confuse it with the separate "Rewrite Slug" field, which only affects the URL
  base, not the registered post type name.) CPT UI registers on `init` at priority `10`.
- **ACF / Secure Custom Fields**: use the **Post Type Key** field from *Custom Fields → Post Types →
  [your post type] → Basic Settings*. (ACF's internal `$post_type['key']` is a different, internal
  object key — not this value.) It registers via `acf/init` at priority `6`, itself fired from
  `init` at priority `5`.

Either way, if an admin disables rewrite entirely in that plugin's UI (CPT UI's "Rewrite" toggle, or
ACF's "No Permalink" setting), `$args['rewrite']` comes through as `false` and the guard above leaves
it alone.

## Technique 3 — Custom `template_include` Routing

```php
add_filter('template_include', function ($template) {
    if (is_singular('post') && preg_match('#/confirm/?$#', $_SERVER['REQUEST_URI'])) {
        return locate_template('tofu-confirm.php');
    }
    if (is_singular('post') && preg_match('#/thanks/?$#', $_SERVER['REQUEST_URI'])) {
        return locate_template('tofu-result.php');
    }
    return $template;
});
```

This swaps in a completely separate template file per step (rather than branching inline in
`single.php`) without touching the rewrite rules table at all — so no `flush_rewrite_rules()` is
needed.

**Tradeoff:** you own all the edge cases WordPress core normally handles for you — trailing slashes,
query strings appended to the URL, multisite subdirectory installs, and `redirect_canonical()`
potentially "correcting" the URL away from what you expect before this filter even runs. This is the
most flexible option and the most manual one.

## Comparison

| Technique | URL shape | Setup cost | Main risk |
|---|---|---|---|
| 1. Query string | `/news/slug/?tofu_step=confirm` | None | Cache layers may treat query strings differently |
| 2. Rewrite endpoint (`EP_PERMALINK`) | `/news/slug/confirm/` | `flush_rewrite_rules()` once, site-wide | Forgotten flush → silent 404s |
| 3. `template_include` + custom regex | `/news/slug/confirm/` | Manual URL-matching code | Most edge cases owned by the theme |

None of these require any change to the plugin itself — `Form::setTemplate()` accepts whatever
string you give it, so the routing technique is entirely a theme-side decision. Start with
Technique 1; reach for 2 or 3 only when a query-string-free URL is a real requirement.
