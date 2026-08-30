<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

/**
 * The field must carry a value.
 *
 * Implicit, so it runs even when the value is empty or the key is absent —
 * that is the whole point of it.
 */
class RequiredRule extends Rule
{
    protected bool $implicit = true;
    protected string $message = 'rule.required';

    public function check(mixed $value): bool
    {
        return !Value::isEmpty($value);
    }
}
