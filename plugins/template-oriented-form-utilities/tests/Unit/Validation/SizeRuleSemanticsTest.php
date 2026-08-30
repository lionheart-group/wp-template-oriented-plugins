<?php

namespace TofuPlugin\Tests\Unit\Validation;

use TofuPlugin\Tests\Unit\Validation\Fixtures\Corpus;
use TofuPlugin\Tests\Unit\BaseTestCase;
use TofuPlugin\Tests\Unit\Validation\Support\EngineProbe;

/**
 * Pins the second-highest-risk behaviour: how `max`/`min`/`between` decide
 * what "size" means for a given value.
 *
 * The naive assumption — "a numeric-looking string compares numerically" —
 * is WRONG and, if implemented literally in the replacement, silently
 * rejects postal codes, phone numbers, and member IDs on every site that
 * never touched its `max:N` config. The actual rule: a string is measured
 * by character count (mb_strlen) UNLESS the same field also carries a
 * `numeric`/`integer` rule, in which case it is compared as a number.
 */
class SizeRuleSemanticsTest extends BaseTestCase
{
    /**
     * TOFU's own documented example: `'phone' => 'max:20'`. A 10-digit
     * phone number is compared by CHARACTER LENGTH (10 <= 20), not by
     * numeric value — if it were compared numerically, essentially every
     * real phone number would still pass, but the point is the mechanism,
     * not this particular number.
     */
    public function testPhoneNumberStringIsComparedByLength(): void
    {
        $result = EngineProbe::run(['phone' => '0312345678'], ['phone' => 'max:20']);
        $this->assertFalse($result['fails'], 'A 10-character numeric string must pass max:20 by length.');
    }

    /**
     * The case that actually distinguishes the two interpretations: a
     * numeric string whose LENGTH is small but whose VALUE is large.
     */
    public function testNumericLookingStringIsComparedByLengthNotValue(): void
    {
        $result = EngineProbe::run(['field' => '100'], ['field' => 'max:20']);
        $this->assertFalse(
            $result['fails'],
            "'100' has length 3, so max:20 must PASS by length. " .
            'If the replacement compares 100 numerically against 20 instead, this fails — that is the regression to guard against.'
        );
    }

    /**
     * Adding `numeric` (or `integer`) to the SAME field is what flips the
     * comparison to numeric — and it does so regardless of where in the
     * pipe string it appears.
     */
    public function testAddingNumericRuleSwitchesToValueComparison(): void
    {
        $result = EngineProbe::run(['field' => '100'], ['field' => 'numeric|max:20']);
        $this->assertTrue($result['fails'], '100 > 20 numerically, so this must now FAIL.');
    }

    public function testNumericRuleOrderDoesNotMatter(): void
    {
        $ruleFirst = EngineProbe::run(['field' => '100'], ['field' => 'numeric|max:20']);
        $ruleLast  = EngineProbe::run(['field' => '100'], ['field' => 'max:20|numeric']);

        $this->assertTrue($ruleFirst['fails']);
        $this->assertSame($ruleFirst['fails'], $ruleLast['fails'], 'numeric|max and max|numeric must behave identically.');
    }

    /**
     * A native PHP int is always compared by value, with no `numeric` rule
     * needed — only STRINGS get the character-length treatment.
     */
    public function testNativeIntegerIsAlwaysComparedByValue(): void
    {
        $result = EngineProbe::run(['field' => 100], ['field' => 'max:20']);
        $this->assertTrue($result['fails'], 'A native int must compare numerically even without an explicit numeric rule.');
    }

