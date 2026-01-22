# Input Page Template

The input page is where users fill out and submit the form. This page handles:

- Displaying form fields
- Showing validation errors (when form is resubmitted with errors)
- Pre-populating fields with previously entered values

## Table of Contents

- [Basic Structure](#basic-structure)
- [Form Open and Close](#form-open-and-close)
- [Text Input Fields](#text-input-fields)
- [Checkbox Fields](#checkbox-fields)
- [Radio Button Fields](#radio-button-fields)
- [Select (Dropdown) Fields](#select-dropdown-fields)
- [File Upload Fields](#file-upload-fields)
- [reCAPTCHA Integration](#recaptcha-integration)
- [Turnstile Integration](#turnstile-integration)
- [Complete Example](#complete-example)
- [Important Notes](#important-notes)
- [Next Steps](#next-steps)

## Basic Structure

```php
<?php
/**
 * Template Name: Contact Form - Input
 */

use TofuPlugin\Helpers\Form;

$formKey = 'contact'; // Must match the key used in Form::register()
$formAction = 'input';

// Embed necessary scripts (reCAPTCHA, file upload handling)
// Must be called BEFORE get_header()
Form::embedScript($formKey);

get_header();
?>

<main>
    <h1>Contact Us</h1>

    <?php echo Form::formOpen($formKey, $formAction); ?>

        <!-- Form fields go here -->

        <button type="submit">Submit</button>

    <?php echo Form::formClose($formKey, $formAction); ?>
</main>

<?php get_footer(); ?>
```

## Form Open and Close

Always use `Form::formOpen()` and `Form::formClose()` to generate the form tags:

```php
<?php echo Form::formOpen($formKey, $formAction); ?>
    <!-- Form content -->
<?php echo Form::formClose($formKey, $formAction); ?>
```

**What these methods do:**

- `formOpen()` - Generates a `<form>` tag with proper action URL, method, and enctype
- `formClose()` - Generates hidden fields for nonce verification and reCAPTCHA token, then closes the form

### Custom Form Attributes

You can pass additional HTML attributes to the form:

```php
<?php echo Form::formOpen($formKey, $formAction, [
    'class' => 'contact-form',
    'data-ajax' => 'true',
]); ?>
```

## Text Input Fields

### Basic Text Input

```php
<div class="form-group">
    <label for="name">Name</label>
    <input
        type="text"
        id="name"
        name="name"
        value="<?php echo Form::value($formKey, 'name'); ?>"
    >

    <?php if (Form::hasError($formKey, 'name')): ?>
        <?php foreach (Form::errors($formKey, 'name') as $errorMessage): ?>
            <p class="error-message"><?php echo esc_html($errorMessage); ?></p>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
```

### Email Input

```php
<div class="form-group">
    <label for="email">Email</label>
    <input
        type="email"
        id="email"
        name="email"
        value="<?php echo Form::value($formKey, 'email'); ?>"
    >

    <?php if (Form::hasError($formKey, 'email')): ?>
        <?php foreach (Form::errors($formKey, 'email') as $errorMessage): ?>
            <p class="error-message"><?php echo esc_html($errorMessage); ?></p>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
```

### Textarea

```php
<div class="form-group">
    <label for="message">Message</label>
    <textarea
        id="message"
        name="message"
        rows="5"
    ><?php echo Form::value($formKey, 'message'); ?></textarea>

    <?php if (Form::hasError($formKey, 'message')): ?>
        <?php foreach (Form::errors($formKey, 'message') as $errorMessage): ?>
            <p class="error-message"><?php echo esc_html($errorMessage); ?></p>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
```

## Checkbox Fields

### Single Checkbox

```php
<div class="form-group">
    <label>
        <input
            type="checkbox"
            name="agree"
            value="yes"
            <?php echo Form::checked($formKey, 'agree', 'yes'); ?>
        >
        I agree to the terms and conditions
    </label>

    <?php if (Form::hasError($formKey, 'agree')): ?>
        <?php foreach (Form::errors($formKey, 'agree') as $errorMessage): ?>
            <p class="error-message"><?php echo esc_html($errorMessage); ?></p>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
```

### Multiple Checkboxes

```php
<div class="form-group">
    <label>Interests</label>

    <label>
        <input
            type="checkbox"
            name="interests[]"
            value="design"
            <?php echo Form::checked($formKey, 'interests', 'design'); ?>
        >
        Design
    </label>

    <label>
        <input
            type="checkbox"
            name="interests[]"
            value="development"
            <?php echo Form::checked($formKey, 'interests', 'development'); ?>
        >
        Development
    </label>

    <label>
        <input
            type="checkbox"
            name="interests[]"
            value="marketing"
            <?php echo Form::checked($formKey, 'interests', 'marketing'); ?>
        >
        Marketing
    </label>

    <?php if (Form::hasError($formKey, 'interests')): ?>
        <?php foreach (Form::errors($formKey, 'interests') as $errorMessage): ?>
            <p class="error-message"><?php echo esc_html($errorMessage); ?></p>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
```

## Radio Button Fields

```php
<div class="form-group">
    <label>Contact Preference</label>

    <label>
        <input
            type="radio"
            name="contact_method"
            value="email"
            <?php echo Form::checked($formKey, 'contact_method', 'email'); ?>
        >
        Email
    </label>

    <label>
        <input
            type="radio"
            name="contact_method"
            value="phone"
            <?php echo Form::checked($formKey, 'contact_method', 'phone'); ?>
        >
        Phone
    </label>

    <?php if (Form::hasError($formKey, 'contact_method')): ?>
        <?php foreach (Form::errors($formKey, 'contact_method') as $errorMessage): ?>
            <p class="error-message"><?php echo esc_html($errorMessage); ?></p>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
```

## Select (Dropdown) Fields

### Single Select

```php
<div class="form-group">
    <label for="department">Department</label>
    <select id="department" name="department">
        <option value="">-- Select --</option>
        <option value="sales" <?php echo Form::selected($formKey, 'department', 'sales'); ?>>Sales</option>
        <option value="support" <?php echo Form::selected($formKey, 'department', 'support'); ?>>Support</option>
        <option value="general" <?php echo Form::selected($formKey, 'department', 'general'); ?>>General Inquiry</option>
    </select>

    <?php if (Form::hasError($formKey, 'department')): ?>
        <?php foreach (Form::errors($formKey, 'department') as $errorMessage): ?>
            <p class="error-message"><?php echo esc_html($errorMessage); ?></p>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
```

### Multiple Select

```php
<div class="form-group">
    <label for="services">Services Interested In</label>
    <select id="services" name="services[]" multiple>
        <option value="web" <?php echo Form::selected($formKey, 'services', 'web'); ?>>Web Development</option>
        <option value="mobile" <?php echo Form::selected($formKey, 'services', 'mobile'); ?>>Mobile Apps</option>
        <option value="consulting" <?php echo Form::selected($formKey, 'services', 'consulting'); ?>>Consulting</option>
    </select>

    <?php if (Form::hasError($formKey, 'services')): ?>
        <?php foreach (Form::errors($formKey, 'services') as $errorMessage): ?>
            <p class="error-message"><?php echo esc_html($errorMessage); ?></p>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
```

## File Upload Fields

```php
<div class="form-group">
    <label for="attachment">Attachment</label>

    <!-- File input -->
    <input
        type="file"
        id="attachment"
        name="attachment"
        accept=".pdf,.doc,.docx"
    >

    <?php
        if (Form::hasFile($formKey, 'attachment')):
            // Get `data-tofu-field` attribute for JS handling to remove information element when the remove button is clicked
            $infoDataAttr = Form::getFileDataAttribute($formKey, 'attachment');
    ?>
        <!-- Wrapper element for uploaded file info and controls -->
        <div <?php echo $infoDataAttr; ?>>
            <!-- Show uploaded file info -->
            <?php $file = Form::file($formKey, 'attachment'); ?>
            <p>
                Uploaded: <?php echo esc_html($file->fileName); ?>
                (<?php echo size_format($file->size); ?>)
            </p>

            <!-- Hidden fields to preserve file data -->
            <?php echo Form::fileHidden($formKey, 'attachment'); ?>

            <!-- Remove button -->
            <?php echo Form::fileRemoveButton($formKey, 'attachment', 'Remove File', [
                'class' => 'btn-remove',
            ]); ?>
        </div>
    <?php endif; ?>

    <?php if (Form::hasError($formKey, 'attachment')): ?>
        <?php foreach (Form::errors($formKey, 'attachment') as $errorMessage): ?>
            <p class="error-message"><?php echo esc_html($errorMessage); ?></p>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
```

## reCAPTCHA Integration

If reCAPTCHA is configured for your form, display any reCAPTCHA errors:

```php
<?php if (Form::hasRecaptcha($formKey) && Form::hasRecaptchaError($formKey)): ?>
    <div class="recaptcha-error">
        <?php foreach (Form::recaptchaErrors($formKey) as $errorMessage): ?>
            <p class="error-message"><?php echo esc_html($errorMessage); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
```

> **Note:** The reCAPTCHA script and hidden token field are automatically handled by `Form::embedScript()` and `Form::formClose()`.

## Turnstile Integration

If Turnstile is configured for your form, display Turnstile widget and any Turnstile errors:

```php
<?php if (Form::hasTurnstile($formKey)): ?>
    <?php echo Form::turnstileWidget($formKey); ?>

    <div class="turnstile-error">
        <?php foreach (Form::turnstileErrors($formKey) as $errorMessage): ?>
            <p class="error-message"><?php echo esc_html($errorMessage); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
```

## Complete Example

```php
<?php
/**
 * Template Name: Contact Form - Input
 */

use TofuPlugin\Helpers\Form;

$formKey = 'contact';
$formAction = 'input';

// Embed necessary scripts before get_header()
Form::embedScript($formKey);

get_header();
?>

<main class="contact-page">
    <h1>Contact Us</h1>

    <?php echo Form::formOpen($formKey, $formAction, ['class' => 'contact-form']); ?>

        <!-- Name -->
        <div class="form-group">
            <label for="name">Name <span class="required">*</span></label>
            <input
                type="text"
                id="name"
                name="name"
                value="<?php echo Form::value($formKey, 'name'); ?>"
                required
            >
            <?php if (Form::hasError($formKey, 'name')): ?>
                <?php foreach (Form::errors($formKey, 'name') as $errorMessage): ?>
                    <p class="error-message"><?php echo esc_html($errorMessage); ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Email -->
        <div class="form-group">
            <label for="email">Email <span class="required">*</span></label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?php echo Form::value($formKey, 'email'); ?>"
                required
            >
            <?php if (Form::hasError($formKey, 'email')): ?>
                <?php foreach (Form::errors($formKey, 'email') as $errorMessage): ?>
                    <p class="error-message"><?php echo esc_html($errorMessage); ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Department -->
        <div class="form-group">
            <label for="department">Department</label>
            <select id="department" name="department">
                <option value="">-- Select --</option>
                <option value="sales" <?php echo Form::selected($formKey, 'department', 'sales'); ?>>Sales</option>
                <option value="support" <?php echo Form::selected($formKey, 'department', 'support'); ?>>Support</option>
                <option value="general" <?php echo Form::selected($formKey, 'department', 'general'); ?>>General</option>
            </select>
        </div>

        <!-- Message -->
        <div class="form-group">
            <label for="message">Message <span class="required">*</span></label>
            <textarea
                id="message"
                name="message"
                rows="5"
                required
            ><?php echo Form::value($formKey, 'message'); ?></textarea>
            <?php if (Form::hasError($formKey, 'message')): ?>
                <?php foreach (Form::errors($formKey, 'message') as $errorMessage): ?>
                    <p class="error-message"><?php echo esc_html($errorMessage); ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Attachment -->
        <div class="form-group">
            <label for="attachment">Attachment</label>
            <input type="file" id="attachment" name="attachment" accept=".pdf,.doc,.docx">

            <?php
                if (Form::hasFile($formKey, 'attachment')):
                    $infoDataAttr = Form::getFileDataAttribute($formKey, 'attachment');
                    $file = Form::file($formKey, 'attachment');
            ?>
                <div <?php echo $infoDataAttr; ?>>
                    <p>Uploaded: <?php echo esc_html($file->fileName); ?></p>
                    <?php echo Form::fileHidden($formKey, 'attachment'); ?>
                    <?php echo Form::fileRemoveButton($formKey, 'attachment', 'Remove'); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Privacy Agreement -->
        <div class="form-group">
            <label>
                <input
                    type="checkbox"
                    name="privacy"
                    value="agreed"
                    <?php echo Form::checked($formKey, 'privacy', 'agreed'); ?>
                    required
                >
                I agree to the <a href="/privacy-policy/">Privacy Policy</a>
            </label>
            <?php if (Form::hasError($formKey, 'privacy')): ?>
                <?php foreach (Form::errors($formKey, 'privacy') as $errorMessage): ?>
                    <p class="error-message"><?php echo esc_html($errorMessage); ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- reCAPTCHA errors -->
        <?php if (Form::hasRecaptcha($formKey) && Form::hasRecaptchaError($formKey)): ?>
            <div class="form-group">
                <?php foreach (Form::recaptchaErrors($formKey) as $errorMessage): ?>
                    <p class="error-message"><?php echo esc_html($errorMessage); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Turnstile errors -->
        <?php if (Form::hasTurnstile($formKey)): ?>
            <?php echo Form::turnstileWidget($formKey); ?>

            <?php if (Form::hasTurnstileError($formKey)): ?>
                <div class="form-group">
                    <?php foreach (Form::turnstileErrors($formKey) as $errorMessage): ?>
                        <p class="error-message"><?php echo esc_html($errorMessage); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Submit -->
        <div class="form-actions">
            <button type="submit" class="btn-submit">Submit</button>
        </div>

    <?php echo Form::formClose($formKey, $formAction); ?>
</main>

<?php get_footer(); ?>
```

## Important Notes

1. **Always call `Form::embedScript()` before `get_header()`** - This ensures JavaScript files are properly enqueued in the page head.
2. **Use `Form::value()` for all field values** - This automatically escapes output and retrieves stored session values.
3. **The field `name` attribute must match validation rules** - Field names in your HTML must match the keys defined in `ValidationConfig`.
4. **Error handling is automatic** - If validation fails, users are redirected back to the input page with errors and their previously entered values preserved.

## Next Steps

- [Confirm Page Template](confirm.md) - Create the confirmation page
- [Result Page Template](result.md) - Create the thank-you page
