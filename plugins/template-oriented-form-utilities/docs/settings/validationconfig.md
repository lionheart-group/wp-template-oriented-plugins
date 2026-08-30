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
| `rules` | `array` | Yes | - | Validation rules for each field. See [Available rules](#available-rules) below. |
| `names` | `array` | Yes | - | Human-readable field names used in error messages. |
| `messages` | `array` | No | `[]` | Custom error messages per field and validation rule. |
| `after` | `?Closure` | No | `null` | Custom callback function for additional validation logic. |
| `records` | `string[]` | No | `[]` | Fields to persist when [`FormConfig::$saveToDatabase`](./formconfig.md) is `true`. Sits alongside `allows` for easy comparison — an empty array (default) persists every field in `allows`; a non-empty array is a further filter, so only the named fields end up in the encrypted `wp_tofu_records` payload. Fields absent from `allows` are silently skipped. Use this to exclude sensitive fields (passwords, tokens) from the saved record without touching validation rules. |

## Writing rules

Rules are given per field, either as a pipe-delimited string or as an array:

```php
rules: [
    'name'  => 'required|max:200',
    'email' => ['required', 'email', 'max' => 255],
    'code'  => ['regex' => '/^(a|b)-\d+$/'],
]
```

Both forms are equivalent. The array form exists because a `regex` pattern containing a
pipe cannot be written in the string form — the split would cut it in half.

Parameters follow a colon and are separated by commas: `between:3,20`,
`mime_type:image/jpeg,image/png`. To include a comma *inside* a parameter, quote it:
`in:"a,b",c`.

**An unrecognised rule name is a fatal error, not a warning.** It is raised while the
form's configuration is being parsed, before any input is looked at, so a typo like
`requried|max:200` fails loudly on the first request instead of quietly skipping the
check.

### Empty values and optional fields

A rule is **skipped entirely when the field is empty**, unless it is marked implicit in
the table below. That is what makes `'phone' => 'max:20'` an optional field with a length
cap rather than a required one.

Empty means `null`, an empty array, or a string of nothing but whitespace — including a
full-width space (U+3000), which a Japanese IME produces for the space bar. `'0'`, `0`
and `false` are **not** empty: zero is a legitimate answer.

### How `max` and `min` measure a value

| Value | Measured as |
|---|---|
| String | Character count (`mb_strlen`), unless the field also has `numeric` or `integer` |
| String with `numeric` / `integer` on the same field | Its numeric value |
| Integer, float, boolean | Its numeric value |
| Uploaded file | Its size in bytes |
| Any other array | Its element count |

So `'phone' => 'max:20'` accepts `"0312345678"` — ten characters. Add `numeric` and the
same rule compares the number instead, which is rarely what a phone or postal code field
wants.

## Available rules

`✓` in the *Always runs* column marks an implicit rule: one that runs even when the field
is empty or absent. Everything else is skipped for an empty value.

### Presence

| Rule | Parameters | Always runs | Notes |
|---|---|---|---|
| `required` | — | ✓ | Field must have a value. Understands file inputs. |
| `required_file` | — | ✓ | A file must be attached, and the value must actually be a file. Also registered as `custom_required_file`. |
| `required_if` | `field,value…` | ✓ | Required when the named field holds one of the values. |
| `required_unless` | `field,value…` | ✓ | Required unless the named field holds one of the values. |
| `required_with` | `field…` | ✓ | Required when any named field has a value. |
| `required_with_all` | `field…` | ✓ | Required when every named field has a value. |
| `required_without` | `field…` | ✓ | Required when any named field is empty. |
| `required_without_all` | `field…` | ✓ | Required when every named field is empty. |
| `requires` | `field…` | ✓ | The named fields must all be filled in. |
| `present` | — | ✓ | The key must be submitted; the value may be empty. |
| `accepted` | — | ✓ | Consent checkboxes: `yes`, `on`, `1`, `true`. |
| `rejected` | — | ✓ | The inverse: `no`, `off`, `0`, `false`. |

### Prohibition

| Rule | Parameters | Always runs | Notes |
|---|---|---|---|
| `prohibited` | — | | Field must be empty. |
| `prohibited_if` | `field,value…` | ✓ | Not allowed when the named field holds one of the values. |
| `prohibited_unless` | `field,value…` | ✓ | Allowed only when the named field holds one of the values. |
| `prohibited_with` | `field…` | ✓ | Not allowed alongside the named fields. |
| `prohibited_with_all` | `field…` | ✓ | Not allowed when every named field is filled in. |
| `prohibited_without` | `field…` | ✓ | Requires the named fields to be filled in **and** this one too. |
| `prohibited_without_all` | `field…` | ✓ | As above, for every named field. |

### Flow control

| Rule | Parameters | Always runs | Notes |
|---|---|---|---|
| `nullable` | — | | Empty value ⇒ skip every rule on the field, `required` included. |
| `sometimes` | — | ✓ | Missing key ⇒ skip every rule. A key that is present but empty is still checked. |
| `default` | `value` | ✓ | Substitutes a value when the field is empty. Also registered as `defaults`. |
| `callback` | closure | ✓ | Array form only: `['callback' => fn ($v) => …]`. |

### Types

| Rule | Parameters | Always runs | Notes |
|---|---|---|---|
| `string` · `array` | — | | |
| `boolean` | — | | Accepts `true`, `false`, `1`, `0`, `"1"`, `"0"`. |
| `numeric` | — | | Any number, including numeric strings. |
| `integer` | — | | A whole number. Also registered as `number`. |
| `float` | — | | A genuine PHP float — the string `"1.5"` does not qualify. Use `numeric` for submitted numbers. |

### Size

| Rule | Parameters | Always runs | Notes |
|---|---|---|---|
| `max` · `min` | `n` | | See the measurement table above. |
| `between` | `min,max` | | |
| `length` | `n` | | Exact character count, always — never a numeric comparison. |
| `digits` | `n` | | Digits only, exactly `n` of them. |
| `digits_between` | `min,max` | | Digits only, length in range. |

### Sets and comparisons

| Rule | Parameters | Always runs | Notes |
|---|---|---|---|
| `in` · `not_in` | `value…` | | Whitelist / blacklist. |
| `any_of` | `value…` | | Like `in`, but every element of an array value must be allowed. |
| `same` · `different` | `field` | | Compares against another field — the email-confirmation pattern. |
| `array_must_have_keys` | `key…` | | |
| `array_can_only_have_keys` | `key…` | | |

### Strings and formats

| Rule | Parameters | Always runs | Notes |
|---|---|---|---|
| `email` | — | | Matches PHP's `FILTER_VALIDATE_EMAIL` exactly. |
| `url` · `json` | — | | |
| `ip` · `ipv4` · `ipv6` | — | | |
| `uuid` | — | ✓ | Rejects the NIL UUID. |
| `phone` | — | | E.164 only (`+81312345678`) — not local formats like `03-1234-5678`. Use `regex` for those. |
| `regex` | pattern | | Also registered as `matches`. Use the array form for a pattern containing `\|`. |
| `alpha` · `alpha_num` · `alpha_dash` · `alpha_spaces` | — | | Unicode-aware, so Japanese text counts as letters. |
| `lowercase` · `uppercase` | — | | |
| `starts_with` · `ends_with` | `text` | | |

### Dates

| Rule | Parameters | Always runs | Notes |
|---|---|---|---|
| `date` | `format` (optional) | | Any parseable date, or an exact format such as `date:Y-m-d`. |
| `after` · `before` | `date` | | Compares against the given date. An unparseable **parameter** is a configuration error and throws; unparseable **input** is an ordinary validation failure. |

### Files

| Rule | Parameters | Always runs | Notes |
|---|---|---|---|
| `required_file` | — | ✓ | See Presence, above. |
| `max_mb` | `n` | | Maximum size in megabytes. |
| `mime_type` | `type…` | | Checks the file's **contents**. |
| `mimes` · `extension` | `ext…` | | Check the **filename extension**, which the visitor chooses. |
| `uploaded_file` | `min,max,type…` | | Combined check; sizes accept suffixes such as `5M`. |

All file rules except `required_file` skip silently when no file was chosen, so an
optional upload does not report a type or size error. Pair them with `required_file` to
make an upload mandatory.

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
