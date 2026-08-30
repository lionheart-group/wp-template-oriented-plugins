<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

class Ipv6Rule extends Rule
{
    protected string $message = 'rule.ipv6';

    public function check(mixed $value): bool
    {
        return is_string($value)
            && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }
}