    /**
     * Japanese names routinely exceed 20 bytes but not 20 characters.
     * mb_strlen (not strlen/byte count) is what makes `max:200` on a name
     * field usable at all for Japanese input.
     */
    public function testMultibyteStringIsMeasuredInCharactersNotBytes(): void
    {
        $fiveChars = 'あいうえお'; // 5 characters, 15 bytes in UTF-8

        $result = EngineProbe::run(['name' => $fiveChars], ['name' => 'max:5']);
        $this->assertFalse($result['fails'], '5-character string must pass max:5 under mb_strlen.');

        $result = EngineProbe::run(['name' => $fiveChars], ['name' => 'max:4']);
        $this->assertTrue($result['fails'], 'Same string must fail max:4 — proving character count, not something coarser, is used.');
    }

    public function testArrayIsMeasuredByCount(): void
    {
        $result = EngineProbe::run(['tags' => ['a', 'b', 'c']], ['tags' => 'max:2']);
        $this->assertTrue($result['fails']);

        $result = EngineProbe::run(['tags' => ['a', 'b', 'c']], ['tags' => 'max:5']);
        $this->assertFalse($result['fails']);
    }

    /**
     * IMPORTANT CORRECTION vs. earlier (secondhand) research: a plain
     * (non-file-specific) size rule against a $_FILES-shaped array does
     * NOT count keys — the engine recognizes the `tmp_name` + `size` shape
     * as an uploaded file and uses the file's actual byte size. Verified
     * directly against the installed engine (not assumed) because this
     * contradicts what earlier exploration had concluded.
     */
    public function testUploadedFileShapedArrayThroughPlainMaxUsesByteSizeNotKeyCount(): void
    {
        $fileArray = [
            'name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => Corpus::samplePath(),
            'error' => \UPLOAD_ERR_OK, 'size' => 999999999,
        ];

        // 5 keys, so a key-count interpretation would pass max:5 — but the
        // huge `size` value must fail even a generous max:6.
        $result = EngineProbe::run(['attachment' => $fileArray], ['attachment' => 'max:6']);
        $this->assertTrue($result['fails'], 'max must compare the file byte size (999999999), not the 5-key count.');

        $smallFileArray = ['name' => 'a.txt', 'tmp_name' => Corpus::samplePath(), 'error' => \UPLOAD_ERR_OK, 'size' => 3];
        $result = EngineProbe::run(['attachment' => $smallFileArray], ['attachment' => 'max:6']);
        $this->assertFalse($result['fails'], 'A 3-byte file must pass max:6 by size.');

        $result = EngineProbe::run(['attachment' => $smallFileArray], ['attachment' => 'max:2']);
        $this->assertTrue($result['fails'], 'The same 3-byte file must fail max:2 by size.');
    }

    /**
     * The file-size interpretation only kicks in when the array actually
     * has a `size` key. An array that merely happens to share some key
     * names with an uploaded file, but lacks `size`, falls back to plain
     * key counting — same as any other array.
     */
    public function testArrayWithoutASizeKeyFallsBackToKeyCountEvenIfShapedLikeAFile(): void
    {
        $arrayWithoutSizeKey = ['name' => 'x', 'type' => 't', 'tmp_name' => '/tmp/x', 'error' => 0, 'foo' => 1];

        $result = EngineProbe::run(['attachment' => $arrayWithoutSizeKey], ['attachment' => 'max:4']);
        $this->assertTrue($result['fails'], '5 keys must fail max:4 by count when there is no `size` key to prefer.');

        $result = EngineProbe::run(['attachment' => $arrayWithoutSizeKey], ['attachment' => 'max:5']);
        $this->assertFalse($result['fails']);
    }

    /**
     * `max`/`min` do NOT trim the value first — unlike `required`,
     * trailing/leading whitespace counts toward the length.
     */
    public function testSizeRulesDoNotTrimWhitespace(): void
    {
        $result = EngineProbe::run(['field' => 'ab   '], ['field' => 'max:3']);
        $this->assertTrue($result['fails'], "'ab   ' is 5 characters untrimmed and must fail max:3.");

        $result = EngineProbe::run(['field' => 'ab   '], ['field' => 'min:4']);
        $this->assertFalse($result['fails'], "The same untrimmed 5-character value must pass min:4.");
    }
}
