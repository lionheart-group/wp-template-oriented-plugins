<?php

namespace TofuPlugin\Tests\Unit\Validation;

use TofuPlugin\Tests\Unit\BaseTestCase;
use TofuPlugin\Tests\Unit\Validation\Support\EngineProbe;

/**
 * Pins how an error message is chosen and rendered.
 *
 * Resolution order: `field:rule` custom message, then the rule's own
 * message key (`rule.<name>`) in the active locale bag, then — if even
 * that key is missing from the bag — the bag key itself is emitted
 * literally. There is NO `rule.default` fallback in between.
 */
class MessageResolutionTest extends BaseTestCase
{
    public function testFieldRuleOverrideTakesPrecedenceOverLocaleDefault(): void
    {
        $default = EngineProbe::run(['field' => ''], ['field' => 'required']);
        $overridden = EngineProbe::run(
            ['field' => ''],
            ['field' => 'required'],
            [],
            ['field' => ['required' => 'Custom message for :attribute']],
        );

        $this->assertTrue($default['fails']);
        $this->assertTrue($overridden['fails']);
        $this->assertNotSame($default['errors']['field'], $overridden['errors']['field']);
        $this->assertSame('Custom message for field', $overridden['errors']['field']);
    }

    /**
     * An untranslated key falls back to its English source rather than
     * leaking the key onto the page.
     *
     * Under the old PHP-array catalogues a key absent from the active
     * locale rendered literally — a ja_JP visitor could be shown the text
     * "rule.prohibited_with". Going through WordPress i18n means an
     * untranslated string simply stays English, which is a far better
     * failure mode for a missing translation.
     */
    public function testUntranslatedKeyFallsBackToEnglishRatherThanRenderingTheKey(): void
    {
        // `ip` has no dedicated Japanese wording of its own in this test's
        // locale path, so it exercises the fallback rather than a hit.
        $result = EngineProbe::run(
            ['field' => 'not-an-ip'],
            ['field' => 'ip'],
            [], [], 'ko_KR',
        );

        $this->assertTrue($result['fails']);
        $this->assertStringNotContainsString('rule.', $result['errors']['field']);
        $this->assertSame('Enter field as a valid IP address', $result['errors']['field']);
    }

    public function testAttributeWithNoAliasRendersTheRawFieldKeyVerbatim(): void
    {
        $result = EngineProbe::run(['first_name' => ''], ['first_name' => 'required']);

        $this->assertTrue($result['fails']);
        $this->assertSame(
            'first_name is required',
            $result['errors']['first_name'],
            'No humanisation (no underscore-to-space, no ucfirst) happens without an explicit alias.'
        );
    }

    public function testAliasIsUsedVerbatimInPlaceOfTheFieldKey(): void
    {
        $result = EngineProbe::run(
            ['first_name' => ''],
            ['first_name' => 'required'],
            ['first_name' => 'お名前'],
        );

        $this->assertTrue($result['fails']);
        $this->assertSame('お名前 is required', $result['errors']['first_name']);
    }

    /**
     * Regression guard for the interpolation mechanism itself: `:max_mb`
     * must not be corrupted by a naive substring replacement of `:max`
     * (a `str_replace` loop over an unordered map would do exactly that,
     * turning ":max_mb" into "5_mb"). The current engine gets this right;
     * a from-scratch replacement must too.
     */
    public function testMaxMbPlaceholderIsNotClobberedByMaxSubstringReplacement(): void
    {
        $oversizedFile = [
            'tmp_name' => \TofuPlugin\Tests\Unit\Validation\Fixtures\Corpus::samplePath(),
            'size' => 999999999,
        ];

        $result = EngineProbe::run(['attachment' => $oversizedFile], ['attachment' => 'max_mb:7']);

        $this->assertTrue($result['fails']);
        $this->assertStringContainsString('7 MB', $result['errors']['attachment']);
        $this->assertStringNotContainsString('7_mb', $result['errors']['attachment']);
    }

    public function testListParametersAreJoinedWithQuotedCommaSeparation(): void
    {
        $result = EngineProbe::run(['field' => 'zzz'], ['field' => 'in:a,b,c']);

        $this->assertTrue($result['fails']);
        $this->assertSame('field must be one of "a", "b", "c"', $result['errors']['field']);
    }

    public function testUnknownPlaceholderIsLeftUnreplacedInTheMessage(): void
    {
        $result = EngineProbe::run(
            ['field' => ''],
            ['field' => 'required'],
            [],
            ['field' => ['required' => ':attribute :nope :max']],
        );

        $this->assertTrue($result['fails']);
        $this->assertSame('field :nope :max', $result['errors']['field']);
    }
}
