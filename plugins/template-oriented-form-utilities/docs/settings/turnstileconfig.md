# TurnstileConfig

Plugin-level configuration for Cloudflare Turnstile.
Set this once with `Form::setTurnstile()`, then enable per form with `turnstileEnabled: true`.

## Usage

```php
use TofuPlugin\Structure\TurnstileConfig;
use TofuPlugin\Helpers\Form;

add_action('init', function () {
    // Register plugin-level Turnstile config (call once, before Form::register())
    Form::setTurnstile(new TurnstileConfig(
        siteKey:   'your-turnstile-site-key',
        secretKey: 'your-turnstile-secret-key',
    ));

    // Enable Turnstile per form
    Form::register(new \TofuPlugin\Structure\FormConfig(
        // …
        turnstileEnabled: true,
    ));
});
```

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `siteKey` | `string` | Yes | - | Cloudflare Turnstile site key (public key). |
| `secretKey` | `string` | Yes | - | Cloudflare Turnstile secret key (private key). |

## Notes

- Get your Cloudflare Turnstile keys from [Cloudflare Turnstile Admin Console](https://www.cloudflare.com/application-services/products/turnstile/).
