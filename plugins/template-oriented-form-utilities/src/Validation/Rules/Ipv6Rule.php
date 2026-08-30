<?php

namespace TofuPlugin\Validation\Rules;

class Ipv6Rule extends IpRule
{
    protected string $message = 'rule.ipv6';
    protected int $flags = FILTER_FLAG_IPV6;
}
