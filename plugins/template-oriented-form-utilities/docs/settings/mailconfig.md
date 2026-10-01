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

### Shared hosting and sendmail

When WordPress sends through PHP's `mail()` (the default), the Return-Path reaches the server's
sendmail as `-f<address>`. Some shared hosts reject a `-f` address whose domain is not hosted on
that server. Sakura Internet is one, with sendmail exiting with status 65 (`EX_DATAERR`). `mail()`
then returns false, and the only error TOFU can log is PHPMailer's generic
`Could not instantiate mail function.` (Japanese: `メール機能をインスタンス化できませんでした。`).
The same form sends fine as soon as `returnPath` is removed.

To check whether a host does this, call sendmail directly over SSH and look at the exit status:

```bash
printf "To: you@example.com\nSubject: test\n\nbody\n" | /usr/sbin/sendmail -t -i -f bounce@example.com; echo "exit=$?"
```

If the domain's mail lives on another server, send through that server over SMTP instead, using
an SMTP plugin or a `phpmailer_init` callback that calls `$phpmailer->isSMTP()`. The sending
server then sets the envelope sender, and SPF, DKIM and DMARC align with the From domain. Many
SMTP servers only accept a `MAIL FROM` that matches the authenticated account, so either leave
`returnPath` unset or set it to that account's address.
