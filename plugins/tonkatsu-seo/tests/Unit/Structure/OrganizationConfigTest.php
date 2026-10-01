<?php

namespace TonkatsuPlugin\Tests\Unit\Structure;

use TonkatsuPlugin\Structure\OrganizationConfig;
use TonkatsuPlugin\Tests\Unit\BaseTestCase;

class OrganizationConfigTest extends BaseTestCase
{
    public function testAcceptsValidValues(): void
    {
        $config = new OrganizationConfig(
            name: '株式会社サンプル',
            url: 'https://example.com/',
            logo: '/logo.png',
            sameAs: ['https://x.com/example', 'https://ja.wikipedia.org/wiki/サンプル'],
        );

        $this->assertCount(2, $config->sameAs);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new OrganizationConfig(name: ' ');
    }

    public function testRejectsAnInvalidUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new OrganizationConfig(name: 'Example', url: 'example.com');
    }

    public function testRejectsAnInvalidLogo(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new OrganizationConfig(name: 'Example', logo: 'javascript:alert(1)');
    }

    /**
     * @return array<string, array{array<mixed>}>
     */
    public static function invalidSameAs(): array
    {
        return [
            'not a url'  => [['x.com/example']],
            'not string' => [[123]],
            'mixed'      => [['https://x.com/example', null]],
        ];
    }

    /**
     * @dataProvider invalidSameAs
     * @param array<mixed> $sameAs
     */
    public function testRejectsInvalidSameAs(array $sameAs): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new OrganizationConfig(name: 'Example', sameAs: $sameAs);
    }
}
