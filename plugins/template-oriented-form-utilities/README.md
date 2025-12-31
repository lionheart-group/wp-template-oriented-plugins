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
            'name' => 'required|max_len:200',
            'email' => 'required|valid_email',
        ],
        messages: [
            'name' => [
                'required' => 'Please enter your name.',
                'max_len' => 'Your name must be within 200 characters.',
            ],
            'email' => [
                'required' => 'Please enter your email address.',
                'valid_email' => 'Please enter a valid email address.',
            ],
        ],
        // $values: \TofuPlugin\Models\FieldValueCollection
        // $errors: \TofuPlugin\Models\ValidationErrorCollection
        after: function ($values, $errors) {
            // Custom validation logic can be added here
            $nameValue = $values->getValue('name');

            if ($nameValue !== null && $nameValue->value === 'Test') {
                $errors->addError('name', 'The name "Test" is not allowed.');
            }
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

You can then use the form in your templates:

**Input page**

```php
<?php
use TofuPlugin\Helpers\Form;

$formKey = 'form';
$formAction = 'input';
?>

<?php echo Form::formOpen($formKey, $formAction); ?>
    <div>
        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?php echo Form::value($formKey, 'name'); ?>" required>

        <?php if (Form::hasError($formKey, 'name')): ?>
            <?php foreach (Form::errors($formKey, 'name') as $errorMessage): ?>
                <p class="error-message"><?php echo esc_html($errorMessage); ?></p>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?php echo Form::value($formKey, 'email'); ?>" required>

        <?php if (Form::hasError($formKey, 'email')): ?>
            <?php foreach (Form::errors($formKey, 'email') as $errorMessage): ?>
                <p class="error-message"><?php echo esc_html($errorMessage); ?></p>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div>
        <button type="submit">Submit</button>
    </div>
<?php echo Form::formClose($formKey, $formAction); ?>
```

**Confirmation page**

```php
<?php
use TofuPlugin\Helpers\Form;

$formKey = 'form';
$formAction = 'confirm';
?>

<?php echo Form::formOpen($formKey, $formAction); ?>
    <div>
        <label for="name">Name</label>
        <?php echo Form::value($formKey, 'name'); ?>
    </div>

    <div>
        <label for="email">Email</label>
        <?php echo Form::value($formKey, 'email'); ?>
    </div>

    <div>
        <a href="<?php echo home_url('/contact/'); ?>">Back</a>
        <button type="submit">Submit</button>
    </div>
<?php echo Form::formClose($formKey, $formAction); ?>
```
