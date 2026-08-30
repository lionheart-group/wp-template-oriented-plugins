<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

class UrlRule extends Rule
{
    protected string $message = 'rule.url';

    public function check(mixed $value): bool
    {
        $string = Value::toStringOrNull($value);
        if ($string === null) {
            return false;
        }

        return filter_var($string, FILTER_VALIDATE_URL) !== false;
    }
}
