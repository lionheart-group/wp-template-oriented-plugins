# Headless / Cross-Origin Setup

This guide covers setting up TOFU when the frontend lives on a **different domain** from
WordPress — for example, a Next.js app at `https://frontend.example.com` calling a WP
backend at `https://wp.example.com`.

## Table of Contents

- [How Cross-Origin Works](#how-cross-origin-works)
- [Requirements](#requirements)
- [Step 1 — WordPress Configuration](#step-1--wordpress-configuration)
- [Step 2 — Browser Client](#step-2--browser-client)
- [Step 3 — WordPress CORS (Additional Settings)](#step-3--wordpress-cors-additional-settings)
- [Alternative: API Proxy (No CORS Needed)](#alternative-api-proxy-no-cors-needed)
- [Troubleshooting](#troubleshooting)

---

## How Cross-Origin Works

When the browser submits to a different domain:

1. The browser sends a **preflight OPTIONS request** — TOFU responds with CORS headers
2. The browser sends the **actual POST** with `credentials: 'include'`
3. TOFU sets the session cookie with `SameSite=None; Secure` on the **input/confirm response** (not on the nonce request)
4. The browser stores the cookie and sends it on subsequent requests

```
Frontend (https://frontend.example.com)
    │
    ├─ GET /wp-json/tofu/v1/forms/contact/nonce
    │   ← Access-Control-Allow-Origin: https://frontend.example.com
    │   ← Access-Control-Allow-Credentials: true
    │   ← { "nonce": "…", "field_name": "…", "action": "input", "recaptcha": null, "turnstile": null }
    │   (no Set-Cookie yet — session is not started by the nonce endpoint)
    │   (recaptcha/turnstile carry the site key when enabled for this form — see
    │    Response format in ajax/index.md — so the frontend build doesn't need its own
    │    per-environment copy of the key)
    │
    ├─ POST /wp-json/tofu/v1/forms/contact/input
    │   → Cookie: (none on first request)
    │   ← Access-Control-Allow-Origin: https://frontend.example.com
    │   ← Set-Cookie: _tofu_session_key=…; SameSite=None; Secure
    │   ← { "success": true, "next": "result" }
    │
    └─ POST /wp-json/tofu/v1/forms/contact/confirm  (if confirm step is enabled)
        → Cookie: _tofu_session_key=…
        ← { "success": true, "next": "result" }
```

---

## Requirements

| Requirement | Why |
|---|---|
| **HTTPS on both domains** | `SameSite=None` cookies require `Secure`, which requires HTTPS |
| `ajaxEnabled: true` in `FormConfig` | Routes are not registered without this |
| `corsOrigins` set to frontend origin | CORS headers are only sent for listed origins |
| `credentials: 'include'` in all fetch calls | Sends/receives cookies cross-origin |

---

## Step 1 — WordPress Configuration

Add `ajaxEnabled: true` and `corsOrigins` to your `FormConfig` in `functions.php`.
`template` is optional — omit it for pure headless forms:

```php
add_action('init', function () {
    \TofuPlugin\Helpers\Form::register(new \TofuPlugin\Structure\FormConfig(
        key:         'contact',
        name:        'Contact Form',
        mail:        $mailConfig,
        validation:  $validationConfig,
        ajaxEnabled: true,

        // List every frontend origin that is allowed to submit this form.
        // Must be exact — no wildcards.
        corsOrigins: [
            'https://frontend.example.com',
            'https://staging.frontend.example.com',  // staging, if needed
        ],

        // Enable confirm step (no template pages required)
        // confirmStep: true,
    ));
});
```

If you also need the traditional WP page flow, add `template` with full frontend URLs:

```php
        template: new \TofuPlugin\Structure\TemplateConfig(
            inputPath:  'https://frontend.example.com/contact/',
            resultPath: 'https://frontend.example.com/contact/result/',
            // confirmPath: 'https://frontend.example.com/contact/confirm/',
        ),
```

---

## Step 2 — Browser Client

Always pass `credentials: 'include'` so the browser sends and stores the session cookie.

### Vanilla JS

```javascript
const WP_BASE   = 'https://wp.example.com';
const FORM_KEY  = 'contact';
const TOFU_BASE = `${WP_BASE}/wp-json/tofu/v1/forms/${FORM_KEY}`;

async function fetchNonce(action = 'input') {
    const res = await fetch(`${TOFU_BASE}/nonce?action=${action}`, {
        credentials: 'include',   // ← required for cross-origin
    });
    return res.json();
}

async function submitInput(formElement) {
    const { nonce, field_name } = await fetchNonce('input');

    const body = new FormData(formElement);
    body.append(field_name, nonce);

    const res = await fetch(`${TOFU_BASE}/input`, {
        method:      'POST',
        body,
        credentials: 'include',  // ← required
    });
    return res.json();
}
```

### Axios

```javascript
import axios from 'axios';

const tofuAxios = axios.create({
    baseURL:       'https://wp.example.com/wp-json/tofu/v1',
    withCredentials: true,  // equivalent to credentials: 'include'
});

async function submitInput(formElement) {
    const { data: { nonce, field_name } } = await tofuAxios.get(`/forms/contact/nonce?action=input`);

    const body = new FormData(formElement);
    body.append(field_name, nonce);

    const { data } = await tofuAxios.post('/forms/contact/input', body);
    return data;
}
```

---

## Step 3 — WordPress CORS (Additional Settings)

WordPress itself sometimes adds its own `Access-Control-Allow-Origin: *` header via the
REST API. This can conflict with TOFU's per-form CORS headers (which must send the
specific origin, not `*`, when `credentials` is involved).

Add this to your theme's `functions.php` to remove the WP default and let TOFU control CORS:

```php
// Remove WordPress's default wildcard CORS header for the REST API
add_filter('rest_pre_serve_request', function ($served, $result, $request) {
    // Only strip the header — TOFU's RestEndpoint adds the correct one
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header_remove('Access-Control-Allow-Origin');
    }
    return $served;
}, 5, 3);
```

> This filter runs before TOFU's handlers (priority 5 < TOFU's default priority),
> so TOFU's `applyCorsHeaders()` method sets the correct, credentialed header afterward.

---

## Alternative: API Proxy (No CORS Needed)

If you can configure your frontend server to **proxy** requests to WordPress, the browser
always talks to its own origin and CORS is never involved.

### Next.js (`next.config.js`)

```js
module.exports = {
    async rewrites() {
        return [
            {
                source:      '/wp-json/:path*',
                destination: 'https://wp.example.com/wp-json/:path*',
            },
        ];
    },
};
```

With this proxy:
- **Omit** `corsOrigins` from `FormConfig` (or leave it empty)
- Use `credentials: 'same-origin'` instead of `'include'`
- The cookie `SameSite` stays at `Lax` (default)

### Nuxt 3 (`nuxt.config.ts`)

```ts
export default defineNuxtConfig({
    routeRules: {
        '/wp-json/**': { proxy: 'https://wp.example.com/wp-json/**' },
    },
});
```

---

## Troubleshooting

### Cookie is not being sent on the second request

- Verify `credentials: 'include'` is set on **all** fetch calls, including the nonce fetch
- Verify WordPress is running over **HTTPS** (required for `SameSite=None; Secure`)
- Check that the `corsOrigins` array in `FormConfig` exactly matches the `Origin` header
  the browser sends (e.g., `https://frontend.example.com` — no trailing slash)

### `Access-Control-Allow-Origin` header missing or wrong

- Confirm `ajaxEnabled: true` is set
- Confirm the origin in `corsOrigins` exactly matches (check browser DevTools → Network → Request Headers → `Origin`)
- Make sure `flush rewrite rules` was run after enabling AJAX mode
  (deactivate and reactivate the plugin, or call `flush_rewrite_rules()` once)

### `403 Forbidden` — nonce verification failed

- The nonce must be fetched fresh for each submission — do not cache or reuse nonces
- Ensure the nonce is appended to the `FormData` with the correct `field_name` returned
  by the `/nonce` endpoint
- WordPress nonces are valid for **12–24 hours** by default (WordPress core's `nonce_life`
  filter — this plugin doesn't change it), which is *shorter* than this plugin's 24-hour
  session (see "Sessions last 24 hours" below). A form left open longer than that will fail
  nonce verification even though the session itself is still valid — re-fetch the nonce
  right before submitting if the user may have left the page open for a long time
- The nonce is also tied to the visitor's login state at the moment it was issued —
  logging in or out while the form is open invalidates any nonce fetched before that,
  regardless of elapsed time

### Session expired on the confirm page

- Sessions last 24 hours — this should not occur in normal usage
- If using SSR, do not server-side-render the form values; fetch them client-side where
  the session cookie is available
- Check that cookies are not blocked by browser privacy settings
  (`SameSite=None` requires the user's browser to allow third-party cookies)

### HTTPS / local development

`SameSite=None; Secure` requires HTTPS. For local development with cross-origin:

- Use [mkcert](https://github.com/FiloSottile/mkcert) to create a local HTTPS certificate
- Or use the [API proxy approach](#alternative-api-proxy-no-cors-needed) which does not require `SameSite=None`
