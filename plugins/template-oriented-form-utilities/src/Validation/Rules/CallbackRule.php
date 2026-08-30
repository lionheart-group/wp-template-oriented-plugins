<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\ValidationConfigurationException;

/**
 * Defers to a caller-supplied closure.
 *
 * Only expressible through the array rule form, since a closure cannot be
 * written in a pipe-delimited string:
 *
 *     'code' => ['callback' => fn ($value) => $value === 'expected'],
 */
class CallbackRule extends Rule
{
    /**
     * Implicit so that a missing callback surfaces even when the field is
     * empty. A misconfigured rule is a developer error and should never
     * depend on what the visitor happened to type.
     */
    protected bool $implicit = true;
    protected string $message = 'rule.default';
    protected array $fillableParams = ['callback'];

    public function check(mixed $value): bool
    {
        $callback = $this->parameter('callback');

        if (!is_callable($callback)) {
            throw ValidationConfigurationException::missingParameter(
                $this->name(),
                'callback',
                $this->attribute()?->key()
            );
        }

        return (bool) $callback($value);
    }
}
