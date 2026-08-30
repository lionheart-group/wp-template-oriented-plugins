<?php

namespace TofuPlugin\Validation\Rules;

/**
 * Base for the `*_if` / `*_unless` rules, whose first parameter names a
 * sibling field and whose remaining parameters are the values to compare
 * it against.
 */
abstract class ConditionalOnValueRule extends ConditionalRule
{
    /**
     * When true the condition fires if the sibling matches one of the
     * values; when false it fires if the sibling matches none of them.
     */
    protected bool $firesOnMatch = true;

    public function fillParameters(array $params): self
    {
        $this->params['field'] = array_shift($params) ?? '';
        $this->params['values'] = array_values($params);

        return $this;
    }

    protected function conditionApplies(): bool
    {
        $value = $this->siblingValue((string) $this->parameter('field', ''));

        /** @var array<int, mixed> $values */
        $values = $this->parameter('values', []);

        $matches = false;
        foreach ($values as $candidate) {
            if (is_scalar($value) || $value === null) {
                if ((string) $candidate === (string) $value) {
                    $matches = true;
                    break;
                }
            }
        }

        return $this->firesOnMatch ? $matches : !$matches;
    }
}
