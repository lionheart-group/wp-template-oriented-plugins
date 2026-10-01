<?php

namespace TofuPlugin\Validation\Rules;

class ProhibitedWithAllRule extends RequiredWithAllRule
{
    protected string $message = 'rule.prohibited_with_all';
    protected bool $demandsValue = false;
}
