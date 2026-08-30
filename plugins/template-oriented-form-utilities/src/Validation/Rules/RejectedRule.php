<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

/**
 * The inverse of `accepted`: the field must carry a negative value.
 */
class RejectedRule extends Rule
{
    protected bool $implicit = true;
    protected string $message = 'rule.rejected';

    /** @var list<string|int|bool> */
    protected const REJECTED = ['no', 'off', '0', 0, false, 'false'];

    public function __construct()
    {
        // Surfaced as :rejected in the message.
        $this->params['rejected'] = self::REJECTED;
    }

    public function check(mixed $value): bool
    {
        return in_array($value, self::REJECTED, true);
    }
}
