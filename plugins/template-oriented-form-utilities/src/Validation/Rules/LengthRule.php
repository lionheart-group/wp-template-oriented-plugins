<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

/**
 * An exact character count. Unlike max/min this is always about length,
 * never numeric magnitude.
 */
class LengthRule extends Rule
{
    protected string $message = 'rule.length';
    protected array $fillableParams = ['length'];

    public function check(mixed $value): bool
    {
        $this->assertHasRequiredParameters($this->fillableParams);

        $string = Value::toStringOrNull($value);
        if ($string === null) {
            return false;
        }

        return mb_strlen($string, 'UTF-8') === (int) $this->parameter('length');
    }
}
