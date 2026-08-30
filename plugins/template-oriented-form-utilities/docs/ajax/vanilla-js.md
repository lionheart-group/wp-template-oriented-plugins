# Vanilla JavaScript

A complete, framework-free example using the Fetch API.
Works in any WordPress theme — just enqueue a JS file and add the form HTML to a page template.

## Table of Contents

- [Setup](#setup)
- [Basic Two-Step Form (Input → Result)](#basic-two-step-form-input--result)
- [Three-Step Form (Input → Confirm → Result)](#three-step-form-input--confirm--result)
- [File Upload](#file-upload)
- [reCAPTCHA Integration](#recaptcha-integration)
- [Cloudflare Turnstile Integration](#cloudflare-turnstile-integration)
- [Complete Example](#complete-example)

---

## Setup

### 1. Register the form (`functions.php`)

```php
add_action('init', function () {
    \TofuPlugin\Helpers\Form::register(new \TofuPlugin\Structure\FormConfig(
        key:         'contact',
        name:        'Contact Form',
        ajaxEnabled: true,
        template: new \TofuPlugin\Structure\TemplateConfig(
            inputPath:  '/contact/',
            resultPath: '/contact/result/',
        ),
        mail:       $mailConfig,
        validation: new \TofuPlugin\Structure\ValidationConfig(
            allows:  ['name', 'email', 'message'],
            rules:   [
                'name'    => 'required|max:200',
                'email'   => 'required|email',
                'message' => 'required|max:2000',
            ],
            names: ['name' => 'Name', 'email' => 'Email', 'message' => 'Message'],
        ),
    ));
});
```

### 2. Create the HTML

Put this markup in any page template or block:

```html
<form id="contact-form" novalidate>
    <div class="field">
        <label for="name">Name</label>
        <input type="text" id="name" name="name">
        <ul class="errors" data-field="name"></ul>
    </div>

    <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email">
        <ul class="errors" data-field="email"></ul>
    </div>

    <div class="field">
        <label for="message">Message</label>
        <textarea id="message" name="message" rows="5"></textarea>
        <ul class="errors" data-field="message"></ul>
    </div>

    <button type="submit">Send</button>
</form>
```

---

## Basic Two-Step Form (Input → Result)

```javascript
const FORM_KEY = 'contact';
const BASE_URL = '/wp-json/tofu/v1/forms/' + FORM_KEY;

/**
 * Fetch a fresh TOFU nonce for the given action.
 */
async function fetchNonce(action = 'input') {
    const res = await fetch(`${BASE_URL}/nonce?action=${action}`, {
        credentials: 'same-origin',
    });
    if (!res.ok) throw new Error('Failed to fetch nonce');
    return res.json(); // { nonce, field_name, action, recaptcha, turnstile }
}

/**
 * Display validation errors returned by the API.
 */
function showErrors(errors) {
    // Clear previous errors
    document.querySelectorAll('.errors').forEach(el => (el.innerHTML = ''));

    for (const [field, messages] of Object.entries(errors)) {
        const container = document.querySelector(`.errors[data-field="${field}"]`);
        if (!container) continue;
        messages.forEach(msg => {
            const li = document.createElement('li');
            li.textContent = msg;
            container.appendChild(li);
        });
    }
}

/**
 * Handle form submission.
 */
document.getElementById('contact-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;

    // Fetch a fresh nonce on each submission
    const { nonce, field_name } = await fetchNonce('input');

    // Build FormData and append the nonce
    const body = new FormData(form);
    body.append(field_name, nonce);

    // POST to the input endpoint
    const res = await fetch(`${BASE_URL}/input`, {
        method: 'POST',
        body,
        credentials: 'same-origin',
    });
    const data = await res.json();

    if (data.success) {
        // data.next: 'confirm' | 'result' — map to your own client-side routes
        const routes = { confirm: '/contact/confirm/', result: '/contact/result/' };
        window.location.href = routes[data.next];
    } else {
        showErrors(data.errors);
    }
});
```

---

## Three-Step Form (Input → Confirm → Result)

Enable the confirm step in `FormConfig`. If you have WP template pages, use `confirmPath`.
For AJAX-only forms without a template, set `confirmStep: true`:

```php
// AJAX-only (no template pages)
new \TofuPlugin\Structure\FormConfig(
    key: 'contact', name: 'Contact Form',
    mail: $mailConfig, validation: $validationConfig,
    ajaxEnabled: true,
    confirmStep: true,
)

// Or with template pages (both flows supported)
new \TofuPlugin\Structure\FormConfig(
    key: 'contact', name: 'Contact Form',
    mail: $mailConfig, validation: $validationConfig,
    ajaxEnabled: true,
    template: new \TofuPlugin\Structure\TemplateConfig(
        inputPath:   '/contact/',
        confirmPath: '/contact/confirm/',
        resultPath:  '/contact/result/',
    ),
)
```

When a confirm step is configured, the `/input` response returns `"next": "confirm"`.
Show the confirm UI and submit to `/confirm` when the user approves.

On the confirm page, add a second form that posts to the `/confirm` endpoint:

```html
<!-- Confirm page HTML -->
<form id="confirm-form" novalidate>
    <button type="submit">Confirm & Send</button>
</form>
```

```javascript
const FORM_KEY = 'contact';
const BASE_URL = '/wp-json/tofu/v1/forms/' + FORM_KEY;

document.getElementById('confirm-form').addEventListener('submit', async (e) => {
    e.preventDefault();

    const { nonce, field_name } = await fetchNonce('confirm');

    const body = new FormData();
    body.append(field_name, nonce);

    const res = await fetch(`${BASE_URL}/confirm`, {
        method: 'POST',
        body,
        credentials: 'same-origin',
    });
    const data = await res.json();

    if (data.success) {
        // data.next is always 'result' here
        window.location.href = '/contact/result/';
    } else {
        // Errors on confirm step (e.g., session expired)
        console.error('Confirm failed:', data.errors);
    }
});
```

---

## File Upload

File inputs work with `FormData` automatically — no extra configuration needed.

```html
<div class="field">
    <label for="attachment">Attachment</label>
    <input type="file" id="attachment" name="attachment">
    <ul class="errors" data-field="attachment"></ul>
</div>
```

```javascript
// FormData automatically includes file inputs — nothing special required.
const body = new FormData(form); // includes file input
body.append(field_name, nonce);
```

Validation rules for files in `ValidationConfig`:

```php
rules: [
    'attachment' => 'required_file|max_mb:5|mime_type:application/pdf,image/jpeg',
],
```

---

## reCAPTCHA Integration

### 1. Register reCAPTCHA config and enable on `FormConfig`

```php
// In functions.php — register plugin-level config once. siteKey/secretKey
// typically come from wp-config.php or env vars so they differ per environment.
\TofuPlugin\Helpers\Form::setRecaptcha(new \TofuPlugin\Structure\ReCAPTCHAConfig(
    siteKey:   getenv('RECAPTCHA_SITE_KEY'),
    secretKey: getenv('RECAPTCHA_SECRET_KEY'),
    threshold: 0.5,
));

// Then enable per form
new \TofuPlugin\Structure\FormConfig(
    // …
    recaptchaEnabled: true,
)
```

### 2. Fetch the site key from the nonce endpoint and load the script dynamically

The `/nonce` response already carries the site key (see [Response format](./index.md#response-format)),
so the client never has to hardcode it — no separate value to keep in sync when your frontend and
backend are promoted through staging/production independently. Since the site key is only known
after that fetch resolves, load `api.js` dynamically instead of a static `<script src="...">` tag:

```javascript
let recaptchaScriptPromise = null;

/** Load the reCAPTCHA script for a given site key, once. */
function loadRecaptchaScript(siteKey) {
    if (!recaptchaScriptPromise) {
        recaptchaScriptPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(siteKey)}`;
            script.async = true;
            script.defer = true;
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Failed to load reCAPTCHA script'));
            document.head.appendChild(script);
        });
    }
    return recaptchaScriptPromise;
}

/**
 * siteKey comes from the /nonce response's `recaptcha.site_key` —
 * fetch a nonce first (see fetchNonce() above) and pass it in here.
 */
async function getRecaptchaToken(siteKey, action = 'submit') {
    await loadRecaptchaScript(siteKey);
    return new Promise((resolve, reject) => {
        grecaptcha.ready(() => {
            grecaptcha.execute(siteKey, { action })
                .then(resolve)
                .catch(reject);
        });
    });
}

// In your submit handler:
const { recaptcha } = await fetchNonce('input');
if (recaptcha) {
    const token = await getRecaptchaToken(recaptcha.site_key, 'submit');
    body.append(recaptcha.token_field_name, token); // '_tofu_recaptcha_token'
}
```

### 3. Display reCAPTCHA errors

The error field name is also returned as `recaptcha.token_field_name` (`_tofu_recaptcha_token`).
Add a container for it:

```html
<ul class="errors" data-field="_tofu_recaptcha_token"></ul>
```

---

## Cloudflare Turnstile Integration

### 1. Register Turnstile config and enable on `FormConfig`

```php
// In functions.php — register plugin-level config once. siteKey/secretKey
// typically come from wp-config.php or env vars so they differ per environment.
\TofuPlugin\Helpers\Form::setTurnstile(new \TofuPlugin\Structure\TurnstileConfig(
    siteKey:   getenv('TURNSTILE_SITE_KEY'),
    secretKey: getenv('TURNSTILE_SECRET_KEY'),
));

// Then enable per form
new \TofuPlugin\Structure\FormConfig(
    // …
    turnstileEnabled: true,
)
```

### 2. Fetch the site key from the nonce endpoint and render the widget explicitly

The same duplication problem applies to Turnstile: the site key is already configured server-side,
so fetch it from `/nonce`'s `turnstile.site_key` instead of hardcoding a `data-sitekey` attribute.
Because the value is only known after that fetch resolves, use Turnstile's
[Explicit Rendering](https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/#explicitly-render-the-turnstile-widget)
API instead of the implicit auto-render:

```html
<!-- In your <head> — render=explicit so it doesn't auto-render before the site key is known -->
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>

<!-- In your form — no data-sitekey here; it's set via turnstile.render() below -->
<div id="turnstile-container"></div>
<ul class="errors" data-field="_tofu_turnstile_token"></ul>
```

```javascript
let turnstileWidgetId = null;

/**
 * siteKey comes from the /nonce response's `turnstile.site_key`.
 * Call once the Turnstile script (`window.turnstile`) has loaded.
 */
function renderTurnstile(siteKey) {
    if (turnstileWidgetId !== null) return; // already rendered
    turnstileWidgetId = turnstile.render('#turnstile-container', {
        sitekey: siteKey,
        'response-field-name': '_tofu_turnstile_token',
    });
}

// Once you have the nonce response:
const { turnstile: turnstileConfig } = await fetchNonce('input');
if (turnstileConfig) {
    renderTurnstile(turnstileConfig.site_key);
}
```

Turnstile automatically populates a hidden input named `_tofu_turnstile_token` (set via the
`response-field-name` option above), which `FormData` picks up automatically once the widget
completes its challenge.

---

## Complete Example

A full working example with error handling, loading state, and reCAPTCHA.

```html
<form id="contact-form" novalidate>
    <div class="field">
        <label for="name">Name *</label>
        <input type="text" id="name" name="name" autocomplete="name">
        <ul class="errors" data-field="name"></ul>
    </div>

    <div class="field">
        <label for="email">Email *</label>
        <input type="email" id="email" name="email" autocomplete="email">
        <ul class="errors" data-field="email"></ul>
    </div>

    <div class="field">
        <label for="message">Message *</label>
        <textarea id="message" name="message" rows="5"></textarea>
        <ul class="errors" data-field="message"></ul>
    </div>

    <ul class="errors" data-field="_tofu_recaptcha_token"></ul>

    <button type="submit" id="submit-btn">Send Message</button>
</form>
```

```javascript
const FORM_KEY = 'contact';
const BASE_URL = '/wp-json/tofu/v1/forms/' + FORM_KEY;

async function fetchNonce(action = 'input') {
    const res = await fetch(`${BASE_URL}/nonce?action=${action}`, {
        credentials: 'same-origin',
    });
    if (!res.ok) throw new Error('Could not load form security token. Please reload the page.');
    return res.json(); // { nonce, field_name, action, recaptcha, turnstile }
}

function showErrors(errors) {
    document.querySelectorAll('.errors').forEach(el => (el.innerHTML = ''));
    for (const [field, messages] of Object.entries(errors || {})) {
        const el = document.querySelector(`.errors[data-field="${field}"]`);
        if (!el) continue;
        messages.forEach(msg => {
            const li = document.createElement('li');
            li.className = 'error-message';
            li.textContent = msg;
            el.appendChild(li);
        });
    }
}

// See "reCAPTCHA Integration" above — the site key comes from the nonce
// response, not a hardcoded constant, so there's nothing to keep in sync
// between environments.
let recaptchaScriptPromise = null;
function loadRecaptchaScript(siteKey) {
    if (!recaptchaScriptPromise) {
        recaptchaScriptPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(siteKey)}`;
            script.async = true;
            script.defer = true;
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Failed to load reCAPTCHA script'));
            document.head.appendChild(script);
        });
    }
    return recaptchaScriptPromise;
}

async function getRecaptchaToken(recaptcha) {
    if (!recaptcha) return null; // not enabled for this form
    await loadRecaptchaScript(recaptcha.site_key);
    return new Promise((resolve) => {
        grecaptcha.ready(() => {
            grecaptcha.execute(recaptcha.site_key, { action: 'submit' }).then(resolve);
        });
    });
}

const form      = document.getElementById('contact-form');
const submitBtn = document.getElementById('submit-btn');

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    submitBtn.disabled = true;
    submitBtn.textContent = 'Sending…';

    try {
        const { nonce, field_name, recaptcha } = await fetchNonce('input');
        const recaptchaToken = await getRecaptchaToken(recaptcha);

        const body = new FormData(form);
        body.append(field_name, nonce);
        if (recaptchaToken) body.append(recaptcha.token_field_name, recaptchaToken);

        const res  = await fetch(`${BASE_URL}/input`, {
            method: 'POST',
            body,
            credentials: 'same-origin',
        });
        const data = await res.json();

        if (data.success) {
            // data.next: 'confirm' | 'result' — navigate to the corresponding client route
            const routes = { confirm: '/contact/confirm/', result: '/contact/result/' };
            window.location.href = routes[data.next];
        } else {
            showErrors(data.errors);
        }
    } catch (err) {
        alert(err.message || 'An unexpected error occurred. Please try again.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Send Message';
    }
});
```
