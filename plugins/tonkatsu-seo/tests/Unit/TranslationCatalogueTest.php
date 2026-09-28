<?php

namespace ToroPlugin\Tests\Unit;

/**
 * Guards the translation catalogues against silent drift.
 *
 * Neither failure fails loudly at runtime — a missing translation just
 * renders English, and a stale .mo just serves yesterday's text:
 *
 *   1. A string is added to src/ but never to the .pot/.po.
 *   2. The .po is edited and the compiled .mo is not regenerated.
 */
class TranslationCatalogueTest extends BaseTestCase
{
    private const DOMAIN = 'template-oriented-rank-optimizer';

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
     * Every translatable string in src/ must have a msgid in the .pot, and
     * a translation in the ja .po.
     */
    public function testEveryTranslatableStringInSourceIsCatalogued(): void
    {
        $pot = file_get_contents(self::languagesDir() . '/' . self::DOMAIN . '.pot');
        $this->assertIsString($pot);

        $ja = toro_test_load_translations('ja');

        $found = 0;
        $missingPot = [];
        $missingJa = [];
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

                $relative = str_replace(dirname(__DIR__, 2) . '/', '', $file);

                if (!str_contains($pot, 'msgid "' . addcslashes($text, "\"\\") . '"')) {
                    $missingPot[$relative][] = $text;
                }
                if (!isset($ja[$text])) {
                    $missingJa[$relative][] = $text;
                }
            }
        }

        $this->assertSame([], $missingPot, 'Translatable strings in src/ with no msgid in the .pot.');
        $this->assertSame([], $missingJa, 'Translatable strings in src/ with no ja translation in the .po.');

        // A pattern that matches nothing would make the checks above pass no
        // matter what.
        $this->assertGreaterThan(
            30,
            $found,
            'Found almost no translatable strings in src/. The pattern has stopped matching, '
            . 'so this test is no longer checking anything.'
        );
    }

    public function testAdminStringsAreTranslated(): void
    {
        $GLOBALS['__toro_test_locale'] = 'ja';

        $this->assertSame('SEO設定 (TORO)', __('SEO Settings (TORO)', self::DOMAIN));
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

        $fresh = tempnam(sys_get_temp_dir(), 'toro-mo-');
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
}
