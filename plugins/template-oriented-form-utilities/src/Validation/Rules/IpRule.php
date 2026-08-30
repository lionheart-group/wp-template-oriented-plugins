<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

/**
 * A valid IP address.
 *
 * Base for the family: `ipv4` and `ipv6` differ only by the filter flag
 * they narrow to.
 */
class IpRule extends Rule
{
    protected string $message = 'rule.ip';

    /**
     * Extra FILTER_VALIDATE_IP flags. Zero accepts any address family.
     */
    protected int $flags = 0;

    public function check(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_IP, $this->flags) !== false;
    }
}
