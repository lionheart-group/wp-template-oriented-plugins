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
                'name'    => 'required|max_len:200',
                'email'   => 'required|valid_email',
                'message' => 'required|max_len:2000',
            ],
            filters: [
                'name'    => 'trim|sanitize_string',
                'email'   => 'trim|sanitize_email|lower_case',
                'message' => 'trim|sanitize_string',
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
    return res.json(); // { nonce, field_name, action }
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
        // Redirect to result (or confirm) page
        window.location.href = data.redirect;
    } else {
        showErrors(data.errors);
    }
});
```

---

## Three-Step Form (Input → Confirm → Result)

Enable the confirm step by adding `confirmPath` to `TemplateConfig`:

```php
template: new \TofuPlugin\Structure\TemplateConfig(
    inputPath:   '/contact/',
    confirmPath: '/contact/confirm/',
    resultPath:  '/contact/result/',
),
```

The `/input` response will now return `"redirect": "/contact/confirm/"` on success.
Load the confirm page normally — TOFU stores submitted values in the session.

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
        window.location.href = data.redirect;
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
    'attachment' => 'custom_required_file|max_mb:5|mime_type:application/pdf,image/jpeg',
],
```

---

## reCAPTCHA Integration

### 1. Add reCAPTCHA config to `FormConfig`

```php
recaptcha: new \TofuPlugin\Structure\ReCAPTCHAConfig(
    siteKey:   'YOUR_SITE_KEY',
    secretKey: 'YOUR_SECRET_KEY',
    threshold: 0.5,
),
```

### 2. Load the reCAPTCHA script and generate a token

```html
<!-- In your <head> -->
<script src="https://www.google.com/recaptcha/api.js?render=YOUR_SITE_KEY" async defer></script>
```

```javascript
const RECAPTCHA_SITE_KEY = 'YOUR_SITE_KEY';

async function getRecaptchaToken(action = 'submit') {
    return new Promise((resolve, reject) => {
        grecaptcha.ready(() => {
            grecaptcha.execute(RECAPTCHA_SITE_KEY, { action })
                .then(resolve)
                .catch(reject);
        });
    });
}

// In your submit handler:
const token = await getRecaptchaToken('submit');
body.append('_tofu_recaptcha_token', token);
```

### 3. Display reCAPTCHA errors

The error field name is `_tofu_recaptcha_token`. Add a container for it:

```html
<ul class="errors" data-field="_tofu_recaptcha_token"></ul>
```

---

## Cloudflare Turnstile Integration

### 1. Add Turnstile config to `FormConfig`

```php
turnstile: new \TofuPlugin\Structure\TurnstileConfig(
    siteKey:   'YOUR_SITE_KEY',
    secretKey: 'YOUR_SECRET_KEY',
),
```

### 2. Add the Turnstile widget

```html
<!-- In your <head> -->
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

<!-- In your form -->
<div class="cf-turnstile"
     data-sitekey="YOUR_SITE_KEY"
     data-response-field-name="_tofu_turnstile_token">
</div>
<ul class="errors" data-field="_tofu_turnstile_token"></ul>
```

Turnstile automatically populates a hidden input named `_tofu_turnstile_token`,
which `FormData` picks up automatically.

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
const FORM_KEY        = 'contact';
const BASE_URL        = '/wp-json/tofu/v1/forms/' + FORM_KEY;
const RECAPTCHA_KEY   = 'YOUR_SITE_KEY'; // set '' to disable

async function fetchNonce(action = 'input') {
    const res = await fetch(`${BASE_URL}/nonce?action=${action}`, {
        credentials: 'same-origin',
    });
    if (!res.ok) throw new Error('Could not load form security token. Please reload the page.');
    return res.json();
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

async function getRecaptchaToken() {
    if (!RECAPTCHA_KEY || typeof grecaptcha === 'undefined') return null;
    return new Promise((resolve) => {
        grecaptcha.ready(() => {
            grecaptcha.execute(RECAPTCHA_KEY, { action: 'submit' }).then(resolve);
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
        const [{ nonce, field_name }, recaptchaToken] = await Promise.all([
            fetchNonce('input'),
            getRecaptchaToken(),
        ]);

        const body = new FormData(form);
        body.append(field_name, nonce);
        if (recaptchaToken) body.append('_tofu_recaptcha_token', recaptchaToken);

        const res  = await fetch(`${BASE_URL}/input`, {
            method: 'POST',
            body,
            credentials: 'same-origin',
        });
        const data = await res.json();

        if (data.success) {
            window.location.href = data.redirect;
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
