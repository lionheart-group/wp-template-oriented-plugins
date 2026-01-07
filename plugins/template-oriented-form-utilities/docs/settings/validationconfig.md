# ValidationConfig

Configuration for form field validation and filtering.

## Usage

```php
use TofuPlugin\Structure\ValidationConfig;

$validation = new ValidationConfig(
    allows: ['name', 'email', 'message'],
    rules: [
        'name' => 'required|max_len:200',
        'email' => 'required|valid_email',
        'message' => 'required|max_len:1000',
    ],
    filters: [
        'name' => 'trim|sanitize_string',
        'email' => 'trim|sanitize_email',
        'message' => 'trim|sanitize_string',
    ],
    names: [
        'name' => 'Full Name',
        'email' => 'Email Address',
        'message' => 'Message',
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
    after: function ($values, $errors) {
        // Custom validation logic
    },
);
```

## Properties

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|
| `allows` | `array` | Yes | - | List of allowed field names. Only specified fields are stored in the session. |
| `rules` | `array` | Yes | - | Validation rules for each field. Uses [GUMP validation rules](https://github.com/Wixel/GUMP?tab=readme-ov-file#available-validators). |
| `filters` | `array` | Yes | - | Filtering/sanitization rules for each field. Uses [GUMP filter rules](https://github.com/Wixel/GUMP?tab=readme-ov-file#available-filters). |
| `names` | `array` | Yes | - | Human-readable field names used in error messages. |
| `messages` | `array` | No | `[]` | Custom error messages per field and validation rule. |
| `after` | `?Closure` | No | `null` | Custom callback function for additional validation logic. |

## For file upload fields validation rules

The default GUMP validation does not fit well for file upload fields. You can use the following custom rules:

- `custom_required_file`: Ensures a file is uploaded.
    - **The default `required_file` rule does not work when you back to the input page after validation errors or from the confirmation page.**
- `max_mb,<number>`: Validates the maximum file size in megabytes.
- `mime_type,<type1>;<type2>;...`: Validates the file MIME type.

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
