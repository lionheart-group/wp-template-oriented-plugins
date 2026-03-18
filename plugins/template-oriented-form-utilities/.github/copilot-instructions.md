# TOFU (Template-Oriented Form Utilities) — Copilot Instructions

## Overview

TOFU is a WordPress plugin (PHP 8.1+, GPLv3+) for building multi-step forms using PHP templates.
It handles validation, session storage, file uploads, email notifications, and bot protection —
all configured in code (no WP admin UI).

**Namespace:** `TofuPlugin\` (PSR-4, mapped to `src/`)
**Entry point:** `template-oriented-form-utilities.php`
**Version:** 0.0.3

**Form flow:** Input Page → *(optional)* Confirm Page → Result Page

---

## Architecture

```
functions.php (FormConfig registration)
    └── TofuPlugin\Helpers\Form::register(FormConfig)
            └── TofuPlugin\Models\Form (manages state, actions, session)
                    ├── actionInput()   — validate + store session, redirect to confirm/result
                    ├── actionConfirm() — verify + send emails, redirect to result
                    └── redirect()      — to input | confirm | result
```

Custom endpoint: `?_tofu_key=<base64-JSON>` (registered via `add_rewrite_endpoint`)
Handled in `template_redirect` hook by `TofuPlugin\Init\Endpoint`.

---

## Key Source Files

| Path | Role |
|---|---|
| `src/Helpers/Form.php` | Static helper — form registration, rendering, value/error accessors |
| `src/Models/Form.php` | Core form model — action flow, validation, session, reCAPTCHA/Turnstile |
| `src/Models/Validation.php` | GUMP-based validation/filtering + custom file validators |
| `src/Models/Mail.php` | Email composition and sending via `wp_mail()` |
| `src/Models/Session.php` | DB model for encrypted session storage |
| `src/Models/Record.php` | DB model for optional form records |
| `src/Helpers/Session.php` | Session save/get/clear with encryption |
| `src/Helpers/Uploader.php` | Secure file upload to `tofu-uploads/` temp dir |
| `src/Helpers/ReCAPTCHA.php` | Google reCAPTCHA v3 token verification |
| `src/Helpers/Turnstile.php` | Cloudflare Turnstile token verification |
| `src/Helpers/Encryptor.php` | AES encryption for session data |
| `src/Helpers/Template.php` | `get_template_part()` wrapper + `{field}` placeholder replacement |
| `src/Helpers/Sanitizer.php` | Recursive `esc_html` / sanitization utilities |
| `src/Structure/FormConfig.php` | Form configuration (key, name, template, mail, validation, bot protection) |
| `src/Structure/TemplateConfig.php` | Page URL paths: `inputPath`, `confirmPath`, `resultPath` |
| `src/Structure/MailConfig.php` | Mail sender info + `MailRecipientsCollection` |
| `src/Structure/MailRecipientsConfig.php` | Per-recipient config: email, subject, body, CC, BCC |
| `src/Structure/ValidationConfig.php` | GUMP rules, filters, field names, messages, `after` hook |
| `src/Structure/ReCAPTCHAConfig.php` | reCAPTCHA site key, secret key, threshold |
| `src/Structure/TurnstileConfig.php` | Turnstile site key, secret key |
| `src/Init/Initializer.php` | Activation / deactivation / upgrade lifecycle |
| `src/Init/Migrate.php` | DB migration system |
| `src/Init/Endpoint.php` | Registers + handles `_tofu_key` custom endpoint |
| `src/Consts.php` | Plugin-wide constants |
| `src/Logger.php` | Monolog wrapper (logs only when `WP_DEBUG === true`) |
| `migrations/` | SQL migration files (run once on activation/upgrade) |

---

## Database Tables (created on activation)

| Table | Purpose |
|---|---|
| `wp_tofu_migrate` | Tracks executed migrations |
| `wp_tofu_sessions` | Encrypted session storage; unique on `(form_id, session_key)`; indexed on `expiration` |
| `wp_tofu_records` | Optional form record storage (not yet fully implemented) |

---

## Form Registration (`functions.php`)

```php
add_action('init', function () {
    \TofuPlugin\Helpers\Form::register(new \TofuPlugin\Structure\FormConfig(
        key:   'contact',
        name:  'Contact Form',

        template: new \TofuPlugin\Structure\TemplateConfig(
            inputPath:   '/contact/',
            confirmPath: '/contact/confirm/',  // null = skip confirm step
            resultPath:  '/contact/result/',
        ),

        mail: new \TofuPlugin\Structure\MailConfig(
            fromEmail: 'noreply@example.com',
            fromName:  'Example Website',
            recipients: new \TofuPlugin\Structure\MailRecipientsCollection([
                // Auto-reply to user — {field} placeholders are replaced with submitted values
                new \TofuPlugin\Structure\MailRecipientsConfig(
                    recipientEmail: '{email}',
                    subjectPath:    'form/contact/auto-reply-subject',
                    mailBodyPath:   'form/contact/auto-reply-body',
                ),
                // Admin notification
                new \TofuPlugin\Structure\MailRecipientsConfig(
                    recipientEmail:    'admin@example.com',
                    recipientCcEmail:  'manager@example.com',
                    subjectPath:       'form/contact/admin-subject',
                    mailBodyPath:      'form/contact/admin-body',
                ),
            ]),
        ),

        validation: new \TofuPlugin\Structure\ValidationConfig(
            allows:   ['name', 'email', 'message'],  // whitelist — other fields are ignored
            rules:    [
                'name'    => 'required|max_len:200',
                'email'   => 'required|valid_email',
                'message' => 'required|max_len:2000',
            ],
            filters:  [
                'name'    => 'trim|sanitize_string',
                'email'   => 'trim|sanitize_email|lower_case',
                'message' => 'trim|sanitize_string',
            ],
            names:    ['name' => 'Full Name', 'email' => 'Email Address'],
            messages: [
                'name'  => ['required' => 'Please enter your name.'],
                'email' => ['valid_email' => 'Enter a valid email address.'],
            ],
            after: function ($values, $errors) {
                // Custom post-validation hook
                // $values->getValue('field'), $errors->addError('field', 'msg')
            },
        ),

        // Optional bot protection — use reCAPTCHA OR Turnstile, not both
        recaptcha: new \TofuPlugin\Structure\ReCAPTCHAConfig(
            siteKey: 'SITE_KEY', secretKey: 'SECRET_KEY', threshold: 0.5,
        ),
        // turnstile: new \TofuPlugin\Structure\TurnstileConfig(siteKey: '...', secretKey: '...'),
    ));
});
```

---

## Page Templates

### Input Page

```php
use TofuPlugin\Helpers\Form;
$formKey = 'contact'; $formAction = 'input';
Form::embedScript($formKey); // MUST be before get_header()
get_header();

