<?php

namespace TofuPlugin\Validation;

/**
 * Raised for developer errors in a form's `rules` configuration — an
 * unregistered rule name, a missing or unusable rule parameter, an
 * unparseable rule definition.
 *
 * These are deliberately loud and thrown while the rules are being parsed,
 * before any validation runs. A typo like `requried|max:200` must never
 * degrade into a silently skipped check, which would weaken validation on a
 * live form without anyone noticing.
 *
 * Invalid *user input* is never reported through this class — that is an
 * ordinary validation failure.
 */
class ValidationConfigurationException extends \InvalidArgumentException
{
    /**
     * @param string[] $known
     */
    public static function unknownRule(string $name, array $known = []): self
    {
        $message = sprintf('Validation rule "%s" is not registered.', $name);

        if ($known !== []) {
            $suggestion = self::closestMatch($name, $known);
            if ($suggestion !== null) {
                $message .= sprintf(' Did you mean "%s"?', $suggestion);
            }
        }

        return new self($message);
    }

    public static function missingParameter(string $rule, string $parameter, ?string $field = null): self
    {
        return new self(sprintf(
            'Missing required parameter "%s" on rule "%s"%s.',
            $parameter,
            $rule,
            $field === null ? '' : sprintf(' for field "%s"', $field)
        ));
    }

    public static function invalidParameter(string $rule, string $detail, ?string $field = null): self
    {
        return new self(sprintf(
            'Invalid parameter for rule "%s"%s: %s',
            $rule,
            $field === null ? '' : sprintf(' on field "%s"', $field),
            $detail
        ));
    }

    public static function invalidDefinition(string $field, string $detail): self
    {
        return new self(sprintf('Invalid rule definition for field "%s": %s', $field, $detail));
    }

    /**
     * @param string[] $known
     */
    private static function closestMatch(string $name, array $known): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($known as $candidate) {
            $distance = levenshtein($name, $candidate);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }

        // Only suggest something genuinely close, otherwise the hint is noise.
        return $bestDistance <= 3 ? $best : null;
    }
}
