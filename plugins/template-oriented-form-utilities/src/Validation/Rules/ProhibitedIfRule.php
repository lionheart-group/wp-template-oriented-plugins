<?php

namespace TofuPlugin\Validation\Rules;

class ProhibitedIfRule extends ConditionalOnValueRule
{
    protected string $message = 'rule.prohibited_if';
    protected bool $demandsValue = false;
}
