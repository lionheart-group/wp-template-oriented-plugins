<?php

namespace TofuPlugin\Validation\Rules;

class RequiredUnlessRule extends ConditionalOnValueRule
{
    protected string $message = 'rule.required_unless';
    protected bool $firesOnMatch = false;
}
