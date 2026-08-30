<?php

namespace TofuPlugin\Validation\Rules;

class MinRule extends SizeComparisonRule
{
    protected string $message = 'rule.min';
    protected array $fillableParams = ['min'];
    protected ?string $lowerBoundParam = 'min';
}
