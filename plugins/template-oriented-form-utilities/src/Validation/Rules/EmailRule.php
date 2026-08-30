<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

/**
 * Exactly filter_var()'s FILTER_VALIDATE_EMAIL, verified against an
 * adversarial corpus with zero divergence.
 *
 * Deliberately NOT WordPress's is_email(), which is stricter in places and
 * would start rejecting addresses that pass today.
 */
class EmailRule extends Rule
{
    protected string $message = 'rule.email';

    public function check(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
}
