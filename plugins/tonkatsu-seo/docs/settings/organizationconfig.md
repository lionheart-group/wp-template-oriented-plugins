# OrganizationConfig

The organization behind the site, output as the `Organization` node of the JSON-LD graph and
referenced as the `WebSite` node's `publisher`.

## Usage

```php
use TonkatsuPlugin\Structure\OrganizationConfig;
use TonkatsuPlugin\Structure\SiteConfig;

new SiteConfig(
    organization: new OrganizationConfig(
        name: '株式会社サンプル',
        url: 'https://example.com/',
        logo: get_theme_file_uri('images/logo.png'),
        sameAs: [
            'https://x.com/example',
            'https://www.facebook.com/example',
        ],
    ),
);
```

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `name` | `string` | Yes | - | Organization name. Must not be empty. |
| `url` | `?string` | No | `null` | Organization URL. `null` uses the home URL. |
| `logo` | `?string` | No | `null` | Logo image URL (absolute, or root-relative). |
| `sameAs` | `string[]` | No | `[]` | Profile URLs elsewhere — social accounts, Wikipedia. Every entry must be a valid URL. |
