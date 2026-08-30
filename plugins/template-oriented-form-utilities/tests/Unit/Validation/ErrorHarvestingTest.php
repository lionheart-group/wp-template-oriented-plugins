<?php

namespace TofuPlugin\Tests\Unit\Validation;

use TofuPlugin\Models\Form;
use TofuPlugin\Models\Validation;
use TofuPlugin\Structure\FormConfig;
use TofuPlugin\Structure\MailConfig;
use TofuPlugin\Structure\MailRecipientsCollection;
use TofuPlugin\Structure\MailRecipientsConfig;
use TofuPlugin\Structure\ValidationConfig;
use TofuPlugin\Tests\Unit\BaseTestCase;
use TofuPlugin\Tests\Unit\Validation\Support\EngineProbe;

/**
 * Pins how failures across multiple fields (and multiple rules on one
 * field) are collected into the single "one message per field" shape that
 * TofuPlugin\Models\ValidationErrorCollection expects.
 */
class ErrorHarvestingTest extends BaseTestCase
{
    // -----------------------------------------------------------------
    // Field ordering: driven by the `rules` array, not the data array
    // -----------------------------------------------------------------

    public function testErrorOrderFollowsTheRulesArrayNotTheDataArray(): void
    {
        $result = EngineProbe::run(
            ['z' => '', 'a' => '', 'm' => ''],
            ['a' => 'required', 'm' => 'required', 'z' => 'required'],
        );

        $this->assertSame(['a', 'm', 'z'], array_keys($result['errors']));
    }

    public function testReversingBothArraysStillFollowsTheRulesArrayOrder(): void
    {
        $result = EngineProbe::run(
            ['a' => '', 'm' => '', 'z' => ''],
            ['z' => 'required', 'm' => 'required', 'a' => 'required'],
        );

        $this->assertSame(['z', 'm', 'a'], array_keys($result['errors']));
    }

    public function testFieldPresentInDataButAbsentFromRulesNeverAppearsInErrors(): void
    {
        $result = EngineProbe::run(
            ['tracked' => '', 'untracked' => ''],
            ['tracked' => 'required'],
        );

        $this->assertArrayHasKey('tracked', $result['errors']);
        $this->assertArrayNotHasKey('untracked', $result['errors']);
    }

    public function testFieldPresentInRulesButAbsentFromDataStillAppearsWhenItFails(): void
    {
        $result = EngineProbe::run([], ['missing_field' => 'required']);

        $this->assertArrayHasKey('missing_field', $result['errors']);
    }

    // -----------------------------------------------------------------
    // Exactly one message per field: the first rule (in declaration order)
    // that fails, even though the engine itself keeps checking the rest.
    // -----------------------------------------------------------------

    public function testOnlyTheFirstFailingRuleInDeclarationOrderIsReported(): void
    {
        $maxFirst = EngineProbe::run(['field' => 'abcdef'], ['field' => 'required|max:2|min:100']);
        $minFirst = EngineProbe::run(['field' => 'abcdef'], ['field' => 'required|min:100|max:2']);

        $this->assertCount(1, $maxFirst['errors']);
        $this->assertStringContainsString('maximum', $maxFirst['errors']['field']);

        $this->assertCount(1, $minFirst['errors']);
        $this->assertStringContainsString('minimum', $minFirst['errors']['field']);
    }

    /**
     * A non-implicit rule that gets SKIPPED (because the value is empty)
     * does not count as "the first rule" — the next one in line that
     * actually runs is what produces the message.
     */
    public function testSkippedRuleDoesNotProduceTheReportedMessage(): void
    {
        $result = EngineProbe::run(['field' => ''], ['field' => 'email|required']);

        // `email` is non-implicit and skipped on empty input, so `required`
        // (which DOES run on empty input) is the one that actually fails.
        $this->assertStringContainsString('required', $result['errors']['field']);
    }

    // -----------------------------------------------------------------
    // Dot/wildcard rule keys: currently unsupported end-to-end.
    // -----------------------------------------------------------------

    /**
     * The raw engine happily produces a NESTED array for a dotted field
     * name (`firstOfAll()` interprets the dot as a path)...
     */
    public function testRawEngineNestsErrorsForDottedFieldNames(): void
    {
        $result = EngineProbe::run(['a.b' => ''], ['a.b' => 'required']);

        // Our own EngineProbe casts each message to string for the golden
        // corpus's sake, which turns the nested array into a PHP warning
        // (promoted to an exception by our own error handler) — already a
        // symptom that something downstream expects a flat string here.
        $this->assertArrayHasKey('throws', $result);
    }

    /**
     * ...but that nested shape reaches TofuPlugin\Models\ValidationErrorCollection
     * ::addError(string $field, string $message), whose `string` type hint
     * cannot accept it. In other words: dot/wildcard rule keys are not
     * merely "unsupported" in this plugin today, they FATAL the request.
     * No documented TOFU form uses this notation (confirmed by grep across
     * docs/), so this is safe to leave broken — but the replacement should
     * treat rule keys as flat, literal strings rather than silently
     * "fixing" this into working dot-path support, which nobody asked for
     * and nothing here has ever tested.
     */
    public function testDottedFieldNameFatalsThroughTheRealValidationPathway_KNOWN_BUG(): void
    {
        $form = $this->makeForm(['a.b' => 'required']);

        $this->expectException(\TypeError::class);

        (new Validation())->validate($form, ['a.b' => '']);
    }

    private function makeForm(array $rules): Form
    {
        $config = new FormConfig(
            key: 'dot-notation-test',
            name: 'Dot Notation Test',
            mail: new MailConfig(
                fromEmail: 'noreply@example.com',
                fromName: 'Test',
                recipients: new MailRecipientsCollection([
                    new MailRecipientsConfig(
                        recipientEmail: 'admin@example.com',
                        subject: 'Test',
                        mailBody: 'Test body',
                    ),
                ]),
            ),
            validation: new ValidationConfig(
                allows: array_keys($rules),
                rules: $rules,
                names: [],
            ),
        );

        return new Form($config);
    }
}
