# MailRecipientsConfig

Configuration for individual email recipients.

## Usage

```php
use TofuPlugin\Structure\MailRecipientsConfig;

// Auto-reply to user
$autoReply = new MailRecipientsConfig(
    recipientEmail: '{email}', // Dynamic field value
    recipientCcEmail: null,
    recipientBccEmail: null,
    subjectPath: 'form/contact/auto-reply-subject',
    mailBodyPath: 'form/contact/auto-reply-body',
);

// Static admin notification
$adminNotification = new MailRecipientsConfig(
    recipientEmail: 'admin@example.com',
    subject: 'New Contact Form Submission',
    mailBody: 'You have received a new contact form submission.',
);
```

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `recipientEmail` | `string` | Yes | - | Recipient email address. Supports dynamic field placeholders like `{email}`. |
| `recipientCcEmail` | `?string` | No | `null` | CC email address. Supports dynamic field placeholders. |
| `recipientBccEmail` | `?string` | No | `null` | BCC email address. Supports dynamic field placeholders. |
| `subject` | `?string` | No | `null` | Static email subject line. Either `subject` or `subjectPath` must be set. |
| `subjectPath` | `?string` | No | `null` | Path to a PHP template file for the email subject. Either `subject` or `subjectPath` must be set. |
| `mailBody` | `?string` | No | `null` | Static email body content. Either `mailBody` or `mailBodyPath` must be set. |
| `mailBodyPath` | `?string` | No | `null` | Path to a PHP template file for the email body. Either `mailBody` or `mailBodyPath` must be set. |

## Dynamic Field Placeholders

You can use `{field_name}` syntax in email addresses to dynamically insert form field values:

```php
recipientEmail: '{email}',      // Uses the value from the 'email' form field
recipientCcEmail: '{cc_email}', // Uses the value from the 'cc_email' form field
```

## Subject and Body Templates

When using `subjectPath` or `mailBodyPath`, it returns the output of the specified template file by using `get_template_part()`. The template files can access form data to generate dynamic content.

```php
<?php
use TofuPlugin\Structure\MailRecipientsConfig;

$mailRecipient = new MailRecipientsConfig(
    // ...
    subjectPath: 'form/contact/auto-reply-subject',
    // ...
);
```

Example subject template (`form/contact/auto-reply-subject.php`):
```php
New Submission Received from <?php echo Form::value('form', 'email'); ?>
```

However, the above example is very simple, so you can replace it with the static `subject` property as follows:

```php
subject: 'New Submission Received from {email}',
```

## Notes

- Either `subject` or `subjectPath` must be provided (not both).
- Either `mailBody` or `mailBodyPath` must be provided (not both).
- Template files receive form data that can be used to generate dynamic content.
