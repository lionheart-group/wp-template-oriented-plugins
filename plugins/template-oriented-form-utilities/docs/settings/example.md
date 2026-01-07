# Complete Example

Here's a complete example showing all configuration options:

```php
<?php
// functions.php

add_action('init', function () {
    // Template configuration
    $template = new \TofuPlugin\Structure\TemplateConfig(
        inputPath: '/contact/',
        confirmPath: '/contact/confirm/',
        resultPath: '/contact/result/',
    );

    // Mail configuration
    $mail = new \TofuPlugin\Structure\MailConfig(
        fromEmail: 'noreply@example.com',
        fromName: 'Example Website',
        recipients: new \TofuPlugin\Structure\MailRecipientsCollection([
            // Auto-reply to the user
            new \TofuPlugin\Structure\MailRecipientsConfig(
                recipientEmail: '{email}',
                subjectPath: 'form/contact/auto-reply-subject',
                mailBodyPath: 'form/contact/auto-reply-body',
            ),
            // Notification to admin
            new \TofuPlugin\Structure\MailRecipientsConfig(
                recipientEmail: 'admin@example.com',
                recipientCcEmail: 'manager@example.com',
                subjectPath: 'form/contact/admin-subject',
                mailBodyPath: 'form/contact/admin-body',
            ),
        ]),
    );

    // Validation configuration
    $validation = new \TofuPlugin\Structure\ValidationConfig(
        allows: ['name', 'email', 'phone', 'message'],
        rules: [
            'name' => 'required|max_len:200',
            'email' => 'required|valid_email',
            'phone' => 'max_len:20',
            'message' => 'required|max_len:2000',
        ],
        filters: [
            'name' => 'trim|sanitize_string',
            'email' => 'trim|sanitize_email|lower_case',
            'phone' => 'trim|sanitize_numbers',
            'message' => 'trim|sanitize_string',
        ],
        names: [
            'name' => 'Full Name',
            'email' => 'Email Address',
            'phone' => 'Phone Number',
            'message' => 'Message',
        ],
        messages: [
            'name' => [
                'required' => 'Please enter your name.',
                'max_len' => 'Name must be 200 characters or less.',
            ],
            'email' => [
                'required' => 'Please enter your email address.',
                'valid_email' => 'Please enter a valid email address.',
            ],
            'message' => [
                'required' => 'Please enter your message.',
                'max_len' => 'Message must be 2000 characters or less.',
            ],
        ],
        after: function ($values, $errors) {
            // Custom validation: block specific domains
            $emailValue = $values->getValue('email');
            if ($emailValue !== null && str_ends_with($emailValue->value, '@spam.com')) {
                $errors->addError('email', 'This email domain is not allowed.');
            }
        },
    );

    // reCAPTCHA configuration (optional)
    $recaptcha = new \TofuPlugin\Structure\ReCAPTCHAConfig(
        siteKey: 'your-site-key',
        secretKey: 'your-secret-key',
        threshold: 0.5,
    );

    // Register the form
    \TofuPlugin\Helpers\Form::register(new \TofuPlugin\Structure\FormConfig(
        key: 'contact',
        name: 'Contact Form',
        template: $template,
        mail: $mail,
        validation: $validation,
        saveToDatabase: false,
        recaptcha: $recaptcha,
    ));
});
```
