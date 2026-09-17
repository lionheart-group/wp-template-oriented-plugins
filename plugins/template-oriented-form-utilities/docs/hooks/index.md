# Hooks

TOFU fires a small number of WordPress actions and filters so that code outside the form's own
configuration — another plugin, an mu-plugin, a theme's `functions.php` — can react to submissions
and extend validation.

These complement, and do not replace, the configuration objects. The dividing line is:

> **Configuration describes what a form *is*. Hooks describe what the site *does* when a form does
> something.**
>
> If only the person who wrote `Form::register()` would want it, and it differs per form, it belongs
> in `FormConfig` and friends. If an unrelated plugin would want it, or it applies across every form
> on the site, it is a hook.

All hooks fire for **both** the redirect flow and the AJAX/REST flow, because both share the same
`processInput()` / `processConfirm()` methods internally.

## Compatibility

Hook names are permanent. Like validation rule labels, they are never renamed or removed once
released, because a site's callback would silently stop running (or, worse, a rule name would throw)
after an update.

Hooks are also purely additive: with no callbacks registered, the plugin behaves exactly as it did
before they existed.

---

## Actions

### `tofu_form_submitted`

Fires once a submission has been fully processed — every email dispatched and, when
`saveToDatabase` is enabled, the record persisted.

```php
do_action( 'tofu_form_submitted', array $values, UploadedFileCollection $files, int|false $recordId, FormConfig $config );
```

| Parameter | Description |
|---|---|
| `$values` | Submitted field values. |
| `$files` | Uploaded files. Still present on disk — they are deleted immediately after this hook. |
| `$recordId` | Inserted record ID, or `false` when nothing was saved. |
| `$config` | The form's configuration. Use `$config->key` to target one form. |

It sits after the idempotency guard at the top of `processConfirm()`, so a resubmitted confirm page
does not fire it a second time.

```php
add_action( 'tofu_form_submitted', function ( $values, $files, $record_id, $config ) {
    if ( 'contact' !== $config->key ) {
        return;
    }

    wp_remote_post( 'https://hooks.slack.com/services/XXX', [
        'body' => wp_json_encode( [
            'text' => sprintf( 'New enquiry from %s', $values['name'] ?? 'unknown' ),
        ] ),
    ] );
}, 10, 4 );
```

> **Note:** this runs inline during the submission request. Anything slow — a third-party API, a
> large export — makes the visitor wait for the thank-you page. Schedule the slow part with
> `wp_schedule_single_event()` rather than doing it here.

### `tofu_register_validation_rules`

Fires after the validator factory is built, before any rule string is parsed. This is the only way to
add a **named** validation rule.

```php
do_action( 'tofu_register_validation_rules', ValidatorFactory $factory, FormConfig $config );
```

```php
add_action( 'tofu_register_validation_rules', function ( $factory ) {
    $factory->addRule( 'jp_postal_code', new My_Postal_Code_Rule() );
} );
```

The rule name is then usable in any form's `rules`, and per-field message overrides key off it in the
usual `'field' => [ 'jp_postal_code' => '…' ]` form:

```php
rules: [ 'zip' => 'required|jp_postal_code' ],
```

Passing an existing label to `addRule()` replaces that rule for **every** form on the site. Check
`$config->key` when the change should be narrower.

### `tofu_pre_send_mail`

Fires immediately before each email is dispatched, once per configured recipient.

```php
do_action( 'tofu_pre_send_mail', Mail $mail, MailRecipientsConfig $recipient, array $values, FormConfig $config );
```

`$mail` is mutable — call `addHeader()`, `addAttachment()`, `addBcc()` and so on to adjust the
message. Recipients, subject and body come from `MailRecipientsConfig`; use this hook for what the
static configuration cannot express, namely decisions that depend on what was actually submitted.

