# Confirm Page Template

The confirmation page displays the submitted form data for user review before final submission. This step is **optional** — you can skip it by setting `confirmPath: null` in your `TemplateConfig`.

## Table of Contents

- [Basic Structure](#basic-structure)
- [Session Verification](#session-verification)
- [Displaying Field Values](#displaying-field-values)
- [Back Navigation](#back-navigation)
- [Complete Example](#complete-example)
- [Important Notes](#important-notes)
- [Skipping the Confirm Page](#skipping-the-confirm-page)
- [Next Steps](#next-steps)

## Basic Structure

```php
<?php
/**
 * Template Name: Contact Form - Confirm
 */

use TofuPlugin\Helpers\Form;

$formKey = 'contact'; // Must match the key used in Form::register()
$formAction = 'confirm';

// Verify session data to ensure user came from input page
// Must be done BEFORE get_header()
if (!Form::verifySession($formKey)) {
    Form::redirect($formKey, 'input');
    exit;
}

get_header();
?>

<main>
    <h1>Confirm Your Submission</h1>

    <?php echo Form::formOpen($formKey, $formAction); ?>

        <!-- Display submitted values for review -->

        <div class="form-actions">
            <a href="<?php echo home_url('/contact/'); ?>">Back to Edit</a>
            <button type="submit">Confirm & Submit</button>
        </div>

    <?php echo Form::formClose($formKey, $formAction); ?>
</main>

<?php get_footer(); ?>
```

## Session Verification

Always verify the session at the beginning of the confirm page template:

```php
if (!Form::verifySession($formKey)) {
    Form::redirect($formKey, 'input');
    exit;
}
```

**Why is this important?**

- Prevents users from accessing the confirm page directly without filling out the form
- Ensures form data exists in the session before displaying
- Protects against session hijacking attempts

## Displaying Field Values

### Text Values

```php
<div class="confirm-row">
    <dt>Name</dt>
    <dd><?php echo Form::value($formKey, 'name'); ?></dd>
</div>

<div class="confirm-row">
    <dt>Email</dt>
    <dd><?php echo Form::value($formKey, 'email'); ?></dd>
</div>

<div class="confirm-row">
    <dt>Message</dt>
    <dd><?php echo nl2br(Form::value($formKey, 'message')); ?></dd>
</div>
```

> **Note:** Use `nl2br()` for textarea content to preserve line breaks.

### Checkbox/Radio Values

For single value fields:

```php
<div class="confirm-row">
    <dt>Contact Preference</dt>
    <dd><?php echo Form::value($formKey, 'contact_method'); ?></dd>
</div>
```

For multiple checkbox values (arrays):

```php
<div class="confirm-row">
    <dt>Interests</dt>
    <dd>
        <?php
        $interests = Form::value($formKey, 'interests');
        if (is_array($interests)) {
            echo implode(', ', $interests);
        }
        ?>
    </dd>
</div>
```

### Select Values

```php
<div class="confirm-row">
    <dt>Department</dt>
    <dd><?php echo Form::value($formKey, 'department'); ?></dd>
</div>
```

### File Uploads

```php
<div class="confirm-row">
    <dt>Attachment</dt>
    <dd>
        <?php if (Form::hasFile($formKey, 'attachment')): ?>
            <?php $file = Form::file($formKey, 'attachment'); ?>
            <?php echo esc_html($file->fileName); ?>
            (<?php echo size_format($file->size); ?>)
        <?php else: ?>
            No file attached
        <?php endif; ?>
    </dd>
</div>
```

## Back Navigation

You can provide a back link/button to allow users to return to the input page for edits:

```php
<a href="<?php echo home_url('/contact/'); ?>">Back to Edit</a>
```

## Complete Example

```php
<?php
/**
 * Template Name: Contact Form - Confirm
 */

use TofuPlugin\Helpers\Form;

$formKey = 'contact';
$formAction = 'confirm';

// Verify session - redirect if invalid
if (!Form::verifySession($formKey)) {
    Form::redirect($formKey, 'input');
    exit;
}

get_header();
?>

<main class="confirm-page">
    <h1>Confirm Your Submission</h1>
    <p>Please review your information before submitting.</p>

    <?php echo Form::formOpen($formKey, $formAction, ['class' => 'confirm-form']); ?>

        <dl class="confirm-list">
            <!-- Name -->
            <div class="confirm-row">
                <dt>Name</dt>
                <dd><?php echo Form::value($formKey, 'name'); ?></dd>
            </div>

            <!-- Email -->
            <div class="confirm-row">
                <dt>Email</dt>
                <dd><?php echo Form::value($formKey, 'email'); ?></dd>
            </div>

            <!-- Department -->
            <div class="confirm-row">
                <dt>Department</dt>
                <dd><?php echo Form::value($formKey, 'department'); ?></dd>
            </div>

            <!-- Message -->
            <div class="confirm-row">
                <dt>Message</dt>
                <dd><?php echo nl2br(Form::value($formKey, 'message')); ?></dd>
            </div>

            <!-- Attachment -->
            <div class="confirm-row">
                <dt>Attachment</dt>
                <dd>
                    <?php if (Form::hasFile($formKey, 'attachment')): ?>
                        <?php $file = Form::file($formKey, 'attachment'); ?>
                        <span class="file-info">
                            📎 <?php echo esc_html($file->fileName); ?>
                            (<?php echo size_format($file->size); ?>)
                        </span>
                    <?php else: ?>
                        <span class="no-file">No file attached</span>
                    <?php endif; ?>
                </dd>
            </div>

            <!-- Privacy Agreement -->
            <div class="confirm-row">
                <dt>Privacy Policy</dt>
                <dd>
                    <?php echo Form::contains($formKey, 'privacy', 'agreed') ? 'Agreed' : 'Not agreed'; ?>
                </dd>
            </div>
        </dl>

        <!-- Actions -->
        <div class="form-actions">
            <a href="<?php echo home_url('/contact/'); ?>" class="btn-back">
                ← Back to Edit
            </a>
            <button type="submit" class="btn-submit">
                Confirm & Submit →
            </button>
        </div>

    <?php echo Form::formClose($formKey, $formAction); ?>
</main>

<?php get_footer(); ?>
```

## Important Notes

1. **Always verify the session before displaying content** - Use `Form::verifySession()` at the very beginning of your template.
2. **Call `Form::verifySession()` before get_header()** - This must be called before any output to work properly.
3. **The form action must be 'confirm'** - This tells the plugin to process this as the confirmation step.
4. **Provide a way to go back** - Always include a back link/button so users can edit their submission.

## Skipping the Confirm Page

If you don't need a confirmation step, simply set `confirmPath: null` in your `TemplateConfig`:

```php
$template = new \TofuPlugin\Structure\TemplateConfig(
    inputPath: '/contact/',
    confirmPath: null, // Skip confirmation
    resultPath: '/contact/result/',
);
```

When `confirmPath` is `null`, the form will submit directly from the input page to the result page.

## Next Steps

- [Result Page Template](result.md) - Create the thank-you page
- [Input Page Template](input.md) - Review the input page setup
