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
        type: 'Corporation',
        alternateName: 'Sample Co., Ltd.',
        address: [
            'postalCode'      => '460-0003',
            'addressCountry'  => 'JP',
            'addressRegion'   => '愛知県',
            'addressLocality' => '名古屋市中区',
            'streetAddress'   => '錦3丁目1-1',
        ],
        telephone: '+81-52-000-0000',
        faxNumber: '+81-52-000-0001',
        foundingDate: '1983',
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
| `type` | `string` | No | `'Organization'` | The node's schema.org type: `Organization` or a subtype such as `Corporation`, `LocalBusiness` or `NGO`. Letters and digits only. |
| `alternateName` | `?string` | No | `null` | Another name, e.g. the name in English. |
| `address` | `array<string, string>` | No | `[]` | Printed as a `PostalAddress`. Keys: `postalCode`, `addressCountry`, `addressRegion`, `addressLocality`, `streetAddress`; any other key, or an empty value, is rejected. |
| `telephone` | `?string` | No | `null` | Phone number, preferably in international format (`+81-52-000-0000`). |
| `faxNumber` | `?string` | No | `null` | Fax number, preferably in international format. |
| `foundingDate` | `?string` | No | `null` | `YYYY`, `YYYY-MM` or `YYYY-MM-DD`. |

Properties left at their defaults are not printed.
