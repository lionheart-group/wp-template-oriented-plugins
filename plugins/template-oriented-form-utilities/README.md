# TOFU

Template-Oriented Form Utilities

## Description

This plugin provides a set of utilities for creating and managing template-oriented forms in WordPress. It allows developers to create forms that are based on templates, making it easier to manage and maintain form layouts and functionality.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/template-oriented-form-utilities` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.

## Usage

First, you need to setup your form settings.

```php
<?php
// functions.php

add_action('init', function () {
    // Specify template settings
    $template = new \TofuPlugin\Structure\TemplateConfig(
        inputPath: '/contact/',
        confirmPath: '/contact/confirm/',
        resultPath: '/contact/result/',
    );

    // Specify mail settings
    $mail = new \TofuPlugin\Structure\MailConfig(
        fromEmail: 'sample@example.com',
        fromName: 'Sample Name',

        recipients: new \TofuPlugin\Structure\MailRecipientsCollection([
            // Auto reply to the user
            new \TofuPlugin\Structure\MailRecipientsConfig(
                recipientEmail: '{email}',
                recipientCcEmail: null,
                recipientBccEmail: null,
                subjectPath: get_template_directory() . '/form/contact/auto-reply-subject.php',
                mailBodyPath: get_template_directory() . '/form/contact/auto-reply-body.php',
            ),

            // Send to the admin
            new \TofuPlugin\Structure\MailRecipientsConfig(
                recipientEmail: 'admin@example.com',
                recipientCcEmail: null,
                recipientBccEmail: null,
                subjectPath: get_template_directory() . '/form/contact/admin-subject.php',
                mailBodyPath: get_template_directory() . '/form/contact/admin-body.php',
            ),
        ]),
    );

    // Specify validation settings
    $validation = new \TofuPlugin\Structure\ValidationConfig(
        rules: [
            'name' => [
                'required' => true,
            ],
            'email' => [
                'required' => true,
                'email' => true,
            ],
        ],
        after: function () {
        }
    );

    // Register the form configuration
    \TofuPlugin\Helpers\Form::register(new \TofuPlugin\Structure\FormConfig(
        key: 'form',
        name: 'Contact Form',
        saveToDatabase: false,
        template: $template,
        mail: $mail,
        validation: $validation,
    ));
});
```
