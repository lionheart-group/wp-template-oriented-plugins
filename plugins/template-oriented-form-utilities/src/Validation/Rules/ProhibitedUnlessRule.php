<?php

namespace TofuPlugin\Validation\Rules;

class ProhibitedUnlessRule extends ConditionalOnValueRule
{
    protected string $message = 'rule.prohibited_unless';
    protected bool $demandsValue = false;
    protected bool $firesOnMatch = false;
}
