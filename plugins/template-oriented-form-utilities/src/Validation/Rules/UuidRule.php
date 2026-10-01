<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

/**
 * A non-NIL UUID.
 *
 * Implicit, matching the engine this replaced — which means it also runs on
 * an empty value and rejects it. That looks like an upstream oversight, but
 * it is reproduced rather than quietly corrected.
 */
class UuidRule extends Rule
{
    protected bool $implicit = true;
    protected string $message = 'rule.uuid';

    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD';
    private const NIL = '00000000-0000-0000-0000-000000000000';

    public function check(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return $value !== self::NIL && preg_match(self::PATTERN, $value) === 1;
    }
}