echo Form::formOpen($formKey, $formAction);

// Text input with error display
?><input type="text" name="name" value="<?php echo Form::value($formKey, 'name'); ?>"><?php
foreach (Form::errors($formKey, 'name') as $msg) echo '<p>' . esc_html($msg) . '</p>';

// Checkbox
?><input type="checkbox" name="agree" value="yes" <?php echo Form::checked($formKey, 'agree', 'yes'); ?>><?php

// Select
?><option value="foo" <?php echo Form::selected($formKey, 'type', 'foo'); ?>>Foo</option><?php

// Cloudflare Turnstile widget (if configured)
if (Form::hasTurnstile($formKey)) echo Form::turnstileWidget($formKey);

echo Form::formClose($formKey, $formAction); // embeds nonce + reCAPTCHA hidden field
get_footer();
```

### Confirm Page

```php
use TofuPlugin\Helpers\Form;
$formKey = 'contact'; $formAction = 'confirm';
if (!Form::verifySession($formKey)) Form::redirect($formKey, 'input'); // guard
Form::embedScript($formKey);
get_header();

echo Form::value($formKey, 'name');  // display submitted values

echo Form::formOpen($formKey, $formAction);
// No fields needed — session carries data
echo Form::formClose($formKey, $formAction);
get_footer();
```

### Result Page

```php
use TofuPlugin\Helpers\Form;
$formKey = 'contact';
if (!Form::verifySubmit($formKey)) Form::redirect($formKey, 'input'); // prevent direct access
get_header();
// Show success message
get_footer();
```

---

## File Uploads

```php
// In ValidationConfig
allows: ['attachment'],
rules:  ['attachment' => 'custom_required_file|max_mb:5|mime_type:application/pdf,image/jpeg'],
```

```php
// In input template
echo '<input type="file" name="attachment">';
echo Form::fileHidden($formKey, 'attachment');          // persists file across confirm step
echo Form::fileRemoveButton($formKey, 'attachment', 'Remove');
```

Files are stored temporarily in `wp-content/uploads/tofu-uploads/`, attached to emails on confirm, then deleted.

---

## Helper Method Reference

| Method | Returns | Description |
|---|---|---|
| `Form::register(FormConfig)` | void | Register a form (once, in `init`) |
| `Form::embedScript($key)` | void | Enqueue JS (reCAPTCHA / Turnstile / file input) — before `get_header()` |
| `Form::formOpen($key, $action, $attrs)` | string | `<form>` tag with action URL |
| `Form::formClose($key, $action)` | string | Hidden fields + nonce + `</form>` |
| `Form::value($key, $field, $raw)` | mixed | Sanitized (or raw) field value |
| `Form::hasError($key, $field)` | bool | Field has validation errors |
| `Form::errors($key, $field)` | string[] | Error messages for a field |
| `Form::checked($key, $field, $value)` | string | `"checked"` if value matches |
| `Form::selected($key, $field, $value)` | string | `"selected"` if value matches |
| `Form::hasFile($key, $field)` | bool | File uploaded for field |
| `Form::file($key, $field)` | ?UploadedFile | Uploaded file object |
| `Form::fileHidden($key, $field)` | string | Hidden input to carry file across steps |
| `Form::fileRemoveButton($key, $field, $label)` | string | Remove-file button |
| `Form::hasTurnstile($key)` | bool | Turnstile configured |
| `Form::turnstileWidget($key, $attrs)` | string | Cloudflare Turnstile `<div>` |
| `Form::verifySession($key)` | bool | Session data is valid (use on confirm page) |
| `Form::verifySubmit($key)` | bool | Submission completed (use on result page) |
| `Form::redirect($key, $action)` | void | Redirect to `input`, `confirm`, or `result` |

---

## Custom Validation Rules

| Rule | Usage | Description |
|---|---|---|
| `custom_required_file` | `'file' => 'custom_required_file'` | Required file (new upload or session-persisted) |
| `max_mb` | `'file' => 'max_mb:5'` | Max file size in MB |
| `mime_type` | `'file' => 'mime_type:image/jpeg,image/png'` | Allowed MIME types |

Standard GUMP rules also apply: `required`, `valid_email`, `max_len`, `min_len`, `numeric`, `alpha`, `alpha_numeric`, etc.
Standard GUMP filters: `trim`, `sanitize_string`, `sanitize_email`, `lower_case`, `sanitize_numbers`, etc.

---

## Constants (`src/Consts.php`)

| Constant | Value |
|---|---|
| `QUERY_KEY` | `_tofu_key` |
| `SESSION_COOKIE_KEY` | `_tofu_session_key` |
| `SESSION_EXPIRY` | `86400` (24 h) |
| `NONCE_FORMAT` | `_tofu_%s_nonce` |
| `UPLOAD_SUBFOLDER` | `tofu-uploads` |
| `LOG_SUBFOLDER` | `tofu-logs` |
| `GARBAGE_COLLECTION_PERCENTAGE` | `10` |

---

## Development

```bash
composer install          # install dependencies
composer phpstan          # static analysis
composer test             # PHPUnit tests
composer check            # phpstan + test + test:scoped
composer build            # PHP Scoper production build → build/
```

**Logging:** Enabled only when `WP_DEBUG === true`. Logs to `wp-content/uploads/tofu-logs/tofu.log`.

---

## Security Conventions

- Always verify nonce with `Form::formClose()` (automatic) — never skip
- Use `allows` in `ValidationConfig` as a field whitelist to prevent mass-assignment
- Never output raw `Form::value()` inside HTML attributes — use `$raw = false` (default)
- Use `Form::verifySession()` on confirm page and `Form::verifySubmit()` on result page
- Store secrets (reCAPTCHA / Turnstile keys) in environment variables or `wp-config.php`, not hardcoded
- For AJAX: `ajaxEnabled: false` by default — never expose a form over REST unless explicitly opted in
- For cross-origin AJAX: `corsOrigins` must list exact origins; wildcard `*` is not supported

---

## AJAX / REST API Endpoint

TOFU includes an opt-in WP REST API layer for AJAX and headless (separate-domain) frontends.

### Enable per form

```php
\TofuPlugin\Helpers\Form::register(new \TofuPlugin\Structure\FormConfig(
    key:          'contact',
    ajaxEnabled:  true,                                    // required to expose REST routes
    corsOrigins:  ['https://frontend.example.com'],        // omit = same-origin only
    // … rest of config unchanged
));
```

### Routes (registered only for forms with `ajaxEnabled: true`)

| Method | Route | Description |
|---|---|---|
| `GET` | `/wp-json/tofu/v1/forms/{key}/nonce?action=input` | Get a fresh TOFU nonce |
| `POST` | `/wp-json/tofu/v1/forms/{key}/input` | Submit input step |
| `POST` | `/wp-json/tofu/v1/forms/{key}/confirm` | Submit confirm step |

### JSON responses

**Success (HTTP 200):**
```json
{ "success": true, "next": "confirm", "redirect": "/contact/confirm/" }
```
**Validation error (HTTP 422):**
```json
{ "success": false, "next": "input", "errors": { "name": ["Please enter your name."] } }
```
**Nonce (GET):**
```json
{ "nonce": "abc123…", "field_name": "_tofu_contact_nonce", "action": "input" }
```

### Client-side usage

```javascript
// 1. Fetch nonce
const { nonce, field_name } = await fetch('/wp-json/tofu/v1/forms/contact/nonce')
    .then(r => r.json());

