<?php

namespace TofuPlugin\Validation\Rules;

use TofuPlugin\Validation\Rule;
use TofuPlugin\Validation\Support\Value;
use TofuPlugin\Validation\ValidationConfigurationException;

/**
 * Backs both `regex` and its alias `matches`.
 *
 * A pattern containing `|` cannot be written in the pipe-delimited string
 * form — the split eats it. Use the array form for those:
 * `'code' => ['regex' => '/^(a|b)$/']`.
 */
class RegexRule extends Rule
{
    protected string $message = 'rule.regex';
    protected array $fillableParams = ['regex'];

    public function check(mixed $value): bool
    {
        $this->assertHasRequiredParameters($this->fillableParams);

        $string = Value::toStringOrNull($value);
        if ($string === null) {
            return false;
        }

        $pattern = (string) $this->parameter('regex');
        $result = @preg_match($pattern, $string);

        if ($result === false) {
            // A broken pattern is the form author's mistake, not the
            // visitor's — fail loudly rather than silently rejecting input.
            throw ValidationConfigurationException::invalidParameter(
                $this->name(),
                sprintf('"%s" is not a valid regular expression', $pattern),
                $this->attribute()?->key()
            );
        }

        return $result === 1;
    }
}
