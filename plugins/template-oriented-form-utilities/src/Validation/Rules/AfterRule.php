<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;
use TofuPlugin\Validation\ValidationConfigurationException;

/**
 * The value must be a date later than the given one.
 *
 * Two different kinds of bad input are handled differently on purpose: a
 * malformed RULE PARAMETER is the form author's error and throws, while a
 * malformed USER VALUE is an ordinary validation failure. The engine this
 * replaced raised an exception for both, which meant a visitor could take
 * the page down simply by leaving the date field blank.
 */
class AfterRule extends Rule
{
    protected string $message = 'rule.after';
    protected array $fillableParams = ['time'];

    /**
     * Comparison direction: true for `after`, false for `before`.
     */
    protected bool $mustBeLater = true;

    public function check(mixed $value): bool
    {
        $this->assertHasRequiredParameters($this->fillableParams);

        $boundary = DateRule::timestamp((string) $this->parameter('time'));
        if ($boundary === null) {
            throw ValidationConfigurationException::invalidParameter(
                $this->name(),
                sprintf('"%s" is not a valid date', (string) $this->parameter('time')),
                $this->attribute()?->key()
            );
        }

        $string = Value::toStringOrNull($value);
        if ($string === null) {
            return false;
        }

        $timestamp = DateRule::timestamp($string);
        if ($timestamp === null) {
            return false;
        }

        return $this->mustBeLater ? $timestamp > $boundary : $timestamp < $boundary;
    }
}
