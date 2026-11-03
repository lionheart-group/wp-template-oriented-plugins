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
        $this->assertSame([], $config->extraUrls);
        $this->assertSame([], $config->extraUrlEntries());
    }

    public function testExtraUrlsAcceptStringsAndArrays(): void
    {
        $config = new SitemapConfig(extraUrls: [
            '/lp/campaign/',
            'https://example.com/lp/recruit.html',
            // Another host passes here; the provider leaves it out (the host is only known at runtime).
            'https://other.example.org/lp/',
            ['url' => '/lp/summer/', 'lastmod' => '2026-10-01'],
            ['url' => '/lp/winter/', 'lastmod' => '2026-10-01T09:30:00+09:00'],
            ['url' => '/lp/日本語/'],
        ]);

        $this->assertSame([
            ['url' => '/lp/campaign/', 'lastmod' => null],
            ['url' => 'https://example.com/lp/recruit.html', 'lastmod' => null],
            ['url' => 'https://other.example.org/lp/', 'lastmod' => null],
            ['url' => '/lp/summer/', 'lastmod' => '2026-10-01'],
            ['url' => '/lp/winter/', 'lastmod' => '2026-10-01T09:30:00+09:00'],
            ['url' => '/lp/日本語/', 'lastmod' => null],
        ], $config->extraUrlEntries());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function lastmodValues(): array
    {
        return [
            'date'                 => ['2026-10-01', true],
            'minutes with Z'       => ['2026-10-01T09:30Z', true],
            'seconds with offset'  => ['2026-10-01T09:30:15-05:00', true],
            'fraction'             => ['2026-10-01T09:30:15.25+09:00', true],
            'no time zone'         => ['2026-10-01T09:30:15', false],
            'offset without colon' => ['2026-10-01T09:30:15+0900', false],
            'space separator'      => ['2026-10-01 09:30:15+09:00', false],
            'impossible date'      => ['2026-02-30', false],
            'hour 24'              => ['2026-10-01T24:00Z', false],
            'slashes'              => ['2026/10/01', false],
        ];
    }

    /**
     * @dataProvider lastmodValues
     */
    public function testLastmodFormat(string $value, bool $valid): void
    {
        $this->assertSame($valid, SitemapConfig::isLastmod($value));
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
            'relative url'         => [['extraUrls' => ['lp/campaign/']]],
            'protocol-relative'    => [['extraUrls' => ['//example.com/lp/']]],
            'javascript url'       => [['extraUrls' => ['javascript:alert(1)']]],
            'fragment'             => [['extraUrls' => ['/lp/#form']]],
            'non-string url'       => [['extraUrls' => [123]]],
            'missing url key'      => [['extraUrls' => [['lastmod' => '2026-10-01']]]],
            'unknown key'          => [['extraUrls' => [['url' => '/lp/', 'priority' => '0.8']]]],
            'bad lastmod'          => [['extraUrls' => [['url' => '/lp/', 'lastmod' => '2026/10/01']]]],
            'non-string lastmod'   => [['extraUrls' => [['url' => '/lp/', 'lastmod' => 20261001]]]],
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
