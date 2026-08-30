<?php

namespace TofuPlugin\Validation\Rules;

/**
 * Digits only, of an exact length — postal codes, fixed-width IDs.
 */
class DigitsRule extends DigitsBetweenRule
{
    protected string $message = 'rule.digits';
    protected array $fillableParams = ['length'];

    protected function minLength(): mixed
    {
        return $this->parameter('length');
    }

    protected function maxLength(): mixed
    {
        return $this->parameter('length');
    }
}
