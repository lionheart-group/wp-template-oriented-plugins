<?php

namespace TonkatsuPlugin\Tests\Unit\Structure;

use TonkatsuPlugin\Structure\OrganizationConfig;
use TonkatsuPlugin\Structure\SiteConfig;
use TonkatsuPlugin\Structure\SitemapConfig;
use TonkatsuPlugin\Tests\Unit\BaseTestCase;

class SiteConfigTest extends BaseTestCase
{
    public function testDefaults(): void
    {
        $config = new SiteConfig();

        $this->assertNull($config->siteName);
        $this->assertSame('|', $config->separator);
        $this->assertNull($config->organization);
        $this->assertInstanceOf(SitemapConfig::class, $config->sitemap);
        $this->assertTrue($config->sitemap->enabled);
    }

    public function testAcceptsAFullConfiguration(): void
    {
        $config = new SiteConfig(
            siteName: 'Example',
            separator: '-',
            defaultDescription: 'An example.',
            defaultOgImage: '/wp-content/themes/example/ogp.png',
            twitterSite: '@example_jp',
            locale: 'ja_JP',
            organization: new OrganizationConfig(name: 'Example Inc.'),
        );

        $this->assertSame('@example_jp', $config->twitterSite);
        $this->assertSame('Example Inc.', $config->organization?->name);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidArguments(): array
    {
        return [
            'empty site name'             => [['siteName' => '  ']],
            'empty separator'             => [['separator' => '']],
            'relative og image'           => [['defaultOgImage' => 'images/ogp.png']],
            'non-http og image'           => [['defaultOgImage' => 'ftp://example.com/ogp.png']],
            'twitter without @'           => [['twitterSite' => 'example']],
            'twitter with invalid chars'  => [['twitterSite' => '@exa-mple']],
            'locale without region'       => [['locale' => 'ja']],
            'locale with hyphen'          => [['locale' => 'ja-JP']],
        ];
    }

    /**
     * @dataProvider invalidArguments
     * @param array<string, mixed> $arguments
     */
    public function testRejectsInvalidArguments(array $arguments): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SiteConfig(...$arguments);
    }
}
