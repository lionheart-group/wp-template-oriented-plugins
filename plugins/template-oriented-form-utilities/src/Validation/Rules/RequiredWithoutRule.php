<?php

namespace TofuPlugin\Validation\Rules;

/**
 * Required as soon as ANY of the named siblings is empty.
 */
class RequiredWithoutRule extends ConditionalRule
{
    protected string $message = 'rule.required_without';

    protected function conditionApplies(): bool
    {
        foreach ($this->fields() as $field) {
            if (!$this->siblingIsPresent($field)) {
                return true;
            }
        }

        return false;
    }
}
