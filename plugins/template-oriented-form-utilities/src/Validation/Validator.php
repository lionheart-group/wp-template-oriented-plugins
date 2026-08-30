<?php

namespace TofuPlugin\Validation;

use TofuPlugin\Validation\Support\Value;

/**
 * Runs a set of rules over a set of input values.
 *
 * The run loop is where the engine's most consequential behaviour lives: a
 * rule that is not implicit is skipped entirely when the value is empty.
 * That single line is why `'phone' => 'max:20'` accepts a blank field, and
 * inverting it would make every optional field on every form mandatory.
 */
class Validator
{
    /** @var array<string, mixed> */
    protected array $data;

    /** @var array<string, Attribute> */
    protected array $attributes = [];

    protected ErrorBag $errors;

    protected bool $validated = false;

    /**
     * Field names with a genuine, server-side upload on record.
     *
     * @var array<string, true>
     */
    protected array $verifiedUploads = [];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        array $data,
        protected RuleParser $parser,
        protected MessageResolver $messages,
    ) {
        $this->data = $data;
        $this->errors = new ErrorBag();
    }

    /**
     * @param array<string, mixed> $rules Field => rule definition.
     */
    public function setRules(array $rules): self
    {
        foreach ($rules as $field => $definition) {
            $attribute = new Attribute($this, (string) $field);

            foreach ($this->parser->parse((string) $field, $definition) as $rule) {
                $rule->setAttribute($attribute);
                $attribute->addRule($rule);
            }

            $this->attributes[(string) $field] = $attribute;
        }

        return $this;
    }

    /**
     * @param array<string, string> $aliases Field => human-readable name.
     */
    public function setAliases(array $aliases): self
    {
        foreach ($aliases as $field => $alias) {
            if (isset($this->attributes[$field]) && is_string($alias)) {
                $this->attributes[$field]->setAlias($alias);
            }
        }

        return $this;
    }

    /**
     * Declare which fields have an upload the server itself can vouch for.
     *
     * Rules consult this instead of the client-supplied
     * `__tofu_uploaded_files` map, so a forged hidden input cannot make a
     * field look satisfied.
     *
     * @param string[] $fields
     */
    public function setVerifiedUploads(array $fields): self
    {
        $this->verifiedUploads = [];
        foreach ($fields as $field) {
            $this->verifiedUploads[$field] = true;
        }

        return $this;
    }

    public function hasVerifiedUpload(string $field): bool
    {
        return isset($this->verifiedUploads[$field]);
    }

    public function input(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    public function hasInput(string $field): bool
    {
        return array_key_exists($field, $this->data);
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    public function validate(): self
    {
        $this->errors = new ErrorBag();

        foreach ($this->attributes as $field => $attribute) {
            $this->validateAttribute($field, $attribute);
        }

        $this->validated = true;

        return $this;
    }

    protected function validateAttribute(string $field, Attribute $attribute): void
    {
        $value = $this->input($field);
        $isEmpty = Value::isEmpty($value);

        foreach ($attribute->rules() as $rule) {
            // `sometimes` exempts an entirely absent key; `nullable` exempts
            // an empty value outright. Both short-circuit the whole
            // attribute rather than acting as rules in their own right.
            if ($rule instanceof Rules\SometimesRule && !$this->hasInput($field)) {
                return;
            }

            if ($rule instanceof Rules\NullableRule && $isEmpty) {
                return;
            }
        }

        foreach ($attribute->rules() as $rule) {
            if ($isEmpty && !$rule->isImplicit()) {
                continue;
            }

            if ($rule->check($value)) {
                continue;
            }

            $this->errors->add($field, $rule->name(), $this->messages->resolve($attribute, $rule));

            // Only the first failure per field is ever surfaced, so there is
            // nothing to gain from evaluating the rest — and stopping avoids
            // running an expensive check (mime_type's finfo sniff) for a
            // field that has already failed.
            return;
        }
    }

    public function fails(): bool
    {
        return !$this->errors->isEmpty();
    }

    public function passes(): bool
    {
        return $this->errors->isEmpty();
    }

    public function errors(): ErrorBag
    {
        return $this->errors;
    }

    /**
     * @return array<string, Attribute>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }
}
