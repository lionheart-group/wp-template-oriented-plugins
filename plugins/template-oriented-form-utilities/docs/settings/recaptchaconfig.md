# ReCAPTCHAConfig

Configuration for Google reCAPTCHA v3 integration.

## Usage

```php
use TofuPlugin\Structure\ReCAPTCHAConfig;

$recaptcha = new ReCAPTCHAConfig(
    siteKey: 'your-recaptcha-site-key',
    secretKey: 'your-recaptcha-secret-key',
    threshold: 0.5,
);
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
