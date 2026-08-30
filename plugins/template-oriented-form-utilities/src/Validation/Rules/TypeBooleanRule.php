<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

/**
 * Accepts the values an HTML form can plausibly submit for a boolean, not
 * just PHP's true/false — a checkbox arrives as the string "1" or "0".
 */
class TypeBooleanRule extends Rule
{
    protected string $message = 'rule.boolean';

    public function check(mixed $value): bool
    {
        return in_array($value, [true, false, 1, 0, '1', '0'], true);
    }
}
