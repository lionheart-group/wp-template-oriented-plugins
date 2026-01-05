# MailConfig

Configuration for email sending settings.

## Usage

```php
use TofuPlugin\Structure\MailConfig;
use TofuPlugin\Structure\MailRecipientsCollection;

$mail = new MailConfig(
    fromEmail: 'noreply@example.com',
    fromName: 'Example Site',
    recipients: new MailRecipientsCollection([
        // recipient configurations...
    ]),
);
```

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `fromEmail` | `string` | Yes | - | The "From" email address for outgoing emails. |
| `fromName` | `string` | Yes | - | The "From" name displayed in email clients. |
| `recipients` | [`MailRecipientsCollection`](./mailrecipientscollection.md) | Yes | - | Collection of mail recipient configurations. |

