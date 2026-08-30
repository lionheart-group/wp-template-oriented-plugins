<?php

namespace TofuPlugin\Validation\Rules;

class ProhibitedWithoutAllRule extends RequiredWithoutAllRule
{
    protected string $message = 'rule.prohibited_without_all';
    protected bool $demandsValue = false;
}
