<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

/**
 * Backs both `integer` and `number`.
 *
 * Only `integer` (and `numeric`) switch the size rules to numeric
 * comparison — `number` deliberately does not, matching the engine this
 * replaced. See Support\Value::size().
 */
class TypeIntegerRule extends Rule
{
    protected string $message = 'rule.integer';

    public function check(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }
}
