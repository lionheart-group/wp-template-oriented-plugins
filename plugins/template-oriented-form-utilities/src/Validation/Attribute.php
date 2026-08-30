<?php

namespace TofuPlugin\Validation;

/**
 * One field being validated: its key, its human-readable alias, the rules
 * attached to it, and controlled access back to the surrounding input.
 *
 * Rules reach the wider request only through this object. That is
 * deliberate — it keeps `$this->attribute()->value('other_field')` as the
 * single, greppable way a rule can read a sibling, rather than every rule
 * holding the whole input array.
 */
class Attribute
{
    /** @var Rule[] */
    protected array $rules = [];

    protected ?string $alias = null;

    public function __construct(
        protected Validator $validator,
        protected string $key,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function alias(): ?string
    {
        return $this->alias;
    }

    public function setAlias(string $alias): void
    {
        $this->alias = $alias;
    }

    /**
     * The name to render for `:attribute` — the alias when one was supplied
     * via `ValidationConfig::$names`, otherwise the raw field key verbatim.
     *
     * No humanisation happens here (no underscore-to-space, no ucfirst):
     * a field with no alias shows as `first_name`, which is what every
     * existing form already displays.
     */
    public function displayName(): string
    {
        return $this->alias ?? $this->key;
    }

    public function addRule(Rule $rule): void
    {
        $this->rules[] = $rule;
    }

    /**
     * @return Rule[]
     */
    public function rules(): array
    {
        return $this->rules;
    }

    public function hasRule(string $name): bool
    {
        foreach ($this->rules as $rule) {
            if ($rule->name() === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * This attribute's own value, or a sibling field's when a key is given.
     *
     * The sibling form is what makes cross-field rules (`same`, `required_if`,
     * and the plugin's `custom_required_file`, which reads the
     * `__tofu_uploaded_files` map) possible.
     */
    public function value(?string $key = null): mixed
    {
        return $this->validator->input($key ?? $this->key);
    }

    /**
     * Whether the field's key is present in the input at all — distinct from
     * the value being empty, which is what `present` keys off.
     */
    public function exists(?string $key = null): bool
    {
        return $this->validator->hasInput($key ?? $this->key);
    }

    public function validator(): Validator
    {
        return $this->validator;
    }
}
