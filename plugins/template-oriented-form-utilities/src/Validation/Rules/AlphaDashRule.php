<?php

namespace TofuPlugin\Validation\Rules;

class AlphaDashRule extends CharacterClassRule
{
    protected string $message = 'rule.alpha_dash';
    protected string $pattern = '/^[\pL\pM\pN_-]++$/uD';
}
