<?php

namespace TofuPlugin\Validation\Rules;

/**
 * Required only when EVERY named sibling is empty.
 */
class RequiredWithoutAllRule extends ConditionalRule
{
    protected string $message = 'rule.required_without_all';

    protected function conditionApplies(): bool
    {
        foreach ($this->fields() as $field) {
            if ($this->siblingIsPresent($field)) {
                return false;
            }
        }

        return true;
    }
}
