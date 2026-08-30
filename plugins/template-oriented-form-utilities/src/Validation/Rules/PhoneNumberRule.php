<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

/**
 * An E.164 phone number: an optional `+`, a non-zero leading digit, then up
 * to fourteen more digits.
 *
 * Note this rejects the local formats Japanese forms usually collect
 * ("03-1234-5678" starts with a zero and contains separators) — `regex` is
 * the right tool for those.
 */
class PhoneNumberRule extends Rule
{
    protected string $message = 'rule.phone_number';

    public function check(mixed $value): bool
    {
        $string = Value::toStringOrNull($value);

        return $string !== null && preg_match('/^\+?[1-9]\d{1,14}$/D', $string) === 1;
    }
}
