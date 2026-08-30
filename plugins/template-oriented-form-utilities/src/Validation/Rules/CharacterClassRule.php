<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;

/**
 * Shared base for the alpha* character-class rules.
 *
 * These accept only strings and numbers — a boolean is rejected outright
 * rather than stringified, even though `true` would render as the perfectly
 * alphanumeric "1". The `lowercase` / `uppercase` rules deliberately differ
 * here and do stringify booleans; both behaviours are inherited from the
 * engine this replaced and pinned by the golden corpus.
 */
abstract class CharacterClassRule extends Rule
{
    /**
     * PCRE matching the whole value, e.g. '/^\pL++$/uD'.
     */
    protected string $pattern = '';

    public function check(mixed $value): bool
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        return preg_match($this->pattern, (string) $value) === 1;
    }
}
