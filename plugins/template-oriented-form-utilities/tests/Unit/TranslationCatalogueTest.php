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

    /**
     * A call to one of WordPress's translation functions whose first
     * argument is a string literal, capturing that literal.
     *
     * Longest names lead the alternation so `esc_html__` is not consumed as
     * a bare `__`, and the lookbehind keeps `$obj->__(` and identifiers
     * ending in these names out.
     */
    private const TRANSLATION_CALL = '/(?<![\w$>])(?:esc_html__|esc_attr__|esc_html_e|esc_attr_e|__|_e|_x)\s*\(\s*(?P<quote>[\'"])(?P<text>(?:\\\\.|(?!\g{quote})[^\\\\])*)\g{quote}/';

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
     * Every user-facing string in src/ must go through __() or _x(), and
     * every one of those must have a msgid in the .pot.
     *
     * The catalogue checks above only cover Messages::all(). They said
     * nothing about the strings scattered through the rest of src/, which is
     * how 'reCAPTCHA token is missing.' and its Turnstile twin sat in
     * Models/Form.php as bare literals — shown to visitors, in English, on a
     * fully translated site, while every other bot-protection message
     * beside them was translatable.
     *
     * Only literal calls are checked. That is the same set `wp i18n
     * make-pot` can extract, so anything this test cannot see is something
     * translators would never receive either.
     */
    public function testEveryTranslatableStringInSourceIsInThePot(): void
    {
        $pot = file_get_contents(self::languagesDir() . '/' . self::DOMAIN . '.pot');
        $this->assertIsString($pot);

        $found = 0;
        $missing = [];
        foreach (self::sourceFiles() as $file) {
            $code = (string) file_get_contents($file);

            preg_match_all(self::TRANSLATION_CALL, $code, $matches, PREG_SET_ORDER);
            $found += count($matches);

            foreach ($matches as $match) {
                // Un-escape the PHP literal, then re-escape it the way a
                // .pot file writes a msgid.
                $text = $match['quote'] === '"'
                    ? stripcslashes($match['text'])
                    : str_replace(['\\\'', '\\\\'], ['\'', '\\'], $match['text']);

                if (!str_contains($pot, 'msgid "' . addcslashes($text, "\"\\") . '"')) {
                    $missing[str_replace(dirname(__DIR__, 2) . '/', '', $file)][] = $text;
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Translatable strings in src/ with no msgid in the .pot. Regenerate it with '
            . '`wp i18n make-pot . languages/' . self::DOMAIN . '.pot`, then add the '
            . 'translations to the .po and recompile the .mo.'
        );

        // A pattern that matches nothing would make the check above pass no
        // matter what — which is exactly what the first version of it did,
        // because \b never matches between the two underscores of `__(`.
        $this->assertGreaterThan(
            100,
            $found,
            'Found almost no translatable strings in src/. The pattern has stopped matching, '
            . 'so this test is no longer checking anything.'
        );
    }

    /**
     * @return list<string>
     */
    private static function sourceFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src', \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
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
