# Page Templates

This section covers how to create template pages for your TOFU forms. A typical form flow consists of three pages:

1. **[Input Page](input.md)** - Where users fill out the form
2. **[Confirm Page](confirm.md)** - Where users review their submission (optional)
3. **[Result Page](result.md)** - Displayed after successful submission

## Form Flow

```
┌─────────────┐     ┌──────────────┐     ┌─────────────┐
│  Input Page │────▶│ Confirm Page │────▶│ Result Page │
│             │     │  (optional)  │     │             │
└─────────────┘     └──────────────┘     └─────────────┘
       ▲                   │
       │                   │
       └───────────────────┘
           (Back / Edit)
```

## Form Helper Methods

The `TofuPlugin\Helpers\Form` class provides all the methods you need to build form templates.

### Quick Reference

| Method | Description |
|--------|-------------|
| `Form::embedScript($key)` | Embed required JavaScript (call before `get_header()`) |
| `Form::formOpen($key, $action)` | Generate opening `<form>` tag |
| `Form::formClose($key, $action)` | Generate closing `</form>` tag with hidden fields |
| `Form::value($key, $field)` | Get escaped field value |
| `Form::hasError($key, $field)` | Check if field has validation errors |
| `Form::errors($key, $field)` | Get array of error messages for field |
| `Form::checked($key, $field, $value)` | Return `checked` attribute for checkboxes/radios |
| `Form::selected($key, $field, $value)` | Return `selected` attribute for select options |
| `Form::contains($key, $field, $value)` | Check if field contains a specific value |
| `Form::hasFile($key, $field)` | Check if file was uploaded for field |
| `Form::file($key, $field)` | Get uploaded file object |
| `Form::fileHidden($key, $field)` | Generate hidden inputs for uploaded file |
| `Form::fileRemoveButton($key, $field)` | Generate file remove button |
| `Form::verifySession($key)` | Verify user has valid session data |
| `Form::verifySubmit($key)` | Verify form was submitted successfully |
| `Form::redirect($key, $action)` | Redirect to specified form page |
| `Form::hasRecaptcha($key)` | Check if reCAPTCHA is configured |
| `Form::hasRecaptchaError($key)` | Check if reCAPTCHA validation failed |
| `Form::recaptchaErrors($key)` | Get reCAPTCHA error messages |

## Next Steps

- [Input Page Template](input.md) - Learn how to create the form input page
- [Confirm Page Template](confirm.md) - Learn how to create the confirmation page
- [Result Page Template](result.md) - Learn how to create the result/thank-you page
