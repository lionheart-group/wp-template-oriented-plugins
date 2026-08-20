# AJAX / Headless Mode

TOFU includes a WP REST API layer that lets you submit forms via JavaScript
without full-page reloads, or from a completely separate frontend application.

## Overview

| Mode | Description | Cookie requirement |
|---|---|---|
| **Same-origin AJAX** | JS runs on the same WordPress domain | `SameSite=Lax` (automatic) |
| **Cross-origin / Headless** | Separate frontend (Next.js, Nuxt, etc.) calling WP as an API | `SameSite=None; Secure` + HTTPS |

## How it works

1. **Register the form** in `functions.php` with `ajaxEnabled: true`
2. **Fetch a nonce** from `GET /wp-json/tofu/v1/forms/{key}/nonce`
3. **Submit** `POST /wp-json/tofu/v1/forms/{key}/input` with `FormData`
4. On success, the JSON response contains `next` (`"confirm"` or `"result"`)
5. If `next` is `"confirm"`, show the confirm step and submit `POST …/confirm`

## Endpoints

| Method | URL | Purpose |
|---|---|---|
| `GET` | `/wp-json/tofu/v1/forms/{key}/nonce` | Get a fresh TOFU nonce |
| `POST` | `/wp-json/tofu/v1/forms/{key}/input` | Submit input step |
| `POST` | `/wp-json/tofu/v1/forms/{key}/confirm` | Submit confirm step |

## Response format

**Success (HTTP 200):**
```json
{ "success": true, "next": "confirm" }
```

**Validation error (HTTP 422):**
```json
{
  "success": false,
  "next": "input",
  "errors": {
    "name":  ["Please enter your name."],
    "email": ["Please enter a valid email address."]
  }
}
```

**Nonce (GET):**
```json
{
  "nonce": "abc123…",
  "field_name": "_tofu_contact_nonce",
  "action": "input",
  "recaptcha": { "site_key": "...", "token_field_name": "_tofu_recaptcha_token" },
  "turnstile": null
}
```

`recaptcha`/`turnstile` carry the **site key** (safe to expose publicly — it's the same value that
already appears in every reCAPTCHA/Turnstile widget's HTML/script URL) whenever that protection is
enabled for the form, or `null` otherwise. This lets a client fetch the site key from the same place
it already fetches the nonce, instead of hardcoding it separately per environment — see
[Vanilla JavaScript](./vanilla-js.md) for the integration pattern.

The `nonce` itself expires on WordPress core's usual schedule (12–24 hours by default, unchanged by
this plugin) and is invalidated immediately if the visitor's login state changes — fetch it fresh
right before submitting rather than caching it. See
[Headless — `403 Forbidden`](./headless.md#403-forbidden--nonce-verification-failed) for details.

## PHP Setup

Enable AJAX mode in `functions.php`. `template` is not required for AJAX-only forms:

```php
add_action('init', function () {
    \TofuPlugin\Helpers\Form::register(new \TofuPlugin\Structure\FormConfig(
        key:         'contact',
        name:        'Contact Form',
        mail:        $mailConfig,
        validation:  $validationConfig,

        // No template needed for pure AJAX / headless usage
        ajaxEnabled: true,

        // Enable a confirm step (no template required)
        // confirmStep: true,

        // Cross-origin (headless): add your frontend URL
        // corsOrigins: ['https://frontend.example.com'],
    ));
});
```

If you also want to support the traditional WP page flow alongside AJAX, add `template`:

```php
        template: new \TofuPlugin\Structure\TemplateConfig(
            inputPath:  '/contact/',
            resultPath: '/contact/result/',
            // confirmPath: '/contact/confirm/',  // with confirmStep: true, enables confirm step in both flows
        ),
```

See [FormConfig](../settings/formconfig.md) for the full list of options.

## Guides

- [Vanilla JavaScript](./vanilla-js.md) — No framework, works in any WP theme
- [React](./react.md) — React hooks with multi-step form state
- [Vue 3](./vue.md) — Vue 3 Composition API
- [Headless / Cross-Origin Setup](./headless.md) — Separate frontend domain (Next.js, Nuxt, etc.)

## Notes

- reCAPTCHA and Cloudflare Turnstile are fully supported in AJAX mode
- File uploads are supported via `FormData` (multipart)
- The traditional redirect-based endpoint (`?_tofu_key=…`) is unchanged
  — you can use both modes in the same installation
