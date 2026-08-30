<?php

namespace TofuPlugin\Tests\Unit\Validation\Fixtures;

/**
 * Pure-data golden-corpus definition — no PHPUnit dependency.
 *
 * Generates a combinatorial set of (data, rules, aliases, messages, locale)
 * cases covering the validation behaviour TOFU forms actually depend on.
 * Each case has a stable string ID. `scripts/regenerate-validation-golden.php`
 * runs every case through Support\EngineProbe (currently backed by
 * somnambulist/validation) and freezes the results into `expected.json`.
 * `ValidationGoldenTest` then replays the same corpus and asserts the
 * current engine still produces the frozen result.
 *
 * Coverage is deliberately exhaustive: EVERY rule the engine registers gets
 * the full value sweep, enforced by
 * ValidationGoldenTest::testCorpusCoversEveryRuleTheEngineRegisters().
 * The in-house engine replacing somnambulist/validation ports all of them,
 * so there is no such thing as an "out of scope" rule here.
 *
 * IMPORTANT — the regeneration rule:
 * Regenerating `expected.json` is only legitimate while the ENGINE IS
 * UNCHANGED (e.g. adding corpus cases against the current engine). Once the
 * engine is swapped, the fixture is frozen permanently: a deliberate
 * behaviour change is recorded in `expected-overrides.json` with a written
 * reason, never by regenerating — that would launder the very change the
 * fixture exists to expose.
 */
final class Corpus
{
    /**
     * The 20-value axis used throughout the value sweeps below.
     *
     * Chosen to separate every "empty" boundary the engine treats specially
     * (see docs/settings/validationconfig.md's rule table once Phase 1
     * lands) from values that merely look similar.
     *
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        return [
            'null'               => null,
            'empty_string'       => '',
            'single_space'       => ' ',
            'ascii_whitespace'   => "\n\t",
            'ideographic_space'  => "\u{3000}",
            'zero_string'        => '0',
            'zero_int'           => 0,
            'zero_float'         => 0.0,
            'false'              => false,
            'true'               => true,
            'empty_array'        => [],
            'array_zero'         => [0],
            'array_list'         => ['a', 'b', 'c'],
            'string_abc'         => 'abc',
            'string_japanese'    => 'あいうえお',
            'string_phone'       => '0312345678',
            'numeric_string_100' => '100',
            'int_100'            => 100,
            'file_ok'            => self::uploadedFileArray(),
            'file_no_file'       => [
                'name' => '', 'type' => '', 'tmp_name' => '', 'error' => \UPLOAD_ERR_NO_FILE, 'size' => 0,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function uploadedFileArray(): array
    {
        $path = self::samplePath();
        return [
            'name' => 'sample.txt',
            'type' => 'text/plain',
            'tmp_name' => $path,
            'error' => \UPLOAD_ERR_OK,
            'size' => is_file($path) ? filesize($path) : 0,
        ];
    }

    public static function samplePath(): string
    {
        return __DIR__ . '/sample.txt';
    }

    private const LOCALES = ['en_US', 'ja_JP'];

    /**
     * Rules taking no meaningful parameter.
     *
     * @var string[]
     */
    private const PARAMLESS_RULES = [
        // Covered since the first frozen baseline.
        'required', 'present', 'accepted', 'email', 'numeric', 'integer',
        'boolean', 'string', 'array', 'date', 'url', 'alpha', 'alpha_num', 'alpha_dash',
        // Added when the port scope widened to every registered rule.
        'alpha_spaces', 'float', 'ip', 'ipv4', 'ipv6', 'json', 'lowercase',
        'nullable', 'number', 'phone', 'prohibited', 'rejected', 'sometimes',
        'uploaded_file', 'uppercase', 'uuid',
    ];

