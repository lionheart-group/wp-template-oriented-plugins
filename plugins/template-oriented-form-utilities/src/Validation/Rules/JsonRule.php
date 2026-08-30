<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

/**
 * A JSON string that decodes to something meaningful.
 *
 * Two quirks preserved from the engine this replaced: the value must
 * already be a string (the integer 100 fails even though "100" passes), and
 * JSON that decodes to a falsy scalar — "0", "false", '""' — is rejected
 * rather than accepted as valid-but-empty.
 */
class JsonRule extends Rule
{
    protected string $message = 'rule.json';

    public function check(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $decoded = json_decode($value);

        return json_last_error() === JSON_ERROR_NONE && !empty($decoded);
    }
}
