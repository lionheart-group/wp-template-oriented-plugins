<?php

namespace TofuPlugin\Validation\Rules;

class LowercaseRule extends CaseRule
{
    protected string $message = 'rule.lowercase';

    protected function transform(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }
}
