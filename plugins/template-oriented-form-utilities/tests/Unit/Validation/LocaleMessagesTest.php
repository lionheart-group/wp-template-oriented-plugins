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
     * Reference values for locales the plugin currently supports but the
     * Phase 1 in-house engine will NOT (decision: en/ja only). These exist
     * so the readme's "de/fr/tr/zh now fall back to English" changelog
     * entry is written against a documented, verified baseline rather than
     * a guess.
     */
    public function testGermanFrenchTurkishAndChineseLocalesCurrentlyRenderTheirOwnLanguage(): void
    {
        $this->assertSame('n ist erforderlich', EngineProbe::run(['n' => ''], ['n' => 'required'], [], [], 'de_DE')['errors']['n']);
        $this->assertSame('Le champ n est obligatoire.', EngineProbe::run(['n' => ''], ['n' => 'required'], [], [], 'fr_FR')['errors']['n']);
        $this->assertSame('n zorunludur', EngineProbe::run(['n' => ''], ['n' => 'required'], [], [], 'tr_TR')['errors']['n']);
        $this->assertSame('n 必须存在', EngineProbe::run(['n' => ''], ['n' => 'required'], [], [], 'zh_TW')['errors']['n']);
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
     * src/Resources/i18n/ja.php is out of sync with the actual registered
     * rule set: it carries keys for rules that don't exist as registered
     * rules under the names it uses (rule.exists, rule.unique,
     * rule.uploaded_file.*), while some real, checkable rules
     * (prohibited_with and its siblings) have NO ja.php entry at all and
     * therefore render their literal message key under ja_JP. This test
     * documents that gap so a future ja.php cleanup (Phase 1 per the plan)
     * has a concrete "before" state to diff against.
     */
    public function testProhibitedWithHasNoJapaneseTranslationAndRendersItsKeyLiterally(): void
    {
        $result = EngineProbe::run(
            ['field' => 'x', 'other' => 'x'],
            ['field' => 'prohibited_with:other'],
            [], [], 'ja_JP',
        );

        $this->assertTrue($result['fails']);
        $this->assertSame('rule.prohibited_with', $result['errors']['field']);
    }
}
