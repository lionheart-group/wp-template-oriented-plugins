# ReCAPTCHAConfig

Plugin-level configuration for Google reCAPTCHA v3.
Set this once with `Form::setRecaptcha()`, then enable per form with `recaptchaEnabled: true`.

## Usage

```php
use TofuPlugin\Structure\ReCAPTCHAConfig;
use TofuPlugin\Helpers\Form;

add_action('init', function () {
    // Register plugin-level reCAPTCHA config (call once, before Form::register())
    Form::setRecaptcha(new ReCAPTCHAConfig(
        siteKey:   'your-recaptcha-site-key',
        secretKey: 'your-recaptcha-secret-key',
        threshold: 0.5,
    ));

    // Enable reCAPTCHA per form
    Form::register(new \TofuPlugin\Structure\FormConfig(
        // …
        recaptchaEnabled: true,
    ));
});
```

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `siteKey` | `string` | Yes | - | Google reCAPTCHA v3 site key (public key). |
| `secretKey` | `string` | Yes | - | Google reCAPTCHA v3 secret key (private key). |
| `threshold` | `float` | No | `0.5` | Score threshold (0.0 to 1.0). Submissions with scores below this value are rejected. |

## Notes

- Get your reCAPTCHA keys from [Google reCAPTCHA Admin Console](https://www.google.com/recaptcha/admin).
- The threshold value ranges from 0.0 (likely a bot) to 1.0 (likely a human).
- A threshold of 0.5 is a reasonable default. Lower values are more permissive, higher values are stricter.
