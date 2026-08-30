<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

class DifferentRule extends Rule
{
    protected string $message = 'rule.different';
    protected array $fillableParams = ['field'];

    public function check(mixed $value): bool
    {
        $this->assertHasRequiredParameters($this->fillableParams);

        $other = $this->attribute()?->value((string) $this->parameter('field'));

        return $value != $other;
    }
}