    /**
     * Rules taking a parameter, each with one representative param set.
     *
     * @var array<string, string>
     */
    private const PARAMETERIZED_RULES = [
        // Covered since the first frozen baseline.
        'max'                   => 'max:20',
        'min'                   => 'min:3',
        'between'               => 'between:3,20',
        'length'                => 'length:5',
        'digits'                => 'digits:5',
        'in'                    => 'in:abc,foo,bar',
        'not_in'                => 'not_in:abc,foo,bar',
        'regex'                 => 'regex:/^[a-z]+$/',
        'custom_required_file'  => 'custom_required_file',
        'max_mb'                => 'max_mb:5',
        'mime_type'             => 'mime_type:text/plain,application/pdf',
        // Added when the port scope widened to every registered rule.
        'after'                    => 'after:2020-01-01',
        'before'                   => 'before:2020-01-01',
        'any_of'                   => 'any_of:abc,foo,bar',
        'array_can_only_have_keys' => 'array_can_only_have_keys:a,b',
        'array_must_have_keys'     => 'array_must_have_keys:a,b',
        'callback'                 => 'callback',
        'default'                  => 'default:fallback',
        'defaults'                 => 'defaults:fallback',
        'digits_between'           => 'digits_between:2,5',
        'ends_with'                => 'ends_with:bc',
        'starts_with'              => 'starts_with:ab',
        'extension'                => 'extension:txt,pdf',
        'matches'                  => 'matches:/^[a-z]+$/',
        'mimes'                    => 'mimes:txt,pdf',
    ];

    /**
     * Rules that compare against a sibling field.
     *
     * These get the bespoke branch sweep in addCompanionSweep(), whose case
     * IDs predate the widened port scope and are kept stable so the frozen
     * baseline stays diffable.
     *
     * @var array<string, string>
     */
    private const COMPANION_RULES = [
        'same'              => 'same:other',
        'different'         => 'different:other',
        'required_if'       => 'required_if:other,trigger',
        'required_with'     => 'required_with:other',
        'required_without'  => 'required_without:other',
    ];

    /**
     * The remaining sibling-referencing rules, added when the port scope
     * widened to every registered rule. Swept generically (sibling present
     * vs. absent) rather than with per-rule branches.
     *
     * @var array<string, string>
     */
    private const ADDITIONAL_COMPANION_RULES = [
        'required_unless'         => 'required_unless:other,trigger',
        'required_with_all'       => 'required_with_all:other',
        'required_without_all'    => 'required_without_all:other',
        'requires'                => 'requires:other',
        'prohibited_if'           => 'prohibited_if:other,trigger',
        'prohibited_unless'       => 'prohibited_unless:other,trigger',
        'prohibited_with'         => 'prohibited_with:other',
        'prohibited_with_all'     => 'prohibited_with_all:other',
        'prohibited_without'      => 'prohibited_without:other',
        'prohibited_without_all'  => 'prohibited_without_all:other',
    ];

    /**
     * @return array<string, array{
     *   data: array<string, mixed>,
     *   rules: array<string, mixed>,
     *   aliases: array<string, string>,
     *   messages: array<string, array<string, string>>,
     *   locale: string,
     * }>
     */
    public static function cases(): array
    {
        $cases = [];

        self::addParamlessValueSweep($cases);
        self::addParameterizedValueSweep($cases);
        self::addCompanionSweep($cases);
        self::addAdditionalCompanionSweep($cases);
        self::addKeyMissingSweep($cases);
        self::addCombinations($cases);
        self::addFileRuleCombinations($cases);
        self::addParsingEdgeCases($cases);
        self::addAliasAndMessageCases($cases);

        ksort($cases);

        return $cases;
    }

    private static function make(
        array $data,
        array $rules,
        array $aliases = [],
        array $messages = [],
        string $locale = 'en_US',
    ): array {
        return compact('data', 'rules', 'aliases', 'messages', 'locale');
    }

    private static function addParamlessValueSweep(array &$cases): void
    {
        foreach (self::PARAMLESS_RULES as $rule) {
            foreach (self::values() as $valueName => $value) {
                foreach (self::LOCALES as $locale) {
                    $id = "value_sweep/{$rule}/{$valueName}/{$locale}";
                    $cases[$id] = self::make(['field' => $value], ['field' => $rule], [], [], $locale);
                }
            }
        }
    }

    private static function addParameterizedValueSweep(array &$cases): void
    {
        foreach (self::PARAMETERIZED_RULES as $rule => $ruleString) {
            foreach (self::values() as $valueName => $value) {
                foreach (self::LOCALES as $locale) {
                    $id = "value_sweep/{$rule}/{$valueName}/{$locale}";
                    $cases[$id] = self::make(['field' => $value], ['field' => $ruleString], [], [], $locale);
                }
            }
        }
    }

