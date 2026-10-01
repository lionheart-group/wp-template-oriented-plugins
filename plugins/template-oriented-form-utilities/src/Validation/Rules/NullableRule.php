<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

/**
 * Marks a field as optional: when its value is empty, every other rule on
 * the field is skipped — including implicit ones like `required`.
 *
 * NOT a no-op. `nullable|required` accepts both a missing key and an empty
 * value, so implementing this as a rule that simply passes would silently
 * flip such a field from optional to mandatory. The short-circuit lives in
 * Validator::validateAttribute().
 */
class NullableRule extends Rule
{
    protected string $message = 'rule.default';

    public function check(mixed $value): bool
    {
        return true;
    }
}
