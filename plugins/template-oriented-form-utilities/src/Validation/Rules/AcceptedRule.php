<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

/**
 * The field must carry one of the affirmative values a checkbox submits.
 *
 * Implicit, because an unchecked checkbox submits nothing at all — a
 * non-implicit version would be skipped exactly when it matters and let a
 * mandatory consent box through.
 */
class AcceptedRule extends Rule
{
    protected bool $implicit = true;
    protected string $message = 'rule.accepted';

    /** @var list<string|int|bool> */
    protected const ACCEPTED = ['yes', 'on', '1', 1, true, 'true'];

    public function __construct()
    {
        // Surfaced as :accepted in the message.
        $this->params['accepted'] = self::ACCEPTED;
    }

    public function check(mixed $value): bool
    {
        return in_array($value, self::ACCEPTED, true);
    }
}
