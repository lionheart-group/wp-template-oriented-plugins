<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

class LowercaseRule extends Rule
{
    protected string $message = 'rule.lowercase';

    public function check(mixed $value): bool
    {
        $string = Value::toStringOrNull($value);

        return $string !== null && mb_strtolower($string, 'UTF-8') === $string;
    }
}
