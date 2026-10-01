<?php

namespace TofuPlugin\Validation;

/**
 * Resolves message keys through WordPress's own i18n stack.
 *
 * The catalogue is built from Messages::all(), whose entries are literal
 * __() calls — so translations come from `languages/*.mo` like every other
 * string in the plugin, and `wp i18n make-pot` can extract them.
 *
 * Built lazily: __() cannot translate before the textdomain is loaded on
 * `init`, and constructing this object at plugin load would freeze the
 * untranslated English in place.
 */
class GettextTranslator implements Translator
{
    /** @var array<string, string>|null */
    protected ?array $messages = null;

    /**
     * Per-field overrides from `ValidationConfig::$messages`, keyed
     * `field:rule`. These take precedence over the catalogue and are never
     * translated — the form author already wrote them in the language they
     * want.
     *
     * @var array<string, string>
     */
    protected array $overrides = [];

    /**
     * @param array<string, string> $overrides
     */
    public function __construct(array $overrides = [])
    {
        $this->overrides = $overrides;
    }

    public function get(string $key): ?string
    {
        if (isset($this->overrides[$key])) {
            return $this->overrides[$key];
        }

        $this->messages ??= Messages::all();

        $message = $this->messages[$key] ?? null;

        return is_string($message) ? $message : null;
    }

    /**
     * @param array<string, string> $overrides
     */
    public function addOverrides(array $overrides): void
    {
        $this->overrides = array_merge($this->overrides, $overrides);
    }
}
