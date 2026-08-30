<?php

namespace TofuPlugin\Validation;

/**
 * Turns a failed rule into the string the visitor reads.
 *
 * Lookup order, first hit wins:
 *   1. `"{field}:{rule}"` — the per-field override from
 *      `ValidationConfig::$messages`, keyed on the rule name the form author
 *      actually wrote.
 *   2. The rule's own message key (`rule.required`, …) in the active locale.
 *   3. The key itself, rendered literally.
 *
 * Step 3 is not a nicety — it is the existing behaviour. A rule whose key is
 * absent from the catalogue shows up on the page as `rule.prohibited_with`,
 * which is exactly how the gap in ja.php became visible. There is
 * deliberately no `rule.default` fallback in between: silently substituting a
 * generic message would hide the missing translation instead of surfacing it.
 */
class MessageResolver
{
    public function __construct(
        protected Translator $translator,
    ) {
    }

    public function resolve(Attribute $attribute, Rule $rule): string
    {
        $template = $this->template($attribute, $rule);

        return $this->interpolate($template, $attribute, $rule);
    }

    protected function template(Attribute $attribute, Rule $rule): string
    {
        $override = $this->translator->get($attribute->key() . ':' . $rule->name());
        if ($override !== null) {
            return $override;
        }

        return $this->translator->get($rule->message()) ?? $rule->message();
    }

    protected function interpolate(string $template, Attribute $attribute, Rule $rule): string
    {
        $replacements = [':attribute' => $attribute->displayName()];

        foreach ($rule->parameters() as $name => $value) {
            if (!is_string($name)) {
                continue;
            }
            $replacements[':' . $name] = $this->stringify($value);
        }

        // strtr()'s array form replaces the longest key first, so `:max_mb`
        // survives even though `:max` is also a candidate. A str_replace()
        // loop would rewrite ":max_mb" into "5_mb" depending on map order —
        // MessageResolutionTest pins this.
        return strtr($template, $replacements);
    }

    /**
     * Render a parameter for display. Lists become `"a", "b", "c"` — each
     * element quoted, comma-separated, with no conjunction.
     */
    protected function stringify(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(
                fn ($item): string => '"' . $this->scalarToString($item) . '"',
                $value
            ));
        }

        return $this->scalarToString($value);
    }

    /**
     * A plain string cast, deliberately. `accepted` lists both `1` and
     * `true` among its allowed values and renders them identically as "1";
     * formatting booleans as "true"/"false" instead would change that
     * message. Non-stringable values render as empty rather than raising.
     */
    protected function scalarToString(mixed $value): string
    {
        if ($value === null || is_array($value)) {
            return '';
        }

        if (is_object($value) && !$value instanceof \Stringable) {
            return '';
        }

        return (string) $value;
    }
}
