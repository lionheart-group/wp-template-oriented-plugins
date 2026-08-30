<?php

namespace TofuPlugin\Tests\Unit\Validation;

use TofuPlugin\Tests\Unit\BaseTestCase;
use TofuPlugin\Validation\ValidatorFactory;

/**
 * Keeps the rule reference in docs/settings/validationconfig.md honest.
 *
 * That page is now the only description of what rules exist — it used to
 * link out to the library's README, which went away with the library. Prose
 * has no compiler, and this repository has already shipped documentation
 * advertising rules that were removed two versions earlier, so the list is
 * checked rather than trusted.
 *
 * Only membership is verified. Wording, grouping and the notes column are
 * for a human to write.
 */
class DocumentedRulesTest extends BaseTestCase
{
    private const DOC = 'docs/settings/validationconfig.md';

    /**
     * Rules deliberately left out of the reference table because a
     * dedicated section covers them better than a table row would.
     *
     * @var string[]
     */
    private const DOCUMENTED_ELSEWHERE = [
        // Registered so existing forms keep working; the Presence section
        // names it as required_file's former spelling rather than listing
        // it separately, which would read as two different rules.
        'custom_required_file',
        // Named in their primary row as "also registered as …".
        'defaults',
        'number',
        'matches',
    ];

    private function documentation(): string
    {
        $path = dirname(__DIR__, 3) . '/' . self::DOC;
        $contents = file_get_contents($path);
        $this->assertIsString($contents, "Could not read " . self::DOC);

        return $contents;
    }

    public function testEveryRegisteredRuleIsDocumented(): void
    {
        $doc = $this->documentation();

        $undocumented = [];
        foreach (array_keys(ValidatorFactory::defaultRules()) as $label) {
            if (in_array($label, self::DOCUMENTED_ELSEWHERE, true)) {
                continue;
            }

            // Rules appear as `label` in a table cell, sometimes several to
            // a row separated by a middot.
            if (!str_contains($doc, '`' . $label . '`')) {
                $undocumented[] = $label;
            }
        }

        $this->assertSame(
            [],
            $undocumented,
            'Rules the engine registers but ' . self::DOC . ' never mentions. Add them to the '
            . 'reference table, or to DOCUMENTED_ELSEWHERE if another section covers them.'
        );
    }

    /**
     * The inverse, and the one that actually bit this project before: the
     * page must not advertise a rule that no longer exists.
     */
    public function testEveryRuleNameMentionedInTheReferenceStillExists(): void
    {
        $doc = $this->documentation();
        $registered = array_keys(ValidatorFactory::defaultRules());

        // Only scan the reference tables; the prose above them mentions
        // parameter names and example values in backticks too.
        $start = strpos($doc, '## Available rules');
        $this->assertIsInt($start, 'The reference section is missing from ' . self::DOC);
        $reference = substr($doc, $start);

        preg_match_all('/^\| `([a-z_]+)`/m', $reference, $matches);

        $unknown = array_values(array_unique(array_filter(
            $matches[1],
            static fn (string $label): bool => !in_array($label, $registered, true)
        )));

        $this->assertSame(
            [],
            $unknown,
            'Rule names documented in ' . self::DOC . ' that the engine does not register. '
            . 'Either the rule was removed and the docs were not, or the name is a typo.'
        );
    }
}
