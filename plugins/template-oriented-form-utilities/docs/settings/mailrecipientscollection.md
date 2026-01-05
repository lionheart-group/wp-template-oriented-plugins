# MailRecipientsCollection

A collection of `MailRecipientsConfig` objects that define all email recipients for a form.

## Usage

```php
use TofuPlugin\Structure\MailRecipientsCollection;
use TofuPlugin\Structure\MailRecipientsConfig;

$recipients = new MailRecipientsCollection([
    new MailRecipientsConfig(/* auto-reply config */),
    new MailRecipientsConfig(/* admin notification config */),
]);
```

## Properties

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `recipients` | [`MailRecipientsConfig[]`](./mailrecipientsconfig.md) | Yes | Array of recipient configurations. |
