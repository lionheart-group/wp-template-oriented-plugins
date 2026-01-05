
# TemplateConfig

Configuration for form template pages (input, confirm, result, error).

## Usage

```php
use TofuPlugin\Structure\TemplateConfig;

$template = new TemplateConfig(
    inputPath: '/contact/',
    confirmPath: '/contact/confirm/',
    resultPath: '/contact/result/',
);
```

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `inputPath` | `string` | Yes | - | URL path to the input page where users fill out the form. |
| `resultPath` | `string` | Yes | - | URL path to the result/thank-you page shown after successful submission. |
| `confirmPath` | `?string` | No | `null` | URL path to the confirmation page. Set to `null` to skip confirmation step. |

## Notes

- All paths are relative to the site's home URL.
- The confirmation page is optional. If `confirmPath` is `null`, the form will submit directly from the input page.
