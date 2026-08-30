<?php

namespace TofuPlugin\Validation;

/**
 * Maps rule names to rule instances.
 *
 * Registered entries are prototypes: `resolve()` hands back a CLONE, never
 * the stored object. That is not an optimisation detail — a Rule carries
 * per-field state (`$params`, `$attribute`), so sharing one instance across
 * fields would leak MimeTypeRule's allowed types from one field into the
 * next and give RequiredFileRule the wrong attribute.
 *
 * One class may back several names (`regex` and `matches`, `integer` and
 * `number`). The invoked name is stamped onto the clone because per-field
 * custom messages are keyed on the name the form author wrote.
 */
class RuleRegistry
{
    /** @var array<string, Rule> */
    protected array $prototypes = [];

    public function register(string $name, Rule $rule): void
    {
        $this->prototypes[$name] = $rule;
    }

    /**
     * @param array<string, Rule> $rules
     */
    public function registerMany(array $rules): void
    {
        foreach ($rules as $name => $rule) {
            $this->register($name, $rule);
        }
    }

    public function has(string $name): bool
    {
        return isset($this->prototypes[$name]);
    }

    /**
     * @return string[]
     */
    public function names(): array
    {
        return array_keys($this->prototypes);
    }

    /**
     * @throws ValidationConfigurationException when the name is not registered.
     */
    public function resolve(string $name): Rule
    {
        if (!isset($this->prototypes[$name])) {
            throw ValidationConfigurationException::unknownRule($name, $this->names());
        }

        $rule = clone $this->prototypes[$name];
        $rule->setName($name);

        return $rule;
    }
}
