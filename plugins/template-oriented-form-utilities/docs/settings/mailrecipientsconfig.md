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
    subjectPath: get_template_directory() . '/form/contact/auto-reply-subject',
    mailBodyPath: get_template_directory() . '/form/contact/auto-reply-body',
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

## Notes

- Either `subject` or `subjectPath` must be provided (not both).
- Either `mailBody` or `mailBodyPath` must be provided (not both).
- Template files receive form data that can be used to generate dynamic content.
