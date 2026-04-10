<?php

namespace TofuPlugin\Rules;

use finfo;
use Somnambulist\Components\Validation\Rule;

/**
 * Custom validation rule: allowed MIME types for file uploads.
 *
 * Accepts one or more MIME types as comma-separated parameters.
 * Skips silently if no file is present.
 *
 * Usage in rules: 'attachment' => 'mime_type:application/pdf,image/jpeg'
 *
 * @package TofuPlugin\Rules
 */
class MimeTypeRule extends Rule
{
    protected string $message = 'rule.mime_type';

    /**
     * Override to collect all CSV params as an array under the 'types' key.
     */
    public function fillParameters(array $params): self
    {
        $this->params['types'] = $params;

        return $this;
    }

    public function check(mixed $value): bool
    {
        $allowedTypes = $this->parameter('types', []);
        if (empty($allowedTypes)) {
            throw new \InvalidArgumentException('mime_type rule requires at least one MIME type parameter.');
        }

        // Skip if no file uploaded
        if (!is_array($value) || empty($value['tmp_name'])) {
            return true;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $fileMimeType = $finfo->file($value['tmp_name']);

        return in_array($fileMimeType, $allowedTypes, true);
    }
}
