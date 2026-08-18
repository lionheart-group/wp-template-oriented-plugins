<?php

namespace TofuPlugin\Rules;

use Somnambulist\Components\Validation\Rule;
use TofuPlugin\Consts;

/**
 * Custom validation rule: required file upload.
 *
 * Passes if a new file was uploaded (UPLOAD_ERR_OK) OR a session-persisted
 * file ID is present in the __tofu_uploaded_files input.
 *
 * Usage in rules: 'attachment' => 'custom_required_file'
 *
 * @package TofuPlugin\Rules
 */
class RequiredFileRule extends Rule
{
    protected bool $implicit = true;
    protected string $message = 'rule.custom_required_file';

    public function check(mixed $value): bool
    {
        // New upload present
        if (is_array($value) && isset($value['error']) && $value['error'] === \UPLOAD_ERR_OK) {
            return true;
        }

        // Session-persisted file present
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
