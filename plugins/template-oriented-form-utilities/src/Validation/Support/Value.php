<?php

namespace TofuPlugin\Validation\Support;

use TofuPlugin\Validation\Attribute;

/**
 * Value inspection shared across rules.
 *
 * The two functions here decide behaviour that every form on every site
 * depends on, so both are pinned by the golden corpus
 * (tests/Unit/Validation/Fixtures/) and by dedicated unit tests. Change
 * either one and expect a lot of red.
 */
final class Value
{
    /**
     * Whether a value counts as "absent" for validation purposes.
     *
     * This is the single most load-bearing predicate in the engine: a rule
     * that is not implicit is SKIPPED entirely when the value is empty,
     * which is the only reason an optional field like
     * `'phone' => 'max:20'` passes when left blank.
     *
     * Note `'0'`, `0`, `0.0`, `false` and `[0]` are NOT empty — a literal
     * zero is a legitimate answer and must satisfy `required`.
     *
     * Whitespace is judged in Unicode terms, not bytes. PHP's trim() strips
     * ASCII whitespace only, so a value consisting solely of an ideographic
     * space (U+3000) used to count as real input and satisfy `required` —
     * and U+3000 is what a Japanese IME produces when the space bar is
     * pressed in full-width mode, so it lands in forms by accident
     * constantly. A full-width space now behaves exactly like an ASCII one.
     */
    public static function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return self::isBlank($value);
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }

    /**
     * Whether a string holds nothing but whitespace, in any script.
     *
     * `\p{Z}` covers the Unicode separators that `\s` misses, U+3000 among
     * them.
     */
    private static function isBlank(string $value): bool
    {
        $result = preg_match('/^[\s\p{Z}]*+$/u', $value);

        // preg_match returns false on malformed UTF-8, where the Unicode
        // question is unanswerable; fall back to the byte-wise test rather
        // than calling such a value empty.
        if ($result === false) {
            return trim($value) === '';
        }

        return $result === 1;
    }

    /**
     * The magnitude `max` / `min` / `between` compare against.
     *
     * Deliberately NOT "numeric-looking strings compare numerically". A
     * string is measured by character count unless the field also carries a
     * `numeric` or `integer` rule. That distinction is why
     * `'phone' => 'max:20'` accepts `'0312345678'` — comparing numerically
     * instead would silently start rejecting phone numbers, postal codes
     * and member IDs on sites that changed nothing.
     *
     * @return int|float|null Null when the value has no meaningful size,
     *                        which the calling rule treats as a failure.
     */
    public static function size(mixed $value, ?Attribute $attribute = null): int|float|null
    {
        if ($attribute !== null && ($attribute->hasRule('numeric') || $attribute->hasRule('integer'))) {
            return is_numeric($value) ? (float) $value : null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_array($value)) {
            // An uploaded file is measured by its byte size; any other array
            // by its element count.
            if (array_key_exists('tmp_name', $value) && array_key_exists('size', $value)) {
                return is_numeric($value['size']) ? (float) $value['size'] : null;
            }

            return count($value);
        }

        if (is_string($value)) {
            return mb_strlen($value, 'UTF-8');
        }

        return null;
    }

    /**
     * Whether a value has the shape PHP gives a single `$_FILES` entry.
     *
     * Used by the file-aware rules to tell an upload apart from ordinary
     * input. Multi-file inputs (`name="files[]"`), where PHP nests an array
     * under every key, are deliberately not recognised — TOFU does not
     * support multiple files per field.
     */
    public static function isUploadedFileShape(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) {
            if (!array_key_exists($key, $value)) {
                return false;
            }
        }

        if (is_array($value['name']) || is_array($value['tmp_name'])) {
            return false;
        }

        return is_int($value['error'])
            && $value['error'] >= UPLOAD_ERR_OK
            && $value['error'] <= UPLOAD_ERR_EXTENSION;
    }

    /**
     * Parse a size parameter that may carry a unit suffix (`5M`, `512K`).
     *
     * @return int|float|null Null when the parameter is not a valid size.
     */
    public static function bytes(mixed $parameter): int|float|null
    {
        if (is_int($parameter) || is_float($parameter)) {
            return $parameter;
        }

        if (!is_string($parameter)) {
            return null;
        }

        $parameter = trim($parameter);

        if (!preg_match('/^(\d+(?:\.\d+)?)\s*([BKMGT]?)B?$/i', $parameter, $matches)) {
            return null;
        }

        $size = (float) $matches[1];

        return match (strtoupper($matches[2])) {
            'K' => $size * 1024,
            'M' => $size * 1024 ** 2,
            'G' => $size * 1024 ** 3,
            'T' => $size * 1024 ** 4,
            default => $size,
        };
    }

    /**
     * Coerce a value to a string for the string-oriented rules, or null when
     * it has no sensible string form.
     *
     * Rules use this instead of a bare `(string)` cast so that an array or
     * object value produces a clean validation failure rather than a PHP
     * "Array to string conversion" warning.
     */
    public static function toStringOrNull(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        // Booleans stringify the PHP way — true becomes "1", false becomes
        // "" — which is what the string rules have always compared against.
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return null;
    }
}
