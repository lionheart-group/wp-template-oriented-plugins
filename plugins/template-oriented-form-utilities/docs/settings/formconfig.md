# FormConfig

The main configuration class that holds all settings for a form.

## Usage

```php
use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Helpers\Form;

Form::register(new FormConfig(
    key: 'contact',
    name: 'Contact Form',
    template: $templateConfig,
    mail: $mailConfig,
    validation: $validationConfig,
    saveToDatabase: false,
    recaptcha: $recaptchaConfig, // optional
    turnstile: $turnstileConfig, // optional
));
```

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `key` | `string` | Yes | - | Unique identifier for the form. Used to reference the form throughout the application. |
| `name` | `string` | Yes | - | Human-readable name for the form. |
| `template` | [`TemplateConfig`](./templateconfig.md) | Yes | - | Template configuration for form pages. |
| `mail` | [`MailConfig`](./mailconfig.md) | Yes | - | Mail configuration for sending emails. |
| `validation` | [`ValidationConfig`](./validationconfig.md) | Yes | - | Validation rules and settings. |
| `saveToDatabase` | `bool` | No | `false` | Whether to save form submissions to the database. |
| `recaptcha` | [`?ReCAPTCHAConfig`](./recaptchaconfig.md) | No | `null` | Google reCAPTCHA v3 configuration. |
| `turnstile` | [`?TurnstileConfig`](./turnstileconfig.md) | No | `null` | Cloudflare Turnstile configuration. |
