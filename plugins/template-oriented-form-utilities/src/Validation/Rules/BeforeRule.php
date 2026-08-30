<?php

namespace TofuPlugin\Validation\Rules;

class BeforeRule extends AfterRule
{
    protected string $message = 'rule.before';
    protected bool $mustBeLater = false;
}