    private static function addCompanionSweep(array &$cases): void
    {
        foreach (self::values() as $valueName => $value) {
            foreach (self::LOCALES as $locale) {
                // same / different: companion is a fixed string.
                $cases["companion/same/{$valueName}/{$locale}"] = self::make(
                    ['field' => $value, 'other' => 'xyz'],
                    ['field' => self::COMPANION_RULES['same']],
                    [], [], $locale
                );
                $cases["companion/different/{$valueName}/{$locale}"] = self::make(
                    ['field' => $value, 'other' => 'xyz'],
                    ['field' => self::COMPANION_RULES['different']],
                    [], [], $locale
                );

                // required_if:other,trigger — branch where the trigger matches
                // and where it doesn't.
                $cases["companion/required_if_active/{$valueName}/{$locale}"] = self::make(
                    ['field' => $value, 'other' => 'trigger'],
                    ['field' => self::COMPANION_RULES['required_if']],
                    [], [], $locale
                );
                $cases["companion/required_if_inactive/{$valueName}/{$locale}"] = self::make(
                    ['field' => $value, 'other' => 'not-the-trigger'],
                    ['field' => self::COMPANION_RULES['required_if']],
                    [], [], $locale
                );

                // required_with:other — branch where the companion is present
                // (non-empty) and where it's absent.
                $cases["companion/required_with_present/{$valueName}/{$locale}"] = self::make(
                    ['field' => $value, 'other' => 'present'],
                    ['field' => self::COMPANION_RULES['required_with']],
                    [], [], $locale
                );
                $cases["companion/required_with_absent/{$valueName}/{$locale}"] = self::make(
                    ['field' => $value],
                    ['field' => self::COMPANION_RULES['required_with']],
                    [], [], $locale
                );

                // required_without:other — inverse branches.
                $cases["companion/required_without_present/{$valueName}/{$locale}"] = self::make(
                    ['field' => $value, 'other' => 'present'],
                    ['field' => self::COMPANION_RULES['required_without']],
                    [], [], $locale
                );
                $cases["companion/required_without_absent/{$valueName}/{$locale}"] = self::make(
                    ['field' => $value],
                    ['field' => self::COMPANION_RULES['required_without']],
                    [], [], $locale
                );
            }
        }
    }

    /**
     * Generic two-branch sweep for the sibling-referencing rules added when
     * the port scope widened: once with the companion field present and
     * non-empty, once with it absent entirely. Between them these cover the
     * "condition fires" / "condition does not fire" sides of every
     * required_* and prohibited_* variant.
     */
    private static function addAdditionalCompanionSweep(array &$cases): void
    {
        foreach (self::ADDITIONAL_COMPANION_RULES as $rule => $ruleString) {
            foreach (self::values() as $valueName => $value) {
                foreach (self::LOCALES as $locale) {
                    $cases["companion/{$rule}/sibling_present/{$valueName}/{$locale}"] = self::make(
                        ['field' => $value, 'other' => 'trigger'],
                        ['field' => $ruleString],
                        [], [], $locale
                    );
                    $cases["companion/{$rule}/sibling_absent/{$valueName}/{$locale}"] = self::make(
                        ['field' => $value],
                        ['field' => $ruleString],
                        [], [], $locale
                    );
                }
            }
        }
    }

    private static function addKeyMissingSweep(array &$cases): void
    {
        foreach (self::PARAMLESS_RULES as $rule) {
            $cases["key_missing/{$rule}"] = self::make([], ['field' => $rule]);
        }
        foreach (self::PARAMETERIZED_RULES as $rule => $ruleString) {
            $cases["key_missing/{$rule}"] = self::make([], ['field' => $ruleString]);
        }
        foreach (self::COMPANION_RULES as $rule => $ruleString) {
            $cases["key_missing/{$rule}"] = self::make(['other' => 'xyz'], ['field' => $ruleString]);
        }
        foreach (self::ADDITIONAL_COMPANION_RULES as $rule => $ruleString) {
            $cases["key_missing/{$rule}"] = self::make(['other' => 'trigger'], ['field' => $ruleString]);
        }
    }

