<?php

namespace TofuPlugin\Validation\Rules;

class AlphaNumRule extends CharacterClassRule
{
    protected string $message = 'rule.alpha_num';
    protected string $pattern = '/^[\pL\pN]++$/uD';
}
