<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\UploadedFileInspector;

/**
 * Allowed content types for an upload, given as comma-separated MIME types.
 *
 * Unlike `mimes` and `extension`, which trust the file's name, this sniffs
 * the actual bytes — a text file renamed to .pdf does not pass
 * `mime_type:application/pdf`.
 *
 * Skips silently when no file was chosen.
 *
 * Usage in rules: 'attachment' => 'mime_type:application/pdf,image/jpeg'
 */
class MimeTypeRule extends Rule
{
    protected string $message = 'rule.mime_type';

    /**
     * Collects every CSV parameter as the allowed-type list.
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

        if (UploadedFileInspector::shouldSkip($value)) {
            return true;
        }

        /** @var array<int, string> $allowedTypes */
        return UploadedFileInspector::hasAllowedMimeType($value, $allowedTypes);
    }
}
