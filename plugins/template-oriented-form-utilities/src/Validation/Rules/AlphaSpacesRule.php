<?php

namespace TofuPlugin\Validation\Rules;

class AlphaSpacesRule extends CharacterClassRule
{
    protected string $message = 'rule.alpha_spaces';
    protected string $pattern = '/^[\pL\pM\s]++$/uD';
}
