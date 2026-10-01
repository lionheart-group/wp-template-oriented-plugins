<?php

namespace TofuPlugin\Tests\Unit\Validation;

use TofuPlugin\Tests\Unit\BaseTestCase;
use TofuPlugin\Tests\Unit\Validation\Support\EngineProbe;

/**
 * Pins `email` to exactly `filter_var($v, FILTER_VALIDATE_EMAIL) !== false`
 * — confirmed by running an adversarial corpus against both the current
 * engine and PHP's own filter with zero mismatches. This is the basis for
 * the Phase 1 decision to implement the in-house `email` rule as a direct
 * filter_var() call rather than WordPress's is_email() (which is stricter
 * in some edge cases and would be a silent behaviour change).
 *
 * @dataProvider-driven rather than hand-enumerated assertions so the
 * "matches filter_var" claim is verified afresh on whatever PHP version
 * runs the suite, not just asserted as a fact.
 */
class EmailRuleTest extends BaseTestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function addressProvider(): array
    {
        $addresses = [
            'a@b',
            'user@example.com',
            '"a b"@example.com',
            'a@example..com',
            'a@-example.com',
            'a@exa_mple.com',
            "!#\$%&*+-/=?^_`{|}~@example.com",
            'a@xn--fsq.com',
            'a@[IPv6:::1]',
            'a@127.0.0.1',
            'a@example.com.',
            str_repeat('a', 250) . '@example.com',
            'あ@example.com',
            "a@example.com\n",
            "a@example.com ",
            'plainaddress',
            '@missinglocal.com',
            'trailing.dot.@example.com',
        ];

        $provider = [];
        foreach ($addresses as $address) {
            $provider[$address] = [$address];
        }
        return $provider;
    }

    /**
     * @dataProvider addressProvider
     */
    public function testEmailRuleMatchesFilterVarExactly(string $address): void
    {
        $expectedValid = filter_var($address, \FILTER_VALIDATE_EMAIL) !== false;

        $result = EngineProbe::run(['email' => $address], ['email' => 'email']);

        $this->assertSame(
            $expectedValid,
            !$result['fails'],
            "email rule disagreed with filter_var() for: " . var_export($address, true)
        );
    }

    public function testEmailRuleDoesNotSupportUnicodeLocalParts(): void
    {
        $result = EngineProbe::run(['email' => 'あ@example.com'], ['email' => 'email']);
        $this->assertTrue($result['fails'], 'No FILTER_FLAG_EMAIL_UNICODE — a unicode local part must be rejected.');
    }

    public function testEmailRuleDoesNotTrimWhitespace(): void
    {
        $result = EngineProbe::run(['email' => "a@example.com\n"], ['email' => 'email']);
        $this->assertTrue($result['fails'], 'Trailing whitespace is not stripped before validation.');
    }
}
