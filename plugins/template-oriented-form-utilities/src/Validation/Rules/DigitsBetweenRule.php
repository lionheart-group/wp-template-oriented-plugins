<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

/**
 * Digits only, with a length between two bounds.
 *
 * Base for `digits`, which is the same check with both bounds set to the
 * same number.
 */
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

        return $length >= (int) $this->minLength() && $length <= (int) $this->maxLength();
    }

    protected function minLength(): mixed
    {
        return $this->parameter('min');
    }

    protected function maxLength(): mixed
    {
        return $this->parameter('max');
    }
}
