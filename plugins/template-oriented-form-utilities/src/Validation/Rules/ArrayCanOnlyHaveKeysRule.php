<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

class ArrayCanOnlyHaveKeysRule extends Rule
{
    protected string $message = 'rule.array_can_only_have_keys';

    public function fillParameters(array $params): self
    {
        $this->params['keys'] = $params;

        return $this;
    }

    public function check(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        /** @var array<int, string> $keys */
        $keys = $this->parameter('keys', []);

        foreach (array_keys($value) as $key) {
            if (!in_array((string) $key, array_map('strval', $keys), true)) {
                return false;
            }
        }

        return true;
    }
}
