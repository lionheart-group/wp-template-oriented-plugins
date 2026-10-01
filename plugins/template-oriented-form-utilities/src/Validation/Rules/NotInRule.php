<?php

namespace TofuPlugin\Validation\Rules;

class NotInRule extends InRule
{
    protected string $message = 'rule.not_in';

    public function fillParameters(array $params): self
    {
        $this->params['disallowed_values'] = $params;

        return $this;
    }

    public function check(mixed $value): bool
    {
        /** @var array<int, mixed> $disallowed */
        $disallowed = $this->parameter('disallowed_values', []);

        return !self::contains($disallowed, $value);
    }
}
