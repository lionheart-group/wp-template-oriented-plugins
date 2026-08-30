<?php

namespace TofuPlugin\Validation\Rules;

class BetweenRule extends SizeComparisonRule
{
    protected string $message = 'rule.between';
    protected array $fillableParams = ['min', 'max'];
    protected ?string $lowerBoundParam = 'min';
    protected ?string $upperBoundParam = 'max';
}
