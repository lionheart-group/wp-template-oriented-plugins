<?php

namespace TobiuoPlugin\Helpers;

/**
 * Key and type checks shared by the fromArray() of every config.
 *
 * A definition file is plain PHP arrays, so a typo or a wrong type would
 * otherwise only show up as a post type that behaves differently from what
 * was written.
 */
class Fields
{
    /**
     * How each type is described in error messages.
     *
     * @var array<string, string>
     */
    private const DESCRIPTIONS = [
        'string'   => 'a string',
        '?string'  => 'a string or null',
        'bool'     => 'a bool',
        'array'    => 'an array',
        'string[]' => 'an array of strings',
    ];

    /**
     * Throw on unknown keys and on values of the wrong type.
     *
     * Types are checked, never coerced: `'dateArchive' => 'yes'` is rejected.
     * Keys that are allowed but must hold an object as well as an array
     * (`permalink`) are checked by the caller and listed here as 'mixed'.
     *
     * @param array<array-key, mixed> $values
     * @param array<string, string> $fields Key => 'string' | '?string' | 'bool' | 'array' | 'string[]' | 'mixed'.
     * @param string $prefix Start of the error message, e.g. "PostTypeConfig 'case'".
     * @return void
     */
    public static function assert(array $values, array $fields, string $prefix): void
    {
        $unknown = array_diff(array_map('strval', array_keys($values)), array_keys($fields));
        if ($unknown !== []) {
            throw new \InvalidArgumentException(sprintf(
                '%s: unknown key(s) "%s". Allowed keys: %s.',
                esc_html($prefix),
                esc_html(implode('", "', $unknown)),
                esc_html(implode(', ', array_keys($fields)))
            ));
        }

        foreach ($values as $key => $value) {
            $expected = $fields[$key];
            $valid = match ($expected) {
                'string'   => is_string($value),
                '?string'  => $value === null || is_string($value),
                'bool'     => is_bool($value),
                'array'    => is_array($value),
                'string[]' => is_array($value) && array_filter($value, fn ($item) => !is_string($item)) === [],
                default    => true,
            };

            if (!$valid) {
                throw new \InvalidArgumentException(sprintf(
                    '%s: "%s" must be %s, got %s.',
                    esc_html($prefix),
                    esc_html((string) $key),
                    esc_html(self::DESCRIPTIONS[$expected] ?? $expected),
                    esc_html(get_debug_type($value))
                ));
            }
        }
    }

    /**
     * Throw when a required key is missing.
     *
     * @param array<array-key, mixed> $values
     * @param string[] $required
     * @param string $prefix
     * @return void
     */
    public static function assertRequired(array $values, array $required, string $prefix): void
    {
        foreach ($required as $key) {
            if (!array_key_exists($key, $values)) {
                throw new \InvalidArgumentException(sprintf('%s: "%s" is required.', esc_html($prefix), esc_html($key)));
            }
        }
    }
}
