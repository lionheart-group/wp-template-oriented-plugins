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
     * A rule key containing a dot names a field called exactly that — it is
     * NOT read as a path into nested data.
     *
     * The previous engine did treat it as a path, which produced a nested
     * array where a flat message was expected and fatally errored on the
     * way out (see the integration test below). Treating the key literally
     * is both simpler and the only behaviour a form could ever have used.
     */
    public function testDottedFieldNameIsTreatedAsALiteralKey(): void
    {
        $result = EngineProbe::run(['a.b' => ''], ['a.b' => 'required']);

        $this->assertTrue($result['fails']);
        $this->assertSame(['a.b'], array_keys($result['errors']));
    }

    /**
     * And it now survives the real pathway, where the nested shape used to
     * hit ValidationErrorCollection::addError(string $field, string $message)
     * and raise a TypeError — taking the whole request down.
     */
    public function testDottedFieldNameNoLongerFatalsThroughTheRealValidationPathway(): void
    {
        $form = $this->makeForm(['a.b' => 'required']);

        (new Validation())->validate($form, ['a.b' => '']);

        $this->assertTrue($form->getErrors()->hasFieldErrors('a.b'));
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
