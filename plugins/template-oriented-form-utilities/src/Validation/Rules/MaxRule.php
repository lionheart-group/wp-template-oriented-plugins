<?php

namespace TofuPlugin\Validation\Rules;

class MaxRule extends SizeComparisonRule
{
    protected string $message = 'rule.max';
    protected array $fillableParams = ['max'];
    protected ?string $upperBoundParam = 'max';
}
