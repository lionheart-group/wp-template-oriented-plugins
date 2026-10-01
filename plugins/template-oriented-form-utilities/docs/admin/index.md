# Admin screens

TOFU adds two screens to wp-admin. Neither changes anything: forms are configured in the theme's PHP
code, and the screens only show what is there.

Both require `manage_options` by default. One filter,
[`tofu_admin_page_capability`](../hooks/index.md#tofu_admin_page_capability), sets the capability
for both the menu entries and the pages themselves.

## Records (TOFU → TOFU Records)

The top-level **TOFU** menu lists the submissions saved by forms with
[`saveToDatabase: true`](../settings/formconfig.md), newest first, 25 per page, with a filter by
form. Opening a record decrypts it and shows each field and its value. Which fields are saved is set
with [`ValidationConfig::$records`](../settings/validationconfig.md).

> Records may contain personal data. Widen the capability only to roles that are meant to see it.

## Form settings (Tools → Forms (TOFU))

A read-only view of every form registered with `Form::register()`, for someone who needs to check
the configuration without reading the theme's code.

**Bot protection** shows whether `Form::setRecaptcha()` and `Form::setTurnstile()` registered a
configuration, with the site key and, for reCAPTCHA, the threshold. Secret keys are never shown.

Then, for each form, its name and key, and:

| Section | What it shows |
|---|---|
| Pages | The input, confirm and result paths of `template`, linked. Each is looked up with `url_to_postid()`; a root-relative path is resolved against the site's host, which is where the browser goes when TOFU redirects to it. |
| Mail | The sender, the Return-Path, and for each recipient: To, CC, BCC, the subject (or its `subjectPath` template) and the body (“Inline text” for `mailBody`, or its `mailBodyPath` template). A template is looked up with `locate_template()`, as `get_template_part()` would load it. |
| Fields | Every field in `allows`, with its label (`names`), its rules, and whether it is recorded (when `saveToDatabase` is on). Whether an `after` hook is set. |
| Features | Save to database, confirm step, reCAPTCHA, Turnstile, AJAX (REST API), CORS origins and dynamic template. |
| Checks | The problems below, or “No problems found”. |

The checks:

- **A page is not found.** No page or post answers at the path. A form placed on something
  `url_to_postid()` cannot resolve, such as a post type archive, is reported too.
- **A mail template is not found.** `subjectPath` or `mailBodyPath` names a template the theme does
  not have, so sending fails.
- **Bot protection is enabled but not registered.** `recaptchaEnabled` or `turnstileEnabled` is on
  but `Form::setRecaptcha()` / `Form::setTurnstile()` was not called.
- **A placeholder is not an allowed field.** A `{field}` in To, CC, BCC, the subject or the inline
  body names no field in `allows`, so it stays as written in the mail — unless the `after` hook adds
  a value of that name, which the message mentions when the form has one. Templates
  (`subjectPath`, `mailBodyPath`) are PHP and are not scanned.

Some paths can't be checked and are marked so instead of being reported: those of a form with
`dynamicTemplate: true` (the real ones come from `Form::setTemplate()` per request), a path with a
query string, a URL on another host, and a relative path without a leading `/`.
