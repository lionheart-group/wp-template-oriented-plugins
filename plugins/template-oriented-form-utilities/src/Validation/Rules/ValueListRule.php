<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

/**
 * Shared base for `accepted` and `rejected`: the value must be one of a
 * fixed list.
 *
 * Both are implicit, because an unchecked checkbox submits nothing at all —
 * a non-implicit version would be skipped exactly when it matters and let a
 * mandatory consent box through.
 */
abstract class ValueListRule extends Rule
{
    protected bool $implicit = true;

    /**
     * The accepted values, surfaced in the message under the parameter name
     * returned by parameterName().
     *
     * @return list<string|int|bool>
     */
    abstract protected function allowed(): array;

    abstract protected function parameterName(): string;

    public function __construct()
    {
        $this->params[$this->parameterName()] = $this->allowed();
    }

    public function check(mixed $value): bool
    {
        return in_array($value, $this->allowed(), true);
    }
}
