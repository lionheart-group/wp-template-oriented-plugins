<?php

namespace TofuPlugin\Tests\Unit\Validation;

use TofuPlugin\Tests\Unit\BaseTestCase;
use TofuPlugin\Tests\Unit\Validation\Fixtures\Corpus;
use TofuPlugin\Tests\Unit\Validation\Support\EngineProbe;

/**
 * Replays the golden corpus (tests/Unit/Validation/Fixtures/Corpus.php)
 * against whatever engine currently backs EngineProbe.
 *
 * `expected.json` was captured once, against somnambulist/validation, and is
 * FROZEN. It is never regenerated after an engine swap — doing so would
 * launder exactly the behaviour changes it exists to expose.
 *
 * Deliberate changes are instead recorded, one case at a time and with a
 * written reason, in `expected-overrides.json`. Each entry restates the
 * frozen "before" alongside its new "after", and this test verifies the
 * "before" still matches the fixture — so an entry that no longer describes
 * reality fails loudly instead of rotting. The pull request diff on that
 * ledger IS the list of intentional behaviour changes.
 */
class ValidationGoldenTest extends BaseTestCase
{
    /** @var array<string, array{fails?: bool, errors?: array<string,string>, throws?: string, message?: string}>|null */
    private static ?array $expected = null;

    /** @var array<string, array{reason: string, before: mixed, after: mixed}>|null */
    private static ?array $overrides = null;

    /** @var array<string, string>|null */
    private static ?array $reasons = null;

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

    private static function loadOverrides(): void
    {
        if (self::$overrides !== null) {
            return;
        }

        $path = __DIR__ . '/Fixtures/expected-overrides.json';
        $json = file_get_contents($path);
        self::assertIsString($json, "Could not read override ledger at {$path}");

        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        self::$overrides = $decoded['overrides'] ?? [];
        self::$reasons = $decoded['_reasons'] ?? [];
    }

    /**
     * @return array<string, array{reason: string, before: mixed, after: mixed}>
     */
    private static function overrides(): array
    {
        self::loadOverrides();

        return self::$overrides ?? [];
    }

    /**
     * The result this case must produce: the ledger's "after" when the case
     * has a recorded deviation, otherwise the frozen expectation.
     */
    private static function contractFor(string $caseId): mixed
    {
        $overrides = self::overrides();

        return array_key_exists($caseId, $overrides)
            ? $overrides[$caseId]['after']
            : self::expected()[$caseId];
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

        $this->assertSame(
            self::contractFor($caseId),
            $actual,
            "Case '{$caseId}' diverged. If the change is intentional, record it in " .
            'expected-overrides.json with a reason — never by editing expected.json.'
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function overrideIdProvider(): array
    {
        $provider = [];
        foreach (array_keys(self::overrides()) as $id) {
            $provider[$id] = [$id];
        }

        return $provider;
    }

    /**
     * Each ledger entry must still describe the frozen fixture it claims to
     * override. This is what stops the ledger drifting into fiction: an
     * entry whose "before" no longer matches expected.json is either stale
     * or was never true.
     *
     * @dataProvider overrideIdProvider
     */
    public function testOverrideRestatesTheFrozenExpectationItReplaces(string $caseId): void
    {
        $override = self::overrides()[$caseId];
        $expectedAll = self::expected();

        $this->assertArrayHasKey(
            $caseId,
            $expectedAll,
            "Override '{$caseId}' names a case that is not in the frozen fixture."
        );
        $this->assertSame(
            $expectedAll[$caseId],
            $override['before'],
            "Override '{$caseId}' no longer matches the frozen expectation it claims to replace."
        );
        $this->assertNotSame(
            $override['before'],
            $override['after'],
            "Override '{$caseId}' records no actual change."
        );
        $this->assertIsString($override['reason']);
        $this->assertNotSame('', $override['reason'], "Override '{$caseId}' has no reason.");
        $this->assertArrayHasKey(
            $override['reason'],
            self::$reasons ?? [],
            "Override '{$caseId}' cites an undocumented reason code."
        );
    }

    /**
     * Pins how many deviations exist, so another one cannot be appended
     * without someone consciously updating this number.
     */
    public function testOverrideLedgerHasTheExpectedSize(): void
    {
        $this->assertCount(
            369,
            self::overrides(),
            'The number of deliberate deviations changed. Review the new entries, then update this count.'
        );
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
