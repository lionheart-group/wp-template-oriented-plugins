<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

/**
 * Shared base for `max`, `min` and `between`: resolve the value's size, then
 * check it against whichever bounds the subclass declares.
 *
 * What "size" means is decided by Support\Value::size() — a string is
 * measured by character count unless the field also carries numeric or
 * integer, which is why `'phone' => 'max:20'` accepts "0312345678". All
 * three rules must agree on that, hence the shared base.
 */
abstract class SizeComparisonRule extends Rule
{
    /**
     * Parameter names holding the lower and upper bound. A null entry means
     * this rule does not constrain that end.
     */
    protected ?string $lowerBoundParam = null;
    protected ?string $upperBoundParam = null;

    public function check(mixed $value): bool
    {
        $this->assertHasRequiredParameters($this->fillableParams);

        $size = Value::size($value, $this->attribute());
        if ($size === null) {
            return false;
        }

        if ($this->lowerBoundParam !== null) {
            $lower = Value::bytes($this->parameter($this->lowerBoundParam));
            if ($lower === null || $size < $lower) {
                return false;
            }
        }

        if ($this->upperBoundParam !== null) {
            $upper = Value::bytes($this->parameter($this->upperBoundParam));
            if ($upper === null || $size > $upper) {
                return false;
            }
        }

        return true;
    }
}