// 2. Submit form (supports file uploads via FormData)
const body = new FormData(document.querySelector('form'));
body.append(field_name, nonce);

const res = await fetch('/wp-json/tofu/v1/forms/contact/input', {
    method: 'POST',
    body,
    credentials: 'include',  // required for cross-origin
});
const data = await res.json();
if (data.success) { /* data.next: 'confirm'|'result' */ window.location = data.redirect; }
else              { /* show data.errors */ }
```

### Cross-origin (headless / separate domain)

- Set `corsOrigins: ['https://your-frontend.com']` in `FormConfig`
- Session cookie is automatically issued with `SameSite=None; Secure` (requires HTTPS)
- Client must send `credentials: 'include'` in fetch/axios requests
- `corsOrigins: []` (default) = same-origin only, `SameSite=Lax` cookie

### New FormConfig parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| `ajaxEnabled` | `bool` | `false` | Enable REST API routes for this form |
| `corsOrigins` | `string[]` | `[]` | Allowed CORS origins; empty = same-origin only |

### Key source files (REST)

| File | Role |
|---|---|
| `src/Init/RestEndpoint.php` | Route registration, CORS headers, nonce/input/confirm handlers |
| `src/Models/Form.php` | `processInput()`, `processConfirm()` — pure processing, no redirect/wp_die |
| `src/Helpers/Session.php` | `enableCors()` — sets `SameSite=None; Secure` for cross-origin cookies |
| `src/Consts.php` | `REST_NAMESPACE = 'tofu/v1'`, `REST_NONCE_ACTION_FORMAT` |
