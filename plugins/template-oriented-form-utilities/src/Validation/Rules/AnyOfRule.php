<?php

namespace TofuPlugin\Validation\Rules;

/**
 * Whitelist that also accepts an array value, requiring every element to be
 * allowed — the multi-select counterpart to `in`. A scalar is checked
 * exactly as `in` would check it.
 */
class AnyOfRule extends InRule
{
    protected string $message = 'rule.any_of';

    public function check(mixed $value): bool
    {
        /** @var array<int, mixed> $allowed */
        $allowed = $this->parameter('allowed_values', []);

        if (!is_array($value)) {
            return self::contains($allowed, $value);
        }

        foreach ($value as $item) {
            if (!self::contains($allowed, $item)) {
                return false;
            }
        }

        return true;
    }
}
