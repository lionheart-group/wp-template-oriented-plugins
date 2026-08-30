<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

/**
 * Must equal another field — the email-confirmation pattern.
 *
 * Base for `different`, which is the same comparison with the polarity
 * flipped. Comparison is loose, so the string "1" and the integer 1 count
 * as the same answer.
 */
class SameRule extends Rule
{
    protected string $message = 'rule.same';
    protected array $fillableParams = ['field'];

    /**
     * True for `same`, false for `different`.
     */
    protected bool $mustMatch = true;

    public function check(mixed $value): bool
    {
        $this->assertHasRequiredParameters($this->fillableParams);

        $other = $this->attribute()?->value((string) $this->parameter('field'));
        $matches = $value == $other;

        return $this->mustMatch ? $matches : !$matches;
    }
}
