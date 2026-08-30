<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

/**
 * Whitelist for select / radio input.
 *
 * Comparison is PHP's loose `==`, not a strict or string-cast comparison.
 * That is what lets `in:1,2,3` match the string "1" a form actually submits
 * — and, less obviously, why a boolean true matches any non-empty candidate.
 * Tightening it would change which submissions are accepted, so it stays
 * loose.
 */
class InRule extends Rule
{
    protected string $message = 'rule.in';

    public function fillParameters(array $params): self
    {
        $this->params['allowed_values'] = $params;

        return $this;
    }

    public function check(mixed $value): bool
    {
        /** @var array<int, mixed> $allowed */
        $allowed = $this->parameter('allowed_values', []);

        return self::contains($allowed, $value);
    }

    /**
     * @param array<int, mixed> $allowed
     */
    protected static function contains(array $allowed, mixed $value): bool
    {
        // Intentionally non-strict — see the class docblock.
        return in_array($value, $allowed, false);
    }
}
