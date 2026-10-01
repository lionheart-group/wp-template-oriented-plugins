<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Presence;

/**
 * The field must carry an answer.
 *
 * Implicit, so it runs even when the value is empty or the key is absent —
 * that is the whole point of it.
 *
 * Understands file fields, which plain emptiness cannot: a "no file chosen"
 * $_FILES entry is a non-empty array, and on the confirm step the key is
 * gone even though a file was uploaded earlier. Both are handled by
 * Support\Presence, shared with custom_required_file and the required_*
 * family so they cannot disagree.
 */
class RequiredRule extends Rule
{
    protected bool $implicit = true;
    protected string $message = 'rule.required';

    /**
     * When true, only an actual file satisfies this rule. See
     * RequiredFileRule.
     */
    protected bool $fileOnly = false;

    public function check(mixed $value): bool
    {
        return Presence::satisfied($this->attribute(), $value, $this->fileOnly);
    }
}
