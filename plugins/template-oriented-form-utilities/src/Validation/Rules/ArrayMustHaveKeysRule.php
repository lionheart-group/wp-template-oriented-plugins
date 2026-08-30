<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

class ArrayMustHaveKeysRule extends Rule
{
    protected string $message = 'rule.array_must_have_keys';

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

        foreach ($keys as $key) {
            if (!array_key_exists($key, $value)) {
                return false;
            }
        }

        return true;
    }
}
