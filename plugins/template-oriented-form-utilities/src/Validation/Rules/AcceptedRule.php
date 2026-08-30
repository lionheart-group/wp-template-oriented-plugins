<?php

namespace TofuPlugin\Validation\Rules;

/**
 * The field must carry one of the affirmative values a checkbox submits.
 */
class AcceptedRule extends ValueListRule
{
    protected string $message = 'rule.accepted';

    protected function allowed(): array
    {
        return ['yes', 'on', '1', 1, true, 'true'];
    }

    protected function parameterName(): string
    {
        return 'accepted';
    }
}
