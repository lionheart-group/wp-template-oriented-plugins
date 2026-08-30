<?php

namespace TofuPlugin\Tests\Unit\Validation;

use TofuPlugin\Tests\Unit\BaseTestCase;
use TofuPlugin\Tests\Unit\Validation\Support\EngineProbe;

/**
 * Pins the single highest-risk behaviour in the whole validation engine:
 * which values count as "empty", and which rules run anyway (implicit)
 * versus silently pass on an empty value (skipped).
 *
 * Get this wrong in the replacement and every optional field in every
 * existing TOFU form either becomes required, or a legitimately-required
 * field (e.g. '0' as a valid answer) starts silently passing `required`.
 *
 * These are hand-written (as opposed to living only in the golden corpus)
 * so a human reviewer can see the intent directly, not just a frozen JSON
 * diff — see tests/Unit/Validation/Fixtures/Corpus.php's `value_sweep/*`
 * cases for the same facts under machine-generated coverage.
 */
class EmptyValueSkippingTest extends BaseTestCase
{
    // -----------------------------------------------------------------
    // What counts as "empty" for the purpose of skipping non-implicit rules
    // -----------------------------------------------------------------

    public function testNonImplicitRuleIsSkippedForNullValue(): void
    {
        $result = EngineProbe::run(['phone' => null], ['phone' => 'max:20']);
        $this->assertFalse($result['fails'], '`max` must not run against null — the field is optional.');
    }

    public function testNonImplicitRuleIsSkippedForEmptyString(): void
    {
        $result = EngineProbe::run(['phone' => ''], ['phone' => 'max:20']);
        $this->assertFalse($result['fails']);
    }

    public function testNonImplicitRuleIsSkippedForWhitespaceOnlyString(): void
    {
        $result = EngineProbe::run(['phone' => "  \n\t "], ['phone' => 'max:20']);
        $this->assertFalse($result['fails'], 'A whitespace-only string counts as empty (the engine trims before checking).');
    }

    public function testNonImplicitRuleIsSkippedForEmptyArray(): void
    {
        $result = EngineProbe::run(['tags' => []], ['tags' => 'min:2']);
        $this->assertFalse($result['fails']);
    }

    public function testNonImplicitRuleIsSkippedForMissingKey(): void
    {
        $result = EngineProbe::run([], ['phone' => 'max:20']);
        $this->assertFalse($result['fails'], 'A key absent from the data entirely is treated the same as an empty value.');
    }

    /**
     * This is THE real-world case: TOFU's own docs use `'phone' => 'max:20'`
     * as the canonical example of an optional field with a length cap.
     */
    public function testDocumentedPhoneMaxExampleAllowsBlankInput(): void
    {
        $result = EngineProbe::run(['phone' => ''], ['phone' => 'max:20']);
        $this->assertFalse($result['fails']);

        $result = EngineProbe::run([], ['phone' => 'max:20']);
        $this->assertFalse($result['fails']);
    }

    // -----------------------------------------------------------------
    // What does NOT count as "empty" — these must still be checked
    // -----------------------------------------------------------------

    public function testStringZeroIsNotEmpty(): void
    {
        $result = EngineProbe::run(['field' => '0'], ['field' => 'required']);
        $this->assertFalse($result['fails'], "'0' is a legitimate answer and must satisfy `required`.");
    }

    public function testIntegerZeroIsNotEmpty(): void
    {
        $result = EngineProbe::run(['field' => 0], ['field' => 'required']);
        $this->assertFalse($result['fails']);
    }

    public function testFalseIsNotEmpty(): void
    {
        $result = EngineProbe::run(['field' => false], ['field' => 'required']);
        $this->assertFalse($result['fails']);
    }

    public function testFloatZeroIsNotEmpty(): void
    {
        $result = EngineProbe::run(['field' => 0.0], ['field' => 'required']);
        $this->assertFalse($result['fails']);
    }

    public function testNonEmptyArrayIsNotEmpty(): void
    {
        $result = EngineProbe::run(['field' => [0]], ['field' => 'required']);
        $this->assertFalse($result['fails']);
    }

