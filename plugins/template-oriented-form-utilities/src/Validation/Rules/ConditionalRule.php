<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

/**
 * Shared machinery for the `required_*` and `prohibited_*` families.
 *
 * Each subclass answers one question — "given the sibling fields, does this
 * condition fire?" — and the base turns that into a pass/fail using the
 * subclass's polarity: the required_* rules demand a value when the
 * condition fires, the prohibited_* rules demand its absence.
 *
 * Keeping the two families on one base is deliberate. They are mirror
 * images, and splitting them is how "required_if understands file uploads
 * but prohibited_if doesn't" bugs get in.
 */
abstract class ConditionalRule extends Rule
{
    protected bool $implicit = true;

    /**
     * When true the field must have a value once the condition fires;
     * when false it must NOT have one.
     */
    protected bool $demandsValue = true;

    /**
     * Sibling field names this rule inspects.
     *
     * @var string[]
     */
    protected array $fields = [];

    abstract protected function conditionApplies(): bool;

    public function fillParameters(array $params): self
    {
        $this->fields = array_map('strval', $params);
        $this->params['fields'] = $this->fields;

        return $this;
    }

    public function check(mixed $value): bool
    {
        if (!$this->conditionApplies()) {
            return true;
        }

        return $this->demandsValue
            ? !Value::isEmpty($value)
            : Value::isEmpty($value);
    }

    protected function siblingValue(string $field): mixed
    {
        return $this->attribute()?->value($field);
    }

    protected function siblingIsPresent(string $field): bool
    {
        return !Value::isEmpty($this->siblingValue($field));
    }

    /**
     * @return string[]
     */
    protected function fields(): array
    {
        /** @var array<int, string> $fields */
        $fields = $this->parameter('fields', []);

        return array_map('strval', $fields);
    }
}
