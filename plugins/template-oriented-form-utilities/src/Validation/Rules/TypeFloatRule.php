<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

/**
 * A genuine PHP float.
 *
 * Deliberately strict: the numeric STRING "1.5" does not satisfy this, and
 * neither does the integer 1. Use `numeric` to accept submitted numbers,
 * which is what a form field actually delivers.
 */
class TypeFloatRule extends Rule
{
    protected string $message = 'rule.float';

    public function check(mixed $value): bool
    {
        return is_float($value);
    }
}
