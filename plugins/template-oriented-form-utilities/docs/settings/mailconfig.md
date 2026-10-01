# MailConfig

Configuration for email sending settings.

## Usage

```php
use TofuPlugin\Structure\MailConfig;
use TofuPlugin\Structure\MailRecipientsCollection;

$mail = new MailConfig(
    fromEmail: 'noreply@example.com',
    fromName: 'Example Site',
    returnPath: 'bounce@example.com', // optional
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
| `returnPath` | `string\|null` | No | `null` | Return-Path (envelope sender) for outgoing emails. Bounces are delivered to this address. When `null`, the server's default is used. |

## Return-Path

WordPress ignores a `Return-Path:` header passed to `wp_mail()`, so `addHeader('Return-Path: ...')`
has no effect. `returnPath` instead sets PHPMailer's envelope sender (`$phpmailer->Sender`) via
`phpmailer_init`. The setting applies only to TOFU's own messages and is removed after each send.
An invalid address throws `InvalidArgumentException` when the form is registered.

To vary it per recipient or per submission, call `$mail->setReturnPath()` from the
[`tofu_pre_send_mail`](../hooks/index.md#tofu_pre_send_mail) hook.
