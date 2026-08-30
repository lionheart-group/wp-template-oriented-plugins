<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

/**
 * Digits only, of an exact length — postal codes, fixed-width IDs.
 */
class DigitsRule extends Rule
{
    protected string $message = 'rule.digits';
    protected array $fillableParams = ['length'];

    public function check(mixed $value): bool
    {
        $this->assertHasRequiredParameters($this->fillableParams);

        $string = Value::toStringOrNull($value);
        if ($string === null) {
            return false;
        }

        return preg_match('/^\d+$/D', $string) === 1
            && strlen($string) === (int) $this->parameter('length');
    }
}
