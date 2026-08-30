<?php

namespace TofuPlugin\Validation\Rules;

class RequiredIfRule extends ConditionalOnValueRule
{
    protected string $message = 'rule.required_if';
}
