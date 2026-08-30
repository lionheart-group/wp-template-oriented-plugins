<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

/**
 * Shared base for `lowercase` and `uppercase`: the value must already equal
 * its own case-transformed form.
 *
 * Note these stringify booleans, unlike the alpha* family which rejects
 * them — an inconsistency inherited from the engine this replaced and
 * pinned by the golden corpus.
 */
abstract class CaseRule extends Rule
{
    abstract protected function transform(string $value): string;

    public function check(mixed $value): bool
    {
        $string = Value::toStringOrNull($value);

        return $string !== null && $this->transform($string) === $string;
    }
}
