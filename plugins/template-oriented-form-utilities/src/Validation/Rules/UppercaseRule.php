<?php

namespace TofuPlugin\Validation\Rules;

class UppercaseRule extends CaseRule
{
    protected string $message = 'rule.uppercase';

    protected function transform(string $value): string
    {
        return mb_strtoupper($value, 'UTF-8');
    }
}
