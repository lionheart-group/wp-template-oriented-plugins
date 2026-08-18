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

With this registered, `get_permalink() . 'confirm/'` becomes a real, routable URL for every single
post, and `get_query_var('confirm')` is set (to an empty string, not `false` — check
`get_query_var('confirm') !== ''` won't work for the default request; use
`false !== get_query_var('confirm', false)` or a dedicated `global $wp_query; isset($wp_query->query_vars['confirm'])` check) whenever that endpoint segment is present in the request.

**Setup cost:** registering an endpoint changes the rewrite rules table for **every** permalink on
the site, and WordPress only picks up new rewrite rules after `flush_rewrite_rules()` runs (typically
called once on theme/plugin activation, or triggered by re-saving Settings → Permalinks). Forgetting
this step is a classic WordPress trap — it works immediately in local dev (where rewrite rules get
flushed often) and then silently 404s in production until someone thinks to flush.

**Why doesn't the plugin do this for you?** The plugin's own submit endpoint
(`src/Init/Endpoint.php`, `_tofu_key`) deliberately registers with `EP_NONE` — root-only — precisely
to avoid attaching to every permalink site-wide. Doing the same for `confirm`/`thanks` would be a
much larger blast radius that only your theme can decide is worth taking on.

**Tradeoff:** pretty URLs (`/news/hello-world/confirm/`), at the cost of the flush requirement and a
site-wide rewrite-rule change.

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