    /**
     * Every rule name this corpus exercises.
     *
     * ValidationGoldenTest asserts this matches the set the engine actually
     * registers, so a rule can never be added to (or dropped from) the
     * engine without the corpus noticing.
     *
     * @return string[]
     */
    public static function coveredRuleNames(): array
    {
        $names = array_merge(
            self::PARAMLESS_RULES,
            array_keys(self::PARAMETERIZED_RULES),
            array_keys(self::COMPANION_RULES),
            array_keys(self::ADDITIONAL_COMPANION_RULES),
        );
        sort($names);

        return $names;
    }

    private static function addCombinations(array &$cases): void
    {
        // Order-independence of the numeric/integer size-comparison switch.
        $cases['combo/numeric_then_max'] = self::make(
            ['field' => '0312345678'],
            ['field' => 'numeric|max:20'],
        );
        $cases['combo/max_then_numeric'] = self::make(
            ['field' => '0312345678'],
            ['field' => 'max:20|numeric'],
        );

        // Multiple simultaneous failures — which message wins firstOfAll()?
        $cases['combo/multi_fail_max_then_min'] = self::make(
            ['field' => 'abcdef'],
            ['field' => 'required|max:2|min:100'],
        );
        $cases['combo/multi_fail_min_then_max'] = self::make(
            ['field' => 'abcdef'],
            ['field' => 'required|min:100|max:2'],
        );

        // required/email declaration order on an empty value.
        $cases['combo/email_then_required_on_empty'] = self::make(
            ['field' => ''],
            ['field' => 'email|required'],
        );
        $cases['combo/required_then_email_on_empty'] = self::make(
            ['field' => ''],
            ['field' => 'required|email'],
        );

        // firstOfAll() field ordering: rules-array order vs data order.
        $cases['combo/order_rules_a_m_z_data_z_a_m'] = self::make(
            ['z' => '', 'a' => '', 'm' => ''],
            ['a' => 'required', 'm' => 'required', 'z' => 'required'],
        );
        $cases['combo/order_rules_z_m_a_data_a_m_z'] = self::make(
            ['a' => '', 'm' => '', 'z' => ''],
            ['z' => 'required', 'm' => 'required', 'a' => 'required'],
        );
    }

    private static function addFileRuleCombinations(array &$cases): void
    {
        $sample = self::samplePath();
        $sampleSize = filesize($sample);

        $fileOk = ['name' => 'sample.txt', 'type' => 'text/plain', 'tmp_name' => $sample, 'error' => \UPLOAD_ERR_OK, 'size' => $sampleSize];
        $fileNoFile = ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => \UPLOAD_ERR_NO_FILE, 'size' => 0];

        // required vs custom_required_file across the scenarios that matter:
        // fresh upload, no-file selection, and the confirm-step re-render
        // where the $_FILES key is entirely absent but a session-restored
        // upload ID sits in __tofu_uploaded_files.
        foreach (['required', 'custom_required_file'] as $rule) {
            $cases["file/{$rule}/fresh_upload"] = self::make(['attachment' => $fileOk], ['attachment' => $rule]);
            $cases["file/{$rule}/no_file_selected"] = self::make(['attachment' => $fileNoFile], ['attachment' => $rule]);
            $cases["file/{$rule}/confirm_step_key_absent_with_session_id"] = self::make(
                ['__tofu_uploaded_files' => ['attachment' => 'some-persisted-id']],
                ['attachment' => $rule],
            );
            $cases["file/{$rule}/confirm_step_key_absent_no_session"] = self::make([], ['attachment' => $rule]);
        }

        // max_mb boundary values.
        $cases['file/max_mb/under'] = self::make(
            ['attachment' => ['tmp_name' => $sample, 'size' => 1024]],
            ['attachment' => 'max_mb:5'],
        );
        $cases['file/max_mb/exactly_at_boundary'] = self::make(
            ['attachment' => ['tmp_name' => $sample, 'size' => 5 * 1024 * 1024]],
            ['attachment' => 'max_mb:5'],
        );
        $cases['file/max_mb/over'] = self::make(
            ['attachment' => ['tmp_name' => $sample, 'size' => 5 * 1024 * 1024 + 1]],
            ['attachment' => 'max_mb:5'],
        );
        $cases['file/max_mb/non_numeric_size'] = self::make(
            ['attachment' => ['tmp_name' => $sample, 'size' => 'not-a-number']],
            ['attachment' => 'max_mb:5'],
        );
        $cases['file/max_mb/missing_size'] = self::make(
            ['attachment' => ['tmp_name' => $sample]],
            ['attachment' => 'max_mb:5'],
        );
        $cases['file/max_mb/self_skip_no_tmp_name'] = self::make(
            ['attachment' => ['tmp_name' => '', 'size' => 999999999]],
            ['attachment' => 'max_mb:5'],
        );

