<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

/**
 * Supplies a fallback value when the field was left empty. Backs both
 * `default` and `defaults`.
 *
 * Never fails — it exists to modify the value, not to reject it. The
 * substitution itself happens in Models\Validation, which reads
 * appliedDefaults() off the validator after the run.
 */
class DefaultsRule extends Rule
{
    protected bool $implicit = true;
    protected string $message = 'rule.default_value';
    protected array $fillableParams = ['default'];

    public function check(mixed $value): bool
    {
        return true;
    }

    /**
     * The fallback to apply, or null when the field already has a value.
     */
    public function defaultFor(mixed $value): mixed
    {
        return Value::isEmpty($value) ? $this->parameter('default') : null;
    }
}
