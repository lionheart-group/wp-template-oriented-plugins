<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

/**
 * The field's key must appear in the input — the value may be empty.
 *
 * This is the only rule that distinguishes "submitted as blank" from "not
 * submitted at all".
 */
class PresentRule extends Rule
{
    protected bool $implicit = true;
    protected string $message = 'rule.present';

    public function check(mixed $value): bool
    {
        return $this->attribute()?->exists() ?? false;
    }
}
