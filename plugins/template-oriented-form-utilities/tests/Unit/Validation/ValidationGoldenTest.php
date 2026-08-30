<?php

namespace TofuPlugin\Tests\Unit\Validation;

use TofuPlugin\Tests\Unit\BaseTestCase;
use TofuPlugin\Tests\Unit\Validation\Fixtures\Corpus;
use TofuPlugin\Tests\Unit\Validation\Support\EngineProbe;

/**
 * Replays the golden corpus (tests/Unit/Validation/Fixtures/Corpus.php)
 * against whatever engine currently backs EngineProbe and asserts the
 * result matches the frozen expectations in
 * tests/Unit/Validation/Fixtures/expected.json.
 *
 * This is the regression net for the eventual somnambulist/validation
 * replacement: expected.json was generated once, while somnambulist was
 * still installed, via `php scripts/regenerate-validation-golden.php`. It
 * must never be regenerated again — a future engine swap only rewrites
 * EngineProbe's internals, and this test staying green (unmodified) across
 * that swap IS the proof of behavioural equivalence.
 */
class ValidationGoldenTest extends BaseTestCase
{
    /** @var array<string, array{fails?: bool, errors?: array<string,string>, throws?: string, message?: string}>|null */
    private static ?array $expected = null;

    private static function expected(): array
    {
        if (self::$expected === null) {
            $path = __DIR__ . '/Fixtures/expected.json';
            $json = file_get_contents($path);
            self::assertIsString($json, "Could not read golden fixture at {$path}");

            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($decoded);
            self::$expected = $decoded;
        }

        return self::$expected;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function caseIdProvider(): array
    {
        $ids = array_keys(Corpus::cases());
        $provider = [];
        foreach ($ids as $id) {
            $provider[$id] = [$id];
        }
        return $provider;
    }

    /**
     * @dataProvider caseIdProvider
     */
    public function testCaseMatchesGoldenExpectation(string $caseId): void
    {
        $cases = Corpus::cases();
        $this->assertArrayHasKey($caseId, $cases, 'Corpus case referenced by the data provider is missing.');
        $expectedAll = self::expected();
        $this->assertArrayHasKey(
            $caseId,
            $expectedAll,
            "No golden expectation recorded for '{$caseId}' — run scripts/regenerate-validation-golden.php " .
            'ONLY if this is a newly added corpus case, never to "fix" a failing existing one.'
        );

        $case = $cases[$caseId];
        $actual = EngineProbe::run(
            $case['data'],
            $case['rules'],
            $case['aliases'],
            $case['messages'],
            $case['locale'],
        );

        $this->assertSame($expectedAll[$caseId], $actual, "Case '{$caseId}' diverged from the golden expectation.");
    }

    /**
     * The corpus must exercise EVERY rule the engine registers — the whole
     * point of the frozen baseline is that no rule's behaviour can change
     * unobserved. If the engine gains or loses a rule, this fails loudly
     * rather than letting a blind spot open up silently.
     */
    public function testCorpusCoversEveryRuleTheEngineRegisters(): void
    {
        $expected = EngineProbe::registeredRuleNames();
        sort($expected);

        $this->assertSame(
            $expected,
            Corpus::coveredRuleNames(),
            'Corpus.php and the engine disagree about which rules exist. Add the missing rule(s) to ' .
            'Corpus.php and regenerate the golden fixture — but ONLY while the engine is unchanged.'
        );
    }

    public function testCorpusAndFixtureCoverTheSameCaseIds(): void
    {
        $corpusIds = array_keys(Corpus::cases());
        $fixtureIds = array_keys(self::expected());

        sort($corpusIds);
        sort($fixtureIds);

        $this->assertSame(
            $corpusIds,
            $fixtureIds,
            'Corpus.php and expected.json have drifted apart — regenerate the golden ' .
            'file (only while the current engine is still installed) if cases were intentionally added.'
        );
    }
}
