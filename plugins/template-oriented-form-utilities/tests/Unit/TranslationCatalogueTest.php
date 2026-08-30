<?php

namespace TofuPlugin\Tests\Unit;

use TofuPlugin\Validation\ValidatorFactory;

/**
 * Guards the translation catalogues against silent drift.
 *
 * Three things can rot independently and none of them fail loudly at
 * runtime — a missing translation just renders English, and a stale .mo just
 * serves yesterday's text:
 *
 *   1. A rule gains a message key that nobody adds to Messages::all().
 *   2. A string reaches Messages::all() but never the .po.
 *   3. The .po is edited and the compiled .mo is not regenerated.
 *
 * WordPress reads the .mo, so (3) is the one that actually reaches visitors.
 */
class TranslationCatalogueTest extends BaseTestCase
{
    private const DOMAIN = 'template-oriented-form-utilities';

    private static function languagesDir(): string
    {
        return dirname(__DIR__, 2) . '/languages';
    }

    /**
     * Every message key a rule can emit must exist in the catalogue.
     *
     * A key with no entry renders literally — the visitor is shown something
     * like "rule.prohibited_with" — which is exactly the bug that prompted
     * moving these strings into WordPress i18n.
     */
    public function testEveryRuleMessageKeyHasACatalogueEntry(): void
    {
        $catalogue = \TofuPlugin\Validation\Messages::all();

        $missing = [];
        foreach (ValidatorFactory::defaultRules() as $label => $rule) {
            if (!array_key_exists($rule->message(), $catalogue)) {
                $missing[$label] = $rule->message();
            }
        }

        $this->assertSame([], $missing, 'Rules whose message key is absent from Messages::all().');
    }

    /**
     * Every catalogue string must be translatable, i.e. present as a msgid
     * in the .pot. Otherwise translators never see it.
     */
    public function testEveryCatalogueStringIsInThePot(): void
    {
        $pot = file_get_contents(self::languagesDir() . '/' . self::DOMAIN . '.pot');
        $this->assertIsString($pot);

        $missing = [];
        foreach (\TofuPlugin\Validation\Messages::all() as $key => $text) {
            // Messages::all() runs through __(), which the test bootstrap
            // resolves against the ja catalogue; compare on the key's
            // English source instead by looking for any msgid at all.
            if (!str_contains($pot, 'msgid "' . addcslashes($text, "\"\\") . '"')
                && !str_contains($pot, self::sourceFor($key))) {
                $missing[$key] = $text;
            }
        }

        $this->assertSame([], $missing, 'Catalogue strings with no msgid in the .pot.');
    }

    /**
     * The committed .mo must be what the current .po compiles to.
     *
     * WordPress serves the .mo, so a stale one means the repository says one
     * thing and every visitor sees another.
     */
    public function testCompiledMoMatchesThePo(): void
    {
        $po = self::languagesDir() . '/' . self::DOMAIN . '-ja.po';
        $mo = self::languagesDir() . '/' . self::DOMAIN . '-ja.mo';

        $this->assertFileExists($po);
        $this->assertFileExists($mo);

        $msgfmt = trim((string) shell_exec('command -v msgfmt 2>/dev/null'));
        if ($msgfmt === '') {
            $this->markTestSkipped('msgfmt is not installed; cannot verify the compiled catalogue.');
        }

        $fresh = tempnam(sys_get_temp_dir(), 'tofu-mo-');
        try {
            exec(sprintf('%s -o %s %s 2>&1', escapeshellcmd($msgfmt), escapeshellarg($fresh), escapeshellarg($po)), $out, $status);
            $this->assertSame(0, $status, 'msgfmt failed: ' . implode("\n", $out));

            $this->assertSame(
                md5_file($fresh),
                md5_file($mo),
                "The committed .mo is stale. Regenerate it:\n"
                . "  msgfmt -o languages/" . self::DOMAIN . "-ja.mo languages/" . self::DOMAIN . "-ja.po"
            );
        } finally {
            @unlink($fresh);
        }
    }

    /**
     * Locate a key's English source in the .pot by its reference comment,
     * used when the runtime string has already been translated.
     */
    private static function sourceFor(string $key): string
    {
        return '#. Validation message: ' . $key . "\n";
    }
}
