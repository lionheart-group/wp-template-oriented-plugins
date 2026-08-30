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

    /** @var array<string, array{reason: string, result: mixed}>|null */
    private static ?array $additions = null;

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
        self::$additions = $decoded['additions'] ?? [];
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
     * Cases added to the corpus after the fixture was frozen — a new rule
     * label, say. They have no "before" to restate, so they are recorded
     * separately from the deviations.
     *
     * @return array<string, array{reason: string, result: mixed}>
     */
    private static function additions(): array
    {
        self::loadOverrides();

        return self::$additions ?? [];
    }

    /**
     * The result this case must produce: the ledger's "after" when the case
     * has a recorded deviation, its "result" when the case is an addition,
     * otherwise the frozen expectation.
     */
    private static function contractFor(string $caseId): mixed
    {
        $overrides = self::overrides();
        if (array_key_exists($caseId, $overrides)) {
            return $overrides[$caseId]['after'];
        }

        $additions = self::additions();
        if (array_key_exists($caseId, $additions)) {
            return $additions[$caseId]['result'];
        }

        return self::expected()[$caseId];
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

        // Whether the case is frozen, deviated or added is checked by
        // testEveryCorpusCaseHasAContract; here we only compare against
        // whichever of those three applies.
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
            1434,
            self::overrides(),
            'The number of deliberate deviations changed. Review the new entries, then update this count.'
        );
    }

    /**
     * An addition must name a case that genuinely did NOT exist when the
     * fixture was frozen — otherwise it belongs in `overrides`, where its
     * "before" would be checked.
     */
    public function testAdditionsAreCasesTheFrozenFixtureNeverHad(): void
    {
        $expectedAll = self::expected();
        $corpus = Corpus::cases();

        foreach (self::additions() as $caseId => $addition) {
            $this->assertArrayNotHasKey(
                $caseId,
                $expectedAll,
                "Addition '{$caseId}' already exists in the frozen fixture — record it as an override instead."
            );
            $this->assertArrayHasKey($caseId, $corpus, "Addition '{$caseId}' is not a corpus case.");
            $this->assertIsString($addition['reason']);
            $this->assertNotSame('', $addition['reason'], "Addition '{$caseId}' has no reason.");
        }
    }

    /**
     * Every corpus case must be accounted for: frozen, deviated, or added.
     * Without this a new case could silently assert nothing.
     */
    public function testEveryCorpusCaseHasAContract(): void
    {
        $expectedAll = self::expected();
        $overrides = self::overrides();
        $additions = self::additions();

        $orphans = [];
        foreach (array_keys(Corpus::cases()) as $caseId) {
            if (
                array_key_exists($caseId, $expectedAll)
                || array_key_exists($caseId, $overrides)
                || array_key_exists($caseId, $additions)
            ) {
                continue;
            }
            $orphans[] = $caseId;
        }

        $this->assertSame(
            [],
            $orphans,
            'Corpus cases with no recorded contract. Add them to the "additions" section of the ledger.'
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

    /**
     * Nothing in the fixture may be silently dropped from the corpus: a
     * frozen expectation with no case to exercise it is dead weight that
     * hides a lost check.
     */
    public function testEveryFrozenCaseStillExistsInTheCorpus(): void
    {
        $corpusIds = array_keys(Corpus::cases());
        $missing = array_values(array_diff(array_keys(self::expected()), $corpusIds));

        $this->assertSame(
            [],
            $missing,
            'Cases present in the frozen fixture are no longer generated by Corpus.php. ' .
            'Removing coverage needs to be deliberate, not a side effect.'
        );
    }
}
