# ValidationConfig

Configuration for form field validation.

## Usage

```php
use TofuPlugin\Structure\ValidationConfig;

$validation = new ValidationConfig(
    allows: ['name', 'email', 'message'],
    rules: [
        'name' => 'required|max:200',
        'email' => 'required|email',
        'message' => 'required|max:1000',
    ],
    names: [
        'name' => 'Full Name',
        'email' => 'Email Address',
        'message' => 'Message',
    ],
    messages: [
        'name' => [
            'required' => 'Please enter your name.',
            'max' => 'Your name must be within 200 characters.',
        ],
        'email' => [
            'required' => 'Please enter your email address.',
            'email' => 'Please enter a valid email address.',
        ],
    ],
    after: function ($values, $errors) {
        // Custom validation logic
    },
    // Only persist these fields when FormConfig::$saveToDatabase is true —
    // 'message' is intentionally omitted from the saved database record.
    records: ['name', 'email'],
);
```

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `allows` | `array` | Yes | - | List of allowed field names. Only specified fields are stored in the session. |
| `rules` | `array` | Yes | - | Validation rules for each field. Uses [somnambulist/validation rules](https://github.com/somnambulist-tech/validation?tab=readme-ov-file#available-rules). |
| `names` | `array` | Yes | - | Human-readable field names used in error messages. |
| `messages` | `array` | No | `[]` | Custom error messages per field and validation rule. |
| `after` | `?Closure` | No | `null` | Custom callback function for additional validation logic. |
| `records` | `string[]` | No | `[]` | Fields to persist when [`FormConfig::$saveToDatabase`](./formconfig.md) is `true`. Sits alongside `allows` for easy comparison — an empty array (default) persists every field in `allows`; a non-empty array is a further filter, so only the named fields end up in the encrypted `wp_tofu_records` payload. Fields absent from `allows` are silently skipped. Use this to exclude sensitive fields (passwords, tokens) from the saved record without touching validation rules. |

## File Upload Validation Rules

- `required_file`: Ensures a file is uploaded, and that the submitted value really is a file.
    - Also available under its former name `custom_required_file`. Both names run the same
      rule, so existing forms keep working — but prefer `required_file` in new code.
    - `required` also understands file fields, so either will do when you only need
      "an attachment must be present". Reach for `required_file` when the field must be a
      file and nothing else: `required` accepts any non-empty value, so a form posted
      outside the browser could satisfy it with an ordinary string.
- `max_mb:<number>`: Validates the maximum file size in megabytes.
- `mime_type:<type1>,<type2>,...`: Validates the file's MIME type by inspecting its
  **contents**, not its filename or the browser-supplied type — a text file renamed to
  `.pdf` does not pass `mime_type:application/pdf`.
    - `mimes:<ext1>,<ext2>` and `extension:<ext1>,<ext2>` check the filename extension
      instead, which the visitor chooses. Use `mime_type` when it matters.

> A file uploaded on the input page survives the trip to the confirmation page and back:
> the plugin keeps it in the session and re-associates it on each request. Validation
> confirms that against the server's own record, so a tampered form cannot claim an
> upload that is not there.

## Custom Validation with `after` Callback

The `after` callback allows you to add custom validation logic:

```php
after: function (
    \TofuPlugin\Models\FieldValueCollection $values,
    \TofuPlugin\Models\ValidationErrorCollection $errors
) {
    // Get a field value
    $nameValue = $values->getValue('name');

    if ($nameValue !== null && $nameValue->value === 'Test') {
        // Add a custom error
        $errors->addError('name', 'The name "Test" is not allowed.');
    }

    // You can also add computed values
    $values->addValue('submitted_at', date('Y-m-d H:i:s'));
}
```

> **Note on `confirmStep`:** a field added via `addValue()` inside `after` is *not* filtered by
> `allows` on the request where it's added — it will appear in the mail body immediately if the
> form has no confirm step (`confirmStep: false`), since input validation and mail-sending happen
> in the same request.
>
> If the form *does* have a confirm step, the added field is persisted to the session at the end
> of the input step, but is silently dropped when the session is restored for the confirm-page
> request — because that restore step filters by `allows`. The field will then be missing from
> the mail body, which is sent during the confirm step. **Add the field name to `allows` if your
> form uses `confirmStep: true` and you want a computed field from `after` to reach the mail
> body** (e.g. `submitted_at` in the example above).
