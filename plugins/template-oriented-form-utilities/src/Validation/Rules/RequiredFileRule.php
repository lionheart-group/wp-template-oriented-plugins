<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Consts;
use TofuPlugin\Validation\Rule;

/**
 * A file must be attached to this field.
 *
 * Plain `required` is wrong for file fields in both directions: the
 * `$_FILES` entry for "no file chosen" is a non-empty array, so `required`
 * passes when nothing was selected; and on the confirm step the key is
 * absent entirely, so `required` fails even though a file was uploaded a
 * request ago.
 *
 * Passes when a new upload arrived (UPLOAD_ERR_OK), or when the
 * `__tofu_uploaded_files` map still carries an ID for this field.
 *
 * Usage in rules: 'attachment' => 'custom_required_file'
 */
class RequiredFileRule extends Rule
{
    protected bool $implicit = true;
    protected string $message = 'rule.custom_required_file';

    public function check(mixed $value): bool
    {
        // A new upload in this request.
        if (is_array($value) && isset($value['error']) && $value['error'] === \UPLOAD_ERR_OK) {
            return true;
        }

        // Or one carried over from a previous step.
        $fieldName = $this->attribute()?->key();
        if ($fieldName !== null) {
            $uploadedFiles = $this->attribute()->value(Consts::UPLOADED_FILES_INPUT_NAME);
            if (is_array($uploadedFiles) && !empty($uploadedFiles[$fieldName])) {
                return true;
            }
        }

        return false;
    }
}
