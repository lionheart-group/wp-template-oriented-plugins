<?php

namespace TofuPlugin\Validation\Rules;

/**
 * Required only when EVERY named sibling has a value.
 */
class RequiredWithAllRule extends ConditionalRule
{
    protected string $message = 'rule.required_with_all';

    protected function conditionApplies(): bool
    {
        foreach ($this->fields() as $field) {
            if (!$this->siblingIsPresent($field)) {
                return false;
            }
        }

        return true;
    }
}
