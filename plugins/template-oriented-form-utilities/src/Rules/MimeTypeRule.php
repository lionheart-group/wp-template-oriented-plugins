<?php

namespace TofuPlugin\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\UploadedFileInspector;

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
        if (empty($allowedTypes) || !is_array($allowedTypes)) {
            throw new \InvalidArgumentException('mime_type rule requires at least one MIME type parameter.');
        }

        // Skip if no file uploaded
        if (UploadedFileInspector::shouldSkip($value)) {
            return true;
        }

        // Sniffs the file's actual content, not the client-supplied type —
        // shared with the engine's own file rules so the two cannot drift.
        $fileMimeType = UploadedFileInspector::detectMimeType($value);

        return in_array($fileMimeType, $allowedTypes, true);
    }
}
