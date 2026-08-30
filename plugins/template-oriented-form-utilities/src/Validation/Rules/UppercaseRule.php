<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

class UppercaseRule extends Rule
{
    protected string $message = 'rule.uppercase';

    public function check(mixed $value): bool
    {
        $string = Value::toStringOrNull($value);

        return $string !== null && mb_strtoupper($string, 'UTF-8') === $string;
    }
}
