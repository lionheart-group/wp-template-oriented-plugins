<?php

namespace TofuPlugin\Tests\Unit\Validation;

use TofuPlugin\Tests\Unit\BaseTestCase;
use TofuPlugin\Tests\Unit\Validation\Support\EngineProbe;

/**
 * Pins locale resolution (src/Models/Validation.php's get_locale() ->
 * base-language switch) and records the exact rendered text per locale —
 * a reference point for what is INTENTIONALLY lost when Phase 1 narrows
 * support to en/ja only (de/fr/tr/zh fall back to English; see the plan's
 * decision log).
 */
class LocaleMessagesTest extends BaseTestCase
{
    public function testJapaneseLocaleLoadsTheProjectOwnedTranslationFile(): void
    {
        $result = EngineProbe::run(['n' => ''], ['n' => 'required'], [], [], 'ja_JP');
        $this->assertSame('nは必須項目です', $result['errors']['n']);
    }

    public function testEnglishLocaleUsesTheLibraryBundledMessage(): void
    {
        $result = EngineProbe::run(['n' => ''], ['n' => 'required'], [], [], 'en_US');
        $this->assertSame('n is required', $result['errors']['n']);
    }

    /**
     * de / fr / tr / zh now fall back to English.
     *
     * The departing library bundled catalogues for those four; the plugin
     * ships translations only for the languages it actually supports, and
     * anything without a `languages/*.po` file renders the English source.
     * Adding a locale is now a matter of contributing a .po rather than
     * waiting on an upstream release — which is the main reason for routing
     * messages through WordPress i18n in the first place.
     */
    public function testUnshippedLocalesFallBackToEnglish(): void
    {
        foreach (['de_DE', 'fr_FR', 'tr_TR', 'zh_TW'] as $locale) {
            $this->assertSame(
                'n is required',
                EngineProbe::run(['n' => ''], ['n' => 'required'], [], [], $locale)['errors']['n'],
                "Expected the English fallback for {$locale}."
            );
        }
    }

    public function testUnsupportedLocaleFallsBackToEnglish(): void
    {
        $result = EngineProbe::run(['n' => ''], ['n' => 'required'], [], [], 'ko_KR');
        $this->assertSame('n is required', $result['errors']['n']);
    }

    public function testAllRegionalChineseVariantsCollapseToTheSameCatalogue(): void
    {
        $tw = EngineProbe::run(['n' => ''], ['n' => 'required'], [], [], 'zh_TW');
        $cn = EngineProbe::run(['n' => ''], ['n' => 'required'], [], [], 'zh_CN');
        $hk = EngineProbe::run(['n' => ''], ['n' => 'required'], [], [], 'zh_HK');

        $this->assertSame($tw['errors']['n'], $cn['errors']['n']);
        $this->assertSame($tw['errors']['n'], $hk['errors']['n']);
    }

    /**
     * The prohibited_* family is translated now.
     *
     * Those four keys had no Japanese entry at all, so ja_JP sites rendered
     * the raw message key — literally the text "rule.prohibited_with" — on
     * the page. Moving the catalogue into languages/*.po closed the gap.
     */
    public function testProhibitedWithIsTranslatedRatherThanRenderingItsKey(): void
    {
        $result = EngineProbe::run(
            ['field' => 'x', 'other' => 'x'],
            ['field' => 'prohibited_with:other'],
            [], [], 'ja_JP',
        );

        $this->assertTrue($result['fails']);
        $this->assertStringNotContainsString('rule.', $result['errors']['field']);
        $this->assertSame('fieldは"other"がある場合は使用できません', $result['errors']['field']);
    }

    /**
     * The plugin's own file rules are translated too. Their text came from
     * __() calls that were then overridden by the English catalogue, so
     * ja_JP sites saw English for file fields and Japanese everywhere else.
     */
    public function testPluginFileRuleMessagesAreTranslated(): void
    {
        $result = EngineProbe::run([], ['attachment' => 'custom_required_file'], [], [], 'ja_JP');

        $this->assertTrue($result['fails']);
        $this->assertSame('attachmentを選択してください', $result['errors']['attachment']);
    }
}
