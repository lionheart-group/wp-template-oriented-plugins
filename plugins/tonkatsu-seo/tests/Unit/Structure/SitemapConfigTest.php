<?php

namespace TonkatsuPlugin\Tests\Unit\Structure;

use TonkatsuPlugin\Structure\SitemapConfig;
use TonkatsuPlugin\Tests\Unit\BaseTestCase;

class SitemapConfigTest extends BaseTestCase
{
    public function testExcludesTheUsersProviderByDefault(): void
    {
        $config = new SitemapConfig();

        $this->assertTrue($config->enabled);
        $this->assertSame(['users'], $config->excludeProviders);
        $this->assertSame([], $config->excludePostTypes);
        $this->assertSame([], $config->excludeTaxonomies);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidArguments(): array
    {
        return [
            'non-string provider'  => [['excludeProviders' => [1]]],
            'empty post type'      => [['excludePostTypes' => ['']]],
            'null taxonomy'        => [['excludeTaxonomies' => [null]]],
        ];
    }

    /**
     * @dataProvider invalidArguments
     * @param array<string, mixed> $arguments
     */
    public function testRejectsInvalidEntries(array $arguments): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SitemapConfig(...$arguments);
    }
}
