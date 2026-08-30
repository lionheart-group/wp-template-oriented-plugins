<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

/**
 * The field must be empty.
 *
 * Unlike its conditional siblings this one is NOT implicit — matching the
 * engine it replaces, where the unconditional variant is skipped for an
 * empty value (which would trivially satisfy it anyway).
 */
class ProhibitedRule extends Rule
{
    protected string $message = 'rule.prohibited';

    public function check(mixed $value): bool
    {
        return Value::isEmpty($value);
    }
}
