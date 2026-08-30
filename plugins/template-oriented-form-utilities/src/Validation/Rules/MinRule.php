<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

class MinRule extends Rule
{
    protected string $message = 'rule.min';
    protected array $fillableParams = ['min'];

    public function check(mixed $value): bool
    {
        $this->assertHasRequiredParameters($this->fillableParams);

        $min = Value::bytes($this->parameter('min'));
        $size = Value::size($value, $this->attribute());

        if ($min === null || $size === null) {
            return false;
        }

        return $size >= $min;
    }
}
