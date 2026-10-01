<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

/**
 * Validates the field only when its key is present in the input at all.
 *
 * Distinct from `nullable`: `sometimes|required` still rejects a key that is
 * present but empty — it only exempts a key that was never submitted.
 * The short-circuit lives in Validator::validateAttribute().
 */
class SometimesRule extends Rule
{
    protected bool $implicit = true;
    protected string $message = 'rule.required';

    public function check(mixed $value): bool
    {
        return true;
    }
}
