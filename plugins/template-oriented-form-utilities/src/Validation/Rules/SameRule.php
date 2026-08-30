<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

/**
 * Must equal another field — the email-confirmation pattern.
 *
 * Compares loosely, so the string "1" and the integer 1 count as the same
 * answer.
 */
class SameRule extends Rule
{
    protected string $message = 'rule.same';
    protected array $fillableParams = ['field'];

    public function check(mixed $value): bool
    {
        $this->assertHasRequiredParameters($this->fillableParams);

        $other = $this->attribute()?->value((string) $this->parameter('field'));

        return $value == $other;
    }
}