```php
// BCC the sales team, but only for enquiries addressed to them.
add_action( 'tofu_pre_send_mail', function ( $mail, $recipient, $values, $config ) {
    if ( 'contact' === $config->key && 'sales' === ( $values['department'] ?? '' ) ) {
        $mail->addBcc( 'sales@example.com' );
    }
}, 10, 4 );
```

> **Careful:** `addTo()`, `addCc()` and `addBcc()` build a `MailAddress`, which throws
> `InvalidArgumentException` on a malformed address — it does not quietly skip the send. Validate
> anything dynamic before passing it in.

### `tofu_validation_failed`

Fires when a submission fails validation or a bot check.

```php
do_action( 'tofu_validation_failed', array $errors, FormConfig $config );
```

`$errors` is keyed by field name, each entry an array of messages. Useful for spam and abuse
monitoring, rate-limit counters, and drop-off analytics.

---

## Filters

Every filter falls back to the unfiltered value when a callback returns something unusable, so a
faulty callback cannot take a live form down.

### `tofu_redirect_url`

Filters the URL the form is about to redirect to.

```php
apply_filters( 'tofu_redirect_url', string|null $url, string $action, FormConfig $config );
```

`$action` is one of `'input'`, `'confirm'` or `'result'`. A non-string or empty return value is
ignored. `wp_safe_redirect()` still enforces its same-host guarantee afterwards.

```php
add_filter( 'tofu_redirect_url', function ( $url, $action, $config ) {
    if ( 'result' === $action && 'contact' === $config->key ) {
        return add_query_arg( 'submitted', '1', $url );
    }
    return $url;
}, 10, 3 );
```

For per-page form embeds, prefer `Form::setTemplate()` — see
[Embedding One Form on Many Pages](../pages/multi-page-embeds.md).

### `tofu_record_values`

Filters the values about to be written to `wp_tofu_records`.

```php
apply_filters( 'tofu_record_values', array $values, FormConfig $config );
```

A non-array return value is ignored.

```php
add_filter( 'tofu_record_values', function ( $values, $config ) {
    $values['submitted_from'] = get_the_ID();
    return $values;
}, 10, 2 );
```

This runs **after** `ValidationConfig::$allows` has been applied, by design: a callback can add
values the server already knows, but cannot use this hook to slip unvetted user input past the
mass-assignment guard. Note that `ValidationConfig::$records`, if set, still narrows the payload
afterwards — a key added here must also be listed in `records` when that option is in use.

Records are encrypted at rest, but do consider whether adding personal data such as IP addresses
fits the site's privacy policy and retention rules.

### `tofu_admin_page_capability`

Filters the capability required to view recorded submissions in wp-admin.

```php
apply_filters( 'tofu_admin_page_capability', string $capability );
```

Defaults to `manage_options`. A non-string or empty return value falls back to that default. The
filter governs both the menu entry and the page itself.

```php
add_filter( 'tofu_admin_page_capability', fn () => 'edit_pages' );
```

> Submission records may contain personal data. Widen this only to roles that are meant to see it.

---

## What is *not* a hook

Deliberate omissions, so that the boundary stays predictable:

| | Why |
|---|---|
| Filtering `FormConfig` at registration | It would let unrelated code rewrite `$allows` (the mass-assignment guard) and the mail recipients. The configuration already lives in your own theme code. |
| Filtering submitted values before validation | Same reason — it would run before `$allows` is applied. Use `ValidationConfig::$after` to normalise values. |
| Relaxing or replacing nonce verification | Nonce checks are not optional. |
| Filtering the form markup | Page templates are already entirely yours; that is the point of the plugin. |
| Cancelling a submission mid-flight | There is no user-facing error path for it yet. Reject the submission in validation instead. |

## See also

- [ValidationConfig](../settings/validationconfig.md) — the per-form `after` closure, for
  normalisation and cross-field validation belonging to a single form.
- [FormConfig](../settings/formconfig.md) — the per-form configuration surface.
