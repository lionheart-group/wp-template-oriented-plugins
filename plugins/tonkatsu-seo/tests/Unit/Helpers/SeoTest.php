<?php

namespace TonkatsuPlugin\Tests\Unit\Helpers;

use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Structure\ArchiveConfig;
use TonkatsuPlugin\Structure\PageConfig;
use TonkatsuPlugin\Structure\SiteConfig;
use TonkatsuPlugin\Tests\Unit\BaseTestCase;

class SeoTest extends BaseTestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function paths(): array
    {
        return [
            'front (empty)'      => ['', ''],
            'front (slash)'      => ['/', ''],
            'plain'              => ['about', 'about'],
            'slashes'            => ['/company/about/', 'company/about'],
            'repeated slashes'   => ['//company///about/', 'company/about'],
            'query and fragment' => ['/about/?utm=x#top', 'about'],
            'full url'           => ['https://example.com/about/?a=1', 'about'],
            'percent-encoded'    => ['/%E4%BC%9A%E7%A4%BE/', '会社'],
            'whitespace'         => ['  /about/  ', 'about'],
        ];
    }

    /**
     * @dataProvider paths
     */
    public function testNormalizePath(string $input, string $expected): void
    {
        $this->assertSame($expected, Seo::normalizePath($input));
    }

    public function testGetSiteReturnsDefaultsUntilSet(): void
    {
        $this->assertFalse(Seo::hasSite());
        $this->assertSame('|', Seo::getSite()->separator);

        $site = new SiteConfig(separator: '-');
        Seo::setSite($site);

        $this->assertTrue(Seo::hasSite());
        $this->assertSame($site, Seo::getSite());
    }

    public function testRegisterPageIsFoundByAnyEquivalentPath(): void
    {
        $config = new PageConfig(title: 'About');
        Seo::registerPage('/company/about/', $config);

        $this->assertSame($config, Seo::getPage('company/about'));
        $this->assertSame($config, Seo::getPage('https://example.com/company/about/'));
        $this->assertNull(Seo::getPage('company'));
    }

    public function testFrontPageIsTheEmptyPath(): void
    {
        $config = new PageConfig(title: 'Home');
        Seo::registerPage('/', $config);

        $this->assertSame($config, Seo::getPage(''));
    }

    public function testDuplicatePageRegistrationDies(): void
    {
        Seo::registerPage('about', new PageConfig());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already registered');

        // Same page, spelled differently.
        Seo::registerPage('/about/', new PageConfig());
    }

    public function testNumericPathsSurviveAsKeys(): void
    {
        $config = new PageConfig(title: '2024');
        Seo::registerPage('2024', $config);

        $this->assertSame($config, Seo::getPage('/2024/'));
        $this->assertSame(['2024'], array_map('strval', array_keys(Seo::getPages())));
    }

    public function testRegisterPagesAcceptsConfigsAndArrays(): void
    {
        Seo::registerPages([
            'about'   => new PageConfig(title: 'About'),
            'thanks'  => ['noindex' => true],
        ]);

        $this->assertSame('About', Seo::getPage('about')?->title);
        $this->assertTrue(Seo::getPage('thanks')?->noindex);
    }

    public function testRegisterPagesRejectsUnknownKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(esc_html("PageConfig 'thanks'"));

        Seo::registerPages(['thanks' => ['noIndex' => true]]);
    }

    public function testRegisterPagesRejectsOtherValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Seo::registerPages(['about' => 'About']);
    }

    public function testArchivesAndTaxonomiesAreSeparateRegistries(): void
    {
        $archive = new ArchiveConfig(title: 'News');
        $taxonomy = new ArchiveConfig(noindex: true);

        Seo::registerArchive('news', $archive);
        Seo::registerTaxonomy('news', $taxonomy);

        $this->assertSame($archive, Seo::getArchive('news'));
        $this->assertSame($taxonomy, Seo::getTaxonomy('news'));
        $this->assertSame(['news' => $archive], Seo::getArchives());
        $this->assertSame(['news' => $taxonomy], Seo::getTaxonomies());
        $this->assertNull(Seo::getArchive('post'));
    }

    public function testDuplicateArchiveRegistrationDies(): void
    {
        Seo::registerArchive('news', new ArchiveConfig());

        $this->expectException(\RuntimeException::class);

        Seo::registerArchive('news', new ArchiveConfig());
    }

    public function testDuplicateTaxonomyRegistrationDies(): void
    {
        Seo::registerTaxonomy('category', new ArchiveConfig());

        $this->expectException(\RuntimeException::class);

        Seo::registerTaxonomy('category', new ArchiveConfig());
    }

    public function testEmptyPostTypeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Seo::registerArchive(' ', new ArchiveConfig());
    }
}
