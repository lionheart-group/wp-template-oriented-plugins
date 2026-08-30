<?php

namespace TofuPlugin\Validation\Rules;

/**
 * The inverse of `accepted`: the field must carry a negative value.
 */
class RejectedRule extends ValueListRule
{
    protected string $message = 'rule.rejected';

    protected function allowed(): array
    {
        return ['no', 'off', '0', 0, false, 'false'];
    }

    protected function parameterName(): string
    {
        return 'rejected';
    }
}
