<?php

namespace TofuPlugin\Rules;

use Somnambulist\Components\Validation\Rule;

/**
 * Custom validation rule: maximum file size in megabytes.
 *
 * Skips silently if no file is present (pair with custom_required_file for required files).
 *
 * Usage in rules: 'attachment' => 'max_mb:5'
 *
 * @package TofuPlugin\Rules
 */
class MaxMbRule extends Rule
{
    protected string $message = 'rule.max_mb';
    protected array $fillableParams = ['max_mb'];

    public function check(mixed $value): bool
    {
        $this->assertHasRequiredParameters($this->fillableParams);

        // Skip if no file uploaded
        if (!is_array($value) || empty($value['tmp_name'])) {
            return true;
        }

        $maxMb = (float) $this->parameter('max_mb');
        $fileSizeMb = $value['size'] / (1024 * 1024);

        return $fileSizeMb <= $maxMb;
    }
}
