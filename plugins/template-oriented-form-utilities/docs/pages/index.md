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

The destination of each arrow is normally fixed at registration time via [`TemplateConfig`](../settings/templateconfig.md). If a form calls `Form::setTemplate()` while rendering the input page (e.g. to embed the same form on many pages), that override is stored in the visitor's session and carries through the confirm and result steps — it's cleared along with the rest of the session once `Form::verifySubmit()` succeeds on the result page. See [Embedding One Form on Many Pages](multi-page-embeds.md) for how to actually route a URL like `/news/{slug}/confirm/` to real content in that case.

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
| `Form::getFileDataAttribute($key, $field)` | Get data attribute for uploaded file |
| `Form::fileRemoveButton($key, $field)` | Generate file remove button |
| `Form::verifySession($key)` | Verify user has valid session data |
| `Form::verifySubmit($key)` | Verify form was submitted successfully |
| `Form::redirect($key, $action)` | Redirect to specified form page |
| `Form::setTemplate($key, $template)` | Override input/confirm/result URLs for the current visitor's session |
| `Form::hasRecaptcha($key)` | Check if reCAPTCHA is configured |
| `Form::hasRecaptchaError($key)` | Check if reCAPTCHA validation failed |
| `Form::recaptchaErrors($key)` | Get reCAPTCHA error messages |
| `Form::hasTurnstile($key)` | Check if Turnstile is configured |
| `Form::hasTurnstileError($key)` | Check if Turnstile validation failed |
| `Form::turnstileErrors($key)` | Get Turnstile error messages |
| `Form::turnstileWidget($key)` | Get Turnstile widget HTML |

## Next Steps

- [Input Page Template](input.md) - Learn how to create the form input page
- [Confirm Page Template](confirm.md) - Learn how to create the confirmation page
- [Result Page Template](result.md) - Learn how to create the result/thank-you page
- [Embedding One Form on Many Pages](multi-page-embeds.md) - Routing techniques for per-post confirm/result URLs
