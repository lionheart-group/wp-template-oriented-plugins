<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

class TypeArrayRule extends Rule
{
    protected string $message = 'rule.array';

    public function check(mixed $value): bool
    {
        return is_array($value);
    }
}
