<?php

namespace TofuPlugin\Tests\Unit\Validation\Support;

use TofuPlugin\Validation\GettextTranslator;
use TofuPlugin\Validation\ValidatorFactory;

/**
 * The single choke point this whole test suite drives — and the ONLY file a
 * future engine swap (replacing somnambulist/validation) is expected to
 * rewrite.
 *
 * `run()` mirrors the wiring in src/Models/Validation.php exactly (custom
 * rule registration, `field:rule` message flattening, locale resolution)
 * so that the golden corpus and fixtures in this directory describe the
 * *plugin's* validation contract, not a bare library call. When the engine
 * is swapped, only this file's internals change; the corpus, the generated
 * `expected.json`, and every hand-written test stay untouched. An unchanged
 * ValidationGoldenTest passing before and after the swap is the proof of
 * behavioural equivalence — see docs/settings/validationconfig.md and the
 * plan this suite was built from for the full rationale.
 */
final class EngineProbe
{
    /**
     * Every rule name the engine resolves. Used by ValidationGoldenTest to
     * prove the corpus has no blind spots.
     *
     * Like run(), this is engine-specific and gets rewritten by an engine
     * swap; the assertion that consumes it does not.
     *
     * @return string[]
     */
    public static function registeredRuleNames(): array
    {
        return array_keys(ValidatorFactory::defaultRules());
    }

    /**
     * @param array<string, mixed>                 $data     Merged POST + FILES-shaped input.
     * @param array<string, mixed>                 $rules    Field => rule definition (string or array).
     * @param array<string, string>                $aliases  Field => human-readable alias.
     * @param array<string, array<string, string>>  $messages Field => [rule => message].
     * @param string                                $locale   Raw get_locale()-style value, e.g. 'ja_JP'.
     * @return array{fails: bool, errors: array<string, string>}|array{throws: string, message: string}
     */
    public static function run(
        array $data,
        array $rules,
        array $aliases = [],
        array $messages = [],
        string $locale = 'en_US',
    ): array {
        // Some library rules (date/digits/length/regex/url) implicitly cast
        // an array value to string and emit an E_WARNING rather than
        // failing cleanly. PHPUnit's `convertWarningsToExceptions="true"`
        // turns that into a thrown exception, but a plain `php` CLI run
        // (as used by scripts/regenerate-validation-golden.php) does not —
        // so without normalizing this, the golden fixture and the test run
        // would disagree on cases that have nothing to do with real
        // validation semantics. Installing our own handler here makes the
        // outcome deterministic regardless of the ambient runtime.
        set_error_handler(static function (int $errno, string $errstr): bool {
            throw new \ErrorException($errstr, 0, $errno);
        });

        // The engine reads the locale through get_locale(), which
        // tests/bootstrap.php resolves from this global.
        $previousLocale = $GLOBALS['__tofu_test_locale'] ?? null;
        $GLOBALS['__tofu_test_locale'] = $locale;

        try {
            // Mirrors the 'field:rule' flattening in src/Models/Validation.php.
            $customMessages = [];
            foreach ($messages as $field => $ruleMsgs) {
                foreach ($ruleMsgs as $rule => $message) {
                    $customMessages[$field . ':' . $rule] = $message;
                }
            }

            $factory = new ValidatorFactory(new GettextTranslator($customMessages));

            $validation = $factory->make($data, $rules)
                ->setAliases($aliases)
                ->validate();

            $errors = [];
            foreach ($validation->errors()->firstOfAll() as $field => $message) {
                $errors[(string) $field] = (string) $message;
            }

            return ['fails' => $validation->fails(), 'errors' => $errors];
        } catch (\Throwable $e) {
            return ['throws' => get_class($e), 'message' => self::normalizeMessage($e->getMessage())];
        } finally {
            restore_error_handler();

            if ($previousLocale === null) {
                unset($GLOBALS['__tofu_test_locale']);
            } else {
                $GLOBALS['__tofu_test_locale'] = $previousLocale;
            }
        }
    }

    /**
     * Strip machine- and engine-specific detail out of an exception message
     * before it is frozen into the golden fixture.
     *
     * PHP's TypeError messages tail with `, called in <abs path> on line N`.
     * Freezing that verbatim would (a) bake this developer's home directory
     * into the repository, (b) fail on every other machine and in CI, and
     * (c) pin a `vendor/` path and line number that cease to exist the
     * moment the engine is replaced. None of that is behaviour worth
     * asserting — the exception type and the semantic part of the message
     * are.
     */
    private static function normalizeMessage(string $message): string
    {
        // ", called in /abs/path/File.php on line 21"  ->  ", called in <file>"
        $message = (string) preg_replace(
            '#, called in \S+ on line \d+#',
            ', called in <file>',
            $message
        );

        // Any remaining absolute path anywhere in the message.
        $projectRoot = dirname(__DIR__, 4);

        return str_replace($projectRoot . '/', '<project>/', $message);
    }
}
