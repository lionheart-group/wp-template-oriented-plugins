<?php

namespace TofuPlugin\Validation\Rules;

class AlphaRule extends CharacterClassRule
{
    protected string $message = 'rule.alpha';
    protected string $pattern = '/^\pL++$/uD';
}
