<?php

namespace TofuPlugin\Validation\Rules;

class Ipv4Rule extends IpRule
{
    protected string $message = 'rule.ipv4';
    protected int $flags = FILTER_FLAG_IPV4;
}