    /**
     * And the size rules must actually evaluate against these values,
     * not silently skip them the way they would for a true empty value.
     */
    public function testSizeRuleActuallyEvaluatesAgainstStringZero(): void
    {
        // '0' has length 1 (it is not treated as empty and thus skipped) —
        // so min:1 must PASS and min:2 must FAIL, proving the rule actually
        // ran rather than being silently skipped as if '0' were empty.
        $result = EngineProbe::run(['field' => '0'], ['field' => 'min:1']);
        $this->assertFalse($result['fails']);

        $result = EngineProbe::run(['field' => '0'], ['field' => 'min:2']);
        $this->assertTrue($result['fails']);
    }

    // -----------------------------------------------------------------
    // Implicit rules run even on empty / missing values
    // -----------------------------------------------------------------

    public function testRequiredFailsOnEmptyString(): void
    {
        $result = EngineProbe::run(['field' => ''], ['field' => 'required']);
        $this->assertTrue($result['fails']);
    }

    public function testRequiredFailsOnMissingKey(): void
    {
        $result = EngineProbe::run([], ['field' => 'required']);
        $this->assertTrue($result['fails']);
    }

    public function testRequiredFailsOnEmptyArray(): void
    {
        $result = EngineProbe::run(['field' => []], ['field' => 'required']);
        $this->assertTrue($result['fails']);
    }

    public function testAcceptedFailsWhenCheckboxIsUnchecked(): void
    {
        // An unchecked checkbox submits nothing at all — accepted must be
        // implicit, or a privacy-policy checkbox could be silently bypassed.
        $result = EngineProbe::run([], ['agree' => 'accepted']);
        $this->assertTrue($result['fails']);
    }

    public function testCustomRequiredFileFailsOnMissingKeyAndNoSessionRecord(): void
    {
        $result = EngineProbe::run([], ['attachment' => 'custom_required_file']);
        $this->assertTrue($result['fails']);
    }

    // -----------------------------------------------------------------
    // present vs required — the one rule pair that DOES distinguish
    // "missing key" from "present but empty"
    // -----------------------------------------------------------------

    public function testPresentPassesWhenKeyExistsEvenIfEmpty(): void
    {
        $result = EngineProbe::run(['field' => ''], ['field' => 'present']);
        $this->assertFalse($result['fails'], '`present` only checks key existence, not emptiness.');
    }

    public function testPresentFailsWhenKeyIsMissingEntirely(): void
    {
        $result = EngineProbe::run([], ['field' => 'present']);
        $this->assertTrue($result['fails']);
    }

    // -----------------------------------------------------------------
    // Known, currently-shipping bug — reproduce as-is in Phase 1, fix in a
    // dedicated follow-up commit (Phase 1.5), never silently.
    // -----------------------------------------------------------------

    /**
     * A full-width space is a space.
     *
     * U+3000 is what a Japanese IME emits for the space bar in full-width
     * mode, so it reaches forms by accident constantly. PHP's trim() is
     * byte-wise and strips ASCII whitespace only, so such a value used to
     * read as real input and satisfy `required`. Emptiness is judged in
     * Unicode terms now.
     */
    public function testIdeographicSpaceIsTreatedAsWhitespace(): void
    {
        $fullWidth = EngineProbe::run(['field' => "\u{3000}"], ['field' => 'required']);
        $ascii     = EngineProbe::run(['field' => ' '], ['field' => 'required']);

        $this->assertTrue($fullWidth['fails'], 'A full-width space alone is not an answer.');
        $this->assertSame(
            $ascii['fails'],
            $fullWidth['fails'],
            'A full-width space must behave exactly like an ASCII space.'
        );
    }

    /**
     * The corollary: it makes an optional field optional, rather than
     * failing length checks on a value the visitor thinks is blank.
     */
    public function testIdeographicSpaceSkipsNonImplicitRulesLikeAnyOtherBlank(): void
    {
        $result = EngineProbe::run(['field' => "\u{3000}"], ['field' => 'min:3']);

        $this->assertFalse($result['fails']);
    }

    public function testAFullWidthSpaceFollowedByRealInputIsNotBlank(): void
    {
        $result = EngineProbe::run(['field' => "\u{3000}あ"], ['field' => 'required']);

        $this->assertFalse($result['fails'], 'Only leading whitespace — there is an answer here.');
    }
}
