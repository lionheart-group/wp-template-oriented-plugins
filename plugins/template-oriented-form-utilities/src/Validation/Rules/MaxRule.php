<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

/**
 * Upper bound on the value's size — see Support\Value::size() for what
 * "size" means for each value type. A string is measured by character
 * count, so `max:20` on a phone field accepts "0312345678".
 */
class MaxRule extends Rule
{
    protected string $message = 'rule.max';
    protected array $fillableParams = ['max'];

    public function check(mixed $value): bool
    {
        $this->assertHasRequiredParameters($this->fillableParams);

        $max = Value::bytes($this->parameter('max'));
        $size = Value::size($value, $this->attribute());

        if ($max === null || $size === null) {
            return false;
        }

        return $size <= $max;
    }
}
