<?php

namespace TofuPlugin\Validation;

/**
 * Base class for every validation rule.
 *
 * The protected surface here is a compatibility contract, not just an
 * implementation detail: `src/Rules/` (the plugin's own file rules) and any
 * rule a theme registers extend this class. Three constraints in particular
 * must survive future edits:
 *
 * 1. `fillParameters()` returns `self`, NOT `static`. MimeTypeRule overrides
 *    it as `: self`, and PHP forbids narrowing `static` to `self` — getting
 *    this wrong is an autoload-time fatal, not a test failure.
 * 2. `attribute()` stays nullable. RequiredFileRule calls
 *    `$this->attribute()?->key()` and then `$this->attribute()->value(...)`;
 *    the nullable return is what keeps that PHPStan-clean.
 * 3. `$params` stays protected, never private. MimeTypeRule writes
 *    `$this->params['types']` directly.
 */
abstract class Rule
{
    /**
     * The name this rule was registered and invoked under.
     *
     * Stamped by the registry rather than hard-coded, because one class can
     * back several names (`regex`/`matches`, `integer`/`number`) and the
     * name the author actually wrote is what per-field custom messages are
     * keyed on (`messages: ['field' => ['custom_required_file' => '...']]`).
     */
    protected string $name = '';

    /**
     * Translation key for this rule's failure message.
     */
    protected string $message = 'rule.default';

    /**
     * Implicit rules run even when the value is empty or the key is absent.
     * Everything else is skipped for an empty value, which is what makes
     * optional fields optional.
     */
    protected bool $implicit = false;

    /**
     * @var array<string, mixed>
     */
    protected array $params = [];

    /**
     * Positional parameter names, in the order they appear after the colon
     * in a rule string (`between:1,10` fills `min` then `max`).
     *
     * @var string[]
     */
    protected array $fillableParams = [];

    private ?Attribute $attribute = null;

    /**
     * Whether the value satisfies this rule.
     */
    abstract public function check(mixed $value): bool;

    /**
     * Assign positional parameters parsed from a rule string.
     *
     * Extra parameters beyond $fillableParams are kept under numeric keys so
     * variadic rules can reach them; rules that take an open-ended list
     * (mime_type, in, not_in) override this method instead.
     */
    public function fillParameters(array $params): self
    {
        foreach ($this->fillableParams as $index => $key) {
            if (!array_key_exists($index, $params)) {
                break;
            }
            $this->params[$key] = $params[$index];
        }

        return $this;
    }

    /**
     * Assign parameters by name — used by the array rule form
     * (`'field' => ['between' => ['min' => 1, 'max' => 10]]`).
     *
     * @param array<string, mixed> $params
     */
    public function setParameters(array $params): self
    {
        $this->params = array_merge($this->params, $params);

        return $this;
    }

    public function parameter(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return $this->params;
    }

    /**
     * @param string[] $params
     *
     * @throws ValidationConfigurationException when a required parameter was
     *         not supplied — a configuration error, never a user-input error.
     */
    public function assertHasRequiredParameters(array $params): void
    {
        foreach ($params as $param) {
            if (!isset($this->params[$param])) {
                throw ValidationConfigurationException::missingParameter(
                    $this->name,
                    $param,
                    $this->attribute?->key()
                );
            }
        }
    }

    public function attribute(): ?Attribute
    {
        return $this->attribute;
    }

    public function setAttribute(Attribute $attribute): void
    {
        $this->attribute = $attribute;
    }

    public function isImplicit(): bool
    {
        return $this->implicit;
    }

    /**
     * The translation key, not the rendered text.
     */
    public function message(): string
    {
        return $this->message;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
