<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

class DigitsBetweenRule extends Rule
{
    protected string $message = 'rule.digits_between';
    protected array $fillableParams = ['min', 'max'];

    public function check(mixed $value): bool
    {
        $this->assertHasRequiredParameters($this->fillableParams);

        $string = Value::toStringOrNull($value);
        if ($string === null || preg_match('/^\d+$/D', $string) !== 1) {
            return false;
        }

        $length = strlen($string);

        return $length >= (int) $this->parameter('min')
            && $length <= (int) $this->parameter('max');
    }
}
