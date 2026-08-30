<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

class EndsWithRule extends Rule
{
    protected string $message = 'rule.ends_with';
    protected array $fillableParams = ['compare_with'];

    public function check(mixed $value): bool
    {
        $this->assertHasRequiredParameters($this->fillableParams);

        $string = Value::toStringOrNull($value);

        return $string !== null && str_ends_with($string, (string) $this->parameter('compare_with'));
    }
}
