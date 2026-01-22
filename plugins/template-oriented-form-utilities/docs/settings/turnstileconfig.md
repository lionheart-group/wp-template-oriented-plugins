# TurnstileConfig

Configuration for Cloudflare Turnstile integration.

## Usage

```php
use TofuPlugin\Structure\TurnstileConfig;

$turnstile = new TurnstileConfig(
    siteKey: 'your-turnstile-site-key',
    secretKey: 'your-turnstile-secret-key',
);
```

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `siteKey` | `string` | Yes | - | Cloudflare Turnstile site key (public key). |
| `secretKey` | `string` | Yes | - | Cloudflare Turnstile secret key (private key). |

## Notes

- Get your Cloudflare Turnstile keys from [Cloudflare Turnstile Admin Console](https://www.cloudflare.com/application-services/products/turnstile/).
