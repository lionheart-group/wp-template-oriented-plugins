<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\UploadedFileInspector;

/**
 * Allowed file EXTENSIONS for an upload (`mimes:txt,pdf`).
 *
 * Despite the name this checks the extension, not the content type — use
 * the plugin's `mime_type` rule when the actual bytes matter, since an
 * extension is whatever the visitor chose to call the file.
 */
class MimesRule extends Rule
{
    protected string $message = 'rule.mimes';

    public function fillParameters(array $params): self
    {
        $this->params['allowed_types'] = array_map(
            static fn ($type): string => strtolower(ltrim((string) $type, '.')),
            $params
        );

        return $this;
    }

    public function check(mixed $value): bool
    {
        if (UploadedFileInspector::shouldSkip($value)) {
            return true;
        }

        $extension = UploadedFileInspector::extension($value);
        if ($extension === null) {
            return false;
        }

        /** @var array<int, string> $allowed */
        $allowed = $this->parameter('allowed_types', []);

        return in_array($extension, $allowed, true);
    }
}
