<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Support\Value;

/**
 * Passes only when every named sibling has a value AND this field does too.
 *
 * The name and message ("not allowed when one of the following fields is
 * absent") suggest a prohibition, but the engine this replaced also demands
 * a value on the field itself, so an empty field fails even when the
 * siblings are all present. Reproduced as measured rather than as the name
 * reads.
 */
class ProhibitedWithoutRule extends RequiredWithoutRule
{
    protected string $message = 'rule.prohibited_without';

    public function check(mixed $value): bool
    {
        foreach ($this->fields() as $field) {
            if (!$this->siblingIsPresent($field)) {
                return false;
            }
        }

        return !Value::isEmpty($value);
    }
}
