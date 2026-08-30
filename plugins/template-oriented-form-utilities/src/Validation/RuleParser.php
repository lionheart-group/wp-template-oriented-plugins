<?php

namespace TofuPlugin\Validation;

/**
 * Turns a field's rule definition into `Rule` instances.
 *
 * Two accepted forms:
 *
 *   'name' => 'required|max:200'                   // pipe-delimited string
 *   'name' => ['required', 'max' => 200]           // array
 *
 * The string form is the historical one and its grammar is unchanged. The
 * array form exists mainly because a `regex` pattern containing `|` cannot
 * be expressed in the string form at all — the pipe split eats it.
 */
class RuleParser
{
    public function __construct(
        protected RuleRegistry $registry,
    ) {
    }

    /**
     * @return Rule[]
     */
    public function parse(string $field, mixed $definition): array
    {
        if ($definition instanceof Rule) {
            return [$definition];
        }

        if (is_string($definition)) {
            return $this->parseString($field, $definition);
        }

        if (is_array($definition)) {
            return $this->parseArray($field, $definition);
        }

        throw ValidationConfigurationException::invalidDefinition(
            $field,
            'expected a rule string, an array of rules, or a Rule instance, got ' . get_debug_type($definition)
        );
    }

    /**
     * @return Rule[]
     */
    protected function parseString(string $field, string $definition): array
    {
        $rules = [];

        foreach (explode('|', $definition) as $segment) {
            // A trailing pipe ('required|') is tolerated, as it always was.
            if (trim($segment) === '') {
                continue;
            }

            $rules[] = $this->parseSegment($field, $segment);
        }

        return $rules;
    }

    /**
     * One pipe segment: `name` or `name:param1,param2`.
     */
    protected function parseSegment(string $field, string $segment): Rule
    {
        // Whitespace around a segment is tolerated ('required | max:5'). The
        // previous engine threw here; accepting it can only turn a
        // previously-fatal config into a working one.
        $segment = trim($segment);

        $name = $segment;
        $params = [];

        $colon = strpos($segment, ':');
        if ($colon !== false) {
            $name = substr($segment, 0, $colon);
            // Split on the FIRST colon only, so `regex:/^\d{2}:\d{2}$/` and
            // `after:2026-01-01 10:00` keep their remaining colons.
            $params = $this->splitParameters(substr($segment, $colon + 1));
        }

        return $this->build($field, trim($name), $params);
    }

    /**
     * Comma-separated parameters, honouring double quotes so a parameter can
     * itself contain a comma (`in:"a,b",c`).
     *
     * Parameters are NOT trimmed: `in:a, b` yields `' b'`, matching the
     * historical behaviour that some configs may rely on.
     *
     * @return string[]
     */
    protected function splitParameters(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $params = [];
        $current = '';
        $inQuotes = false;
        $length = strlen($raw);

        for ($i = 0; $i < $length; $i++) {
            $char = $raw[$i];

            if ($char === '"') {
                $inQuotes = !$inQuotes;
                continue;
            }

            if ($char === ',' && !$inQuotes) {
                $params[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $params[] = $current;

        return $params;
    }

    /**
     * @param array<int|string, mixed> $definition
     * @return Rule[]
     */
    protected function parseArray(string $field, array $definition): array
    {
        $rules = [];

        foreach ($definition as $key => $value) {
            if (is_int($key)) {
                if ($value instanceof Rule) {
                    $rules[] = $value;
                    continue;
                }

                if (!is_string($value)) {
                    throw ValidationConfigurationException::invalidDefinition(
                        $field,
                        'a positional entry must be a rule string or a Rule instance, got ' . get_debug_type($value)
                    );
                }

                $rules[] = $this->parseSegment($field, $value);
                continue;
            }

            $rules[] = $this->buildFromKeyed($field, $key, $value);
        }

        return $rules;
    }

    protected function buildFromKeyed(string $field, string $name, mixed $value): Rule
    {
        // ['required' => null] / ['required' => true] — no parameters.
        if ($value === null || $value === true) {
            return $this->build($field, $name, []);
        }

        if (is_array($value)) {
            // An associative array assigns parameters by name.
            if ($value !== [] && !array_is_list($value)) {
                $rule = $this->registry->resolve($name);
                /** @var array<string, mixed> $value */
                $rule->setParameters($value);

                return $rule;
            }

            return $this->build($field, $name, array_map(
                static fn ($item): string => is_scalar($item) ? (string) $item : '',
                $value
            ));
        }

        if (is_scalar($value)) {
            return $this->build($field, $name, [(string) $value]);
        }

        throw ValidationConfigurationException::invalidDefinition(
            $field,
            sprintf('unsupported parameter type for rule "%s": %s', $name, get_debug_type($value))
        );
    }

    /**
     * @param string[] $params
     */
    protected function build(string $field, string $name, array $params): Rule
    {
        $rule = $this->registry->resolve($name);
        $rule->fillParameters($params);

        return $rule;
    }
}
