<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\UploadedFileInspector;

/**
 * Maximum upload size, expressed in megabytes.
 *
 * Skips silently when no file was chosen, so an optional upload does not
 * report a size error — pair it with `custom_required_file` to make one
 * mandatory.
 *
 * Usage in rules: 'attachment' => 'max_mb:5'
 */
class MaxMbRule extends Rule
{
    protected string $message = 'rule.max_mb';
    protected array $fillableParams = ['max_mb'];

    private const BYTES_PER_MB = 1024 * 1024;

    public function check(mixed $value): bool
    {
        $this->assertHasRequiredParameters($this->fillableParams);

        if (UploadedFileInspector::shouldSkip($value)) {
            return true;
        }

        $size = UploadedFileInspector::size($value);
        if ($size === null) {
            return false;
        }

        return $size / self::BYTES_PER_MB <= (float) $this->parameter('max_mb');
    }
}
