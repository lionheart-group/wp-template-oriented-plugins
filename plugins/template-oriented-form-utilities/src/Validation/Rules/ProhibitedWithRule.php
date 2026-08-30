<?php

namespace TofuPlugin\Validation\Rules;

class ProhibitedWithRule extends RequiredWithRule
{
    protected string $message = 'rule.prohibited_with';
    protected bool $demandsValue = false;
}
