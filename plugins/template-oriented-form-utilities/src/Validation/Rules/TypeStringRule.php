<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

class TypeStringRule extends Rule
{
    protected string $message = 'rule.string';

    public function check(mixed $value): bool
    {
        return is_string($value);
    }
}
