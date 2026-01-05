# Result Page Template

The result page (also known as the "thank you" page) is displayed after a form has been successfully submitted. This page confirms to the user that their submission was received and any emails have been sent.

## Basic Structure

```php
<?php
/**
 * Template Name: Contact Form - Result
 */

use TofuPlugin\Helpers\Form;

$formKey = 'contact'; // Must match the key used in Form::register()

// Verify that form was submitted properly
// Must be done BEFORE get_header()
if (!Form::verifySubmit($formKey)) {
    Form::redirect($formKey, 'input');
    exit;
}

get_header();
?>

<main>
    <h1>Thank You!</h1>
    <p>Your message has been sent successfully.</p>
    <p>We will get back to you as soon as possible.</p>

    <a href="<?php echo home_url('/'); ?>">Return to Home</a>
</main>

<?php get_footer(); ?>
```

## Submit Verification

Always verify the submission at the beginning of the result page template:

```php
if (!Form::verifySubmit($formKey)) {
    Form::redirect($formKey, 'input');
    exit;
}
```

**Why is this important?**

- Prevents users from accessing the result page directly via URL
- Ensures the form was actually submitted and processed
- Confirms that emails were sent successfully (if configured)
- Protects against bookmark/refresh issues

## Displaying Submitted Values

You can optionally display a summary of the submitted information:

```php
<?php
// Get form instance to access submitted values
$form = Form::get($formKey);
$values = $form->getValues();
?>

<div class="submission-summary">
    <h2>Submission Details</h2>

    <dl>
        <dt>Name</dt>
        <dd><?php echo esc_html($values->getValue('name')?->value ?? ''); ?></dd>

        <dt>Email</dt>
        <dd><?php echo esc_html($values->getValue('email')?->value ?? ''); ?></dd>
    </dl>
</div>
```

> **Note:** On the result page, session data may be cleared after display, so these values are only available on the initial page load.

## Complete Example

```php
<?php
/**
 * Template Name: Contact Form - Result
 */

use TofuPlugin\Helpers\Form;

$formKey = 'contact';

// Verify submission - redirect if invalid
if (!Form::verifySubmit($formKey)) {
    Form::redirect($formKey, 'input');
    exit;
}

get_header();
?>

<main class="result-page">
    <div class="result-container">
        <div class="success-icon">✓</div>

        <h1>Thank You for Contacting Us!</h1>

        <p class="lead">
            Your message has been received successfully.
        </p>

        <p>
            We appreciate you taking the time to reach out.
            A confirmation email has been sent to your email address.
        </p>

        <p>
            Our team will review your message and get back to you
            within 1-2 business days.
        </p>

        <div class="result-actions">
            <a href="<?php echo home_url('/'); ?>" class="btn-primary">
                Return to Home
            </a>
            <a href="<?php echo home_url('/contact/'); ?>" class="btn-secondary">
                Send Another Message
            </a>
        </div>
    </div>
</main>

<?php get_footer(); ?>
```

## Advanced: Tracking Submissions

You can use the result page to trigger analytics or conversion tracking:

```php
<?php
// Verify submission
if (!Form::verifySubmit($formKey)) {
    Form::redirect($formKey, 'input');
    exit;
}

// Add conversion tracking
add_action('wp_footer', function() {
?>
    <script>
        // Google Analytics event
        if (typeof gtag === 'function') {
            gtag('event', 'form_submission', {
                'event_category': 'Contact',
                'event_label': 'Contact Form'
            });
        }

        // Facebook Pixel
        if (typeof fbq === 'function') {
            fbq('track', 'Lead');
        }
    </script>
<?php
});

get_header();
?>
```

## Important Notes

1. **Always verify the submission** - Use `Form::verifySubmit()` to ensure the form was properly processed.

2. **No form is needed on this page** - The result page is just for displaying a success message.

3. **Session data is cleared** - After successful submission, the session data may be cleared to prevent duplicate submissions.

4. **Don't allow refresh resubmission** - The verification prevents users from refreshing the page and resubmitting the form.

5. **Provide clear next steps** - Include links to help users navigate after submitting the form.

## Error Handling

If you've configured an `errorPath` in your `TemplateConfig`, users will be redirected there if the form submission fails. Otherwise, they'll be returned to the input page with error messages displayed.

For custom error handling on the result page:

```php
// This scenario is rare since errors typically redirect to input/error page
if (!Form::verifySubmit($formKey)) {
    // Log the issue
    error_log('Form submission verification failed for: ' . $formKey);

    // Redirect to input page
    Form::redirect($formKey, 'input');
    exit;
}
```

## Preventing Duplicate Submissions

The plugin handles duplicate submission prevention automatically by:

1. Clearing session data after successful submission
2. Using nonce verification to prevent CSRF attacks
3. Verifying the submission flag before displaying the result page

Users who try to refresh the result page or access it directly will be redirected back to the input page.

## Next Steps

- [Input Page Template](input.md) - Review how to create the input page
- [Confirm Page Template](confirm.md) - Review the confirmation page setup
- [Settings Reference](../settings.md) - Full configuration options
