<?php

namespace TofuPlugin\Validation\Rules;

class DifferentRule extends SameRule
{
    protected string $message = 'rule.different';
    protected bool $mustMatch = false;
}
