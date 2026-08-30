<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

class NumericRule extends Rule
{
    protected string $message = 'rule.numeric';

    public function check(mixed $value): bool
    {
        return is_numeric($value);
    }
}
