
# TemplateConfig

Configuration for form template pages (input, confirm, result).

## Usage

```php
use TofuPlugin\Structure\TemplateConfig;

$template = new TemplateConfig(
    inputPath: '/contact/',
    confirmPath: '/contact/confirm/',
    resultPath: '/contact/result/',
);
```

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `inputPath` | `string` | Yes | - | URL path to the input page where users fill out the form. |
| `resultPath` | `string` | Yes | - | URL path to the result/thank-you page shown after successful submission. |
| `confirmPath` | `?string` | No | `null` | URL path to the confirmation page. Set to `null` to skip confirmation step. |

## Notes

- Each path is handed as-is to `wp_safe_redirect()`, so it must be either root-relative (`/contact/`) or an absolute URL on the same host. `wp_safe_redirect()` rejects other hosts and falls back to the site's home/login URL instead.
- The confirmation page is optional. If `confirmPath` is `null`, the form will submit directly from the input page.

## Dynamic Overrides (Per-Page Embeds)

`TemplateConfig` given to `FormConfig` is static — decided once when the form is registered. If the same form is embedded on many pages whose URL is only known at render time (for example, a contact form embedded in every post via `/news/{slug}/`), call `Form::setTemplate()` while rendering the input page to override the paths for that visitor's session:

```php
use TofuPlugin\Helpers\Form;
use TofuPlugin\Structure\TemplateConfig;

Form::setTemplate('contact', new TemplateConfig(
    inputPath: get_permalink(),
    confirmPath: add_query_arg('tofu_step', 'confirm', get_permalink()),
    resultPath: add_query_arg('tofu_step', 'result', get_permalink()),
));

echo Form::formOpen('contact', 'input');
```

Call it **before** `Form::formOpen()`.

`Form::setTemplate()` itself does **not** write to the session or issue a cookie — it only sets the override in memory for the current request. If it persisted immediately, every GET of a page that calls it (including a first-time visitor who never submits anything) would carry a `Set-Cookie` header, which stops full-page caches (Varnish, nginx `fastcgi_cache`, WP Rocket, CDNs) serving that page at all. Instead, `Form::formClose()` embeds the override in a hidden field, and it is written to the session — the same `wp_tofu_sessions` table, at the same point `storeSession()` already runs — only once the visitor actually submits the input form. Because form submissions always POST to the site root (`?_tofu_key=...`) and are handled entirely inside the plugin with no theme template running in between, this hidden field is what carries the override across that boundary; the session is then what carries it onward through the confirm → result requests, exactly as before.

Notes and edge cases:

- A form using only dynamic overrides (no static `confirmPath`) must set `dynamicTemplate: true` on its `FormConfig`, otherwise registering it with `confirmStep: true` throws — see [FormConfig](formconfig.md).
- An override pointing at a different host is rejected outright (all three paths, not just the offending one) and the static `TemplateConfig` is used instead; a warning is logged. This check runs identically whether the override came from a direct `setTemplate()` call or from the hidden field, so a tampered field value degrades to the static `TemplateConfig`, never to an off-site redirect.
- If a page calls `setTemplate()` but the visitor never submits the form, nothing is written anywhere — not the session, not a cookie. Only an actual submission persists it.
- Once persisted, the override lives in the session, so once `Form::verifySubmit()` clears the session on the result page, it's gone. If a later `Form::redirect($key, 'input')` call happens after that point (e.g. from a failed `verifySubmit()`), it falls back to the static `TemplateConfig::$inputPath`, not the page the visitor originally came from. This is expected — the visit is considered complete at that point.
- `Form::setTemplate()` only controls the redirect *target* — it doesn't make WordPress serve content at `confirmPath`/`resultPath`. See [Embedding One Form on Many Pages](../pages/multi-page-embeds.md) for the routing techniques (query string, rewrite endpoint, `template_include`) needed to actually render something there.
