# FormConfig

The main configuration class that holds all settings for a form.

## Usage

```php
use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Helpers\Form;

// Traditional (redirect-based) form — template required
Form::register(new FormConfig(
    key:        'contact',
    name:       'Contact Form',
    mail:       $mailConfig,
    validation: $validationConfig,
    template:   $templateConfig,
    saveToDatabase: false,
    recaptchaEnabled: true, // optional; requires Form::setRecaptcha() called beforehand
    turnstileEnabled: true, // optional; requires Form::setTurnstile() called beforehand
));

// AJAX / headless form — template optional
Form::register(new FormConfig(
    key:         'contact',
    name:        'Contact Form',
    mail:        $mailConfig,
    validation:  $validationConfig,
    ajaxEnabled: true,
    confirmStep: true,           // optional: enable confirm step without template
    corsOrigins: ['https://frontend.example.com'], // optional: cross-origin
));
```

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `key` | `string` | Yes | - | Unique identifier for the form. Used to reference the form throughout the application. |
| `name` | `string` | Yes | - | Human-readable name for the form. |
| `mail` | [`MailConfig`](./mailconfig.md) | Yes | - | Mail configuration for sending emails. |
| `validation` | [`ValidationConfig`](./validationconfig.md) | Yes | - | Validation rules and settings. |
| `template` | [`?TemplateConfig`](./templateconfig.md) | No | `null` | Template configuration for WP page URLs. Required for the traditional redirect flow; can be omitted for AJAX-only forms. |
| `saveToDatabase` | `bool` | No | `false` | Whether to save form submissions to the database. |
| `recaptchaEnabled` | `bool` | No | `false` | Enable Google reCAPTCHA v3 for this form. Requires `Form::setRecaptcha()` to be called with plugin-level config. |
| `turnstileEnabled` | `bool` | No | `false` | Enable Cloudflare Turnstile for this form. Requires `Form::setTurnstile()` to be called with plugin-level config. |
| `ajaxEnabled` | `bool` | No | `false` | Enable the WP REST API endpoint for this form. See [AJAX / Headless Mode](../ajax/index.md). |
| `corsOrigins` | `string[]` | No | `[]` | Allowed CORS origins for the REST endpoint. Empty = same-origin only. |
| `confirmStep` | `bool` | No | `false` | Enable the confirm step. For template-based forms, also set `template->confirmPath` so the redirect URL is available. |
| `dynamicTemplate` | `bool` | No | `false` | Set to `true` when this form's paths are supplied per-request via `Form::setTemplate()` instead of a static `template`. Lets `confirmStep: true` be registered without a static `template->confirmPath`. See [Dynamic Overrides](./templateconfig.md#dynamic-overrides-per-page-embeds). |

## Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `hasConfirmStep()` | `bool` | Returns `true` if this form has a confirm step. Determined solely by the `confirmStep` flag. |
