<?php

namespace TonkatsuPlugin\Tests\Unit\Helpers;

use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Structure\ArchiveConfig;
use TonkatsuPlugin\Structure\PageConfig;
use TonkatsuPlugin\Structure\RedirectConfig;
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

    public function testRegisterRedirectKeepsRegistrationOrder(): void
    {
        $first = new RedirectConfig(from: '/b/', to: '/c/');
        $second = new RedirectConfig(from: '/a/', to: '/c/');

        Seo::registerRedirect($first);
        Seo::registerRedirect($second);

        $this->assertSame([$first, $second], Seo::getRedirects());
    }

    public function testDuplicateRedirectDies(): void
    {
        Seo::registerRedirect(new RedirectConfig(from: '/old/', to: '/new/'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TONKATSU Redirect Registration Error');

        // Same source, spelled differently.
        Seo::registerRedirect(new RedirectConfig(from: 'old', to: '/other/'));
    }

    public function testSameSourceWithAnotherTypeIsNotADuplicate(): void
    {
        Seo::registerRedirect(new RedirectConfig(from: '/old/', to: '/new/'));
        Seo::registerRedirect(new RedirectConfig(from: '/old/', to: '/new/', type: RedirectConfig::TYPE_PREFIX));

        $this->assertCount(2, Seo::getRedirects());
    }

    public function testRegisterRedirectsAcceptsConfigsAndArrays(): void
    {
        $config = new RedirectConfig(from: '/a/', to: '/b/');

        Seo::registerRedirects([
            $config,
            ['from' => '/closed/', 'status' => 410],
            ['from' => '^news/(\d+)$', 'to' => '/news/$1/', 'type' => 'regex'],
        ]);

        $redirects = Seo::getRedirects();
        $this->assertCount(3, $redirects);
        $this->assertSame($config, $redirects[0]);
        $this->assertSame(410, $redirects[1]->status);
        $this->assertSame(RedirectConfig::TYPE_REGEX, $redirects[2]->type);
    }

    public function testRegisterRedirectsNamesTheEntryInErrors(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(esc_html("RedirectConfig 'redirect #2'"));

        Seo::registerRedirects([
            ['from' => '/a/', 'to' => '/b/'],
            ['from' => '/c/', 'to' => '/d/', 'code' => 302],
        ]);
    }

    public function testRegisterRedirectsRejectsOtherValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('redirect #1 must be a RedirectConfig or an array');

        Seo::registerRedirects(['/a/']);
    }
}
