<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

/**
 * The named sibling fields must all have values.
 *
 * Note this says nothing about THIS field's own value — the check fires
 * regardless of whether the field is filled in. It reads as "this field
 * depends on those ones being answered".
 */
class RequiresRule extends Rule
{
    protected bool $implicit = true;
    protected string $message = 'rule.requires';

    public function fillParameters(array $params): self
    {
        $this->params['fields'] = array_map('strval', $params);

        return $this;
    }

    public function check(mixed $value): bool
    {
        /** @var array<int, string> $fields */
        $fields = $this->parameter('fields', []);

        foreach ($fields as $field) {
            if (Value::isEmpty($this->attribute()?->value((string) $field))) {
                return false;
            }
        }

        return true;
    }
}
