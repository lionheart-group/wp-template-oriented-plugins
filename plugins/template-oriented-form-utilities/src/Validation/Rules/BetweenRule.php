<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

class BetweenRule extends Rule
{
    protected string $message = 'rule.between';
    protected array $fillableParams = ['min', 'max'];

    public function check(mixed $value): bool
    {
        $this->assertHasRequiredParameters($this->fillableParams);

        $min = Value::bytes($this->parameter('min'));
        $max = Value::bytes($this->parameter('max'));
        $size = Value::size($value, $this->attribute());

        if ($min === null || $max === null || $size === null) {
            return false;
        }

        return $size >= $min && $size <= $max;
    }
}
