<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;

/**
 * A parseable date, optionally in an exact format.
 *
 * Input that cannot be parsed is a validation FAILURE, never an exception —
 * a visitor typing nonsense into a date field must see an error message,
 * not a 500.
 */
class DateRule extends Rule
{
    protected string $message = 'rule.date';
    protected array $fillableParams = ['format'];

    public function check(mixed $value): bool
    {
        $string = Value::toStringOrNull($value);
        if ($string === null) {
            return false;
        }

        $format = $this->parameter('format');

        if (is_string($format) && $format !== '') {
            $parsed = \DateTimeImmutable::createFromFormat($format, $string);

            return $parsed !== false && $parsed->format($format) === $string;
        }

        return self::timestamp($string) !== null;
    }

    /**
     * Parse to a timestamp, or null when the string is not a date.
     */
    public static function timestamp(string $value): ?int
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->getTimestamp();
        } catch (\Exception) {
            return null;
        }
    }
}
