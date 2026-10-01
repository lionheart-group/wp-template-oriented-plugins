<?php

namespace TofuPlugin\Validation;

/**
 * Looks up a message template by key.
 *
 * An interface so rules never care where their text comes from. The shipped
 * implementation (GettextTranslator) resolves keys through WordPress's own
 * i18n stack, so validation text is translated the same way as every other
 * string in the plugin and lives in `languages/*.po`.
 */
interface Translator
{
    /**
     * @return string|null Null when the key is not in the catalogue.
     */
    public function get(string $key): ?string;
}