        // mime_type matching / mismatch / zero params.
        $cases['file/mime_type/matching'] = self::make(
            ['attachment' => $fileOk],
            ['attachment' => 'mime_type:text/plain,application/pdf'],
        );
        $cases['file/mime_type/mismatch'] = self::make(
            ['attachment' => $fileOk],
            ['attachment' => 'mime_type:application/pdf,image/jpeg'],
        );
        $cases['file/mime_type/zero_params_throws'] = self::make(
            ['attachment' => $fileOk],
            ['attachment' => 'mime_type'],
        );
        $cases['file/mime_type/self_skip_no_tmp_name'] = self::make(
            ['attachment' => $fileNoFile],
            ['attachment' => 'mime_type:text/plain'],
        );
    }

    private static function addParsingEdgeCases(array &$cases): void
    {
        $cases['parse/regex_with_colon_in_pattern'] = self::make(
            ['field' => '12:34'],
            ['field' => 'regex:/^\d{2}:\d{2}$/'],
        );
        $cases['parse/regex_with_pipe_in_pattern_throws'] = self::make(
            ['field' => 'a'],
            ['field' => 'regex:/^(a|b)$/'],
        );
        $cases['parse/regex_with_pipe_via_array_form'] = self::make(
            ['field' => 'a'],
            ['field' => ['regex:/^(a|b)$/']],
        );
        $cases['parse/in_with_quoted_comma_param'] = self::make(
            ['field' => 'a,b'],
            ['field' => 'in:"a,b",c'],
        );
        $cases['parse/trailing_pipe_tolerated'] = self::make(
            ['field' => 'abc'],
            ['field' => 'required|'],
        );
        $cases['parse/space_padded_segment_throws'] = self::make(
            ['field' => 'abc'],
            ['field' => 'required | max:5'],
        );
        $cases['parse/missing_required_param_throws'] = self::make(
            ['field' => 'abc'],
            ['field' => 'max:'],
        );
        $cases['parse/uppercase_rule_name_throws'] = self::make(
            ['field' => 'abc'],
            ['field' => 'REQUIRED'],
        );
        $cases['parse/unknown_rule_throws'] = self::make(
            ['field' => 'abc'],
            ['field' => 'no_such_rule'],
        );
        $cases['parse/array_form_equivalent_to_string'] = self::make(
            ['field' => ''],
            ['field' => ['required', 'max:5']],
        );
    }

    private static function addAliasAndMessageCases(array &$cases): void
    {
        $cases['message/no_alias_uses_raw_key'] = self::make(
            ['first_name' => ''],
            ['first_name' => 'required'],
        );
        $cases['message/alias_used_verbatim'] = self::make(
            ['first_name' => ''],
            ['first_name' => 'required'],
            ['first_name' => 'お名前'],
        );
        $cases['message/no_override_uses_locale_default'] = self::make(
            ['field' => ''],
            ['field' => 'required'],
        );
        $cases['message/field_rule_override_wins'] = self::make(
            ['field' => ''],
            ['field' => 'required'],
            [],
            ['field' => ['required' => 'Custom required message for :attribute']],
        );
        // rule.prohibited_with has no entry in src/Resources/i18n/ja.php
        // (confirmed by grep) despite `prohibited_with` being a registered
        // rule — the real-world case S17 describes: when a locale bag is
        // missing a rule's message key, the bag key itself is rendered
        // literally rather than falling back to English or `rule.default`.
        $cases['message/missing_locale_key_renders_literally'] = self::make(
            ['field' => 'x', 'other' => 'x'],
            ['field' => 'prohibited_with:other'],
            [], [], 'ja_JP',
        );
        $cases['message/max_mb_placeholder_not_clobbered_by_max'] = self::make(
            ['attachment' => ['tmp_name' => self::samplePath(), 'size' => 999999999]],
            ['attachment' => 'max_mb:7'],
        );
        $cases['message/list_join_in_rule'] = self::make(
            ['field' => 'zzz'],
            ['field' => 'in:a,b,c'],
        );
    }
}
