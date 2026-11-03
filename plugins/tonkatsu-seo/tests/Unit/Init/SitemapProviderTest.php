<?php

namespace TonkatsuPlugin\Tests\Unit\Init;

use TonkatsuPlugin\Consts;
use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Init\Sitemap;
use TonkatsuPlugin\Init\SitemapProvider;
use TonkatsuPlugin\Structure\SiteConfig;
use TonkatsuPlugin\Structure\SitemapConfig;
use TonkatsuPlugin\Tests\Unit\BaseTestCase;

class SitemapProviderTest extends BaseTestCase
{
    /**
     * Temporary document root, removed after each test.
     */
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/tonkatsu-sitemap-' . bin2hex(random_bytes(4));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
        parent::tearDown();
    }

    private function removeTree(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $dir . '/' . $name;
            is_dir($path) && !is_link($path) ? $this->removeTree($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Create a file under $root with the given modification time.
     */
    private function file(string $relative, int $mtime = 1790000000): string
    {
        $path = $this->root . '/' . $relative;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, 'x');
        touch($path, $mtime);

        return (string) realpath($path);
    }

    private function useExtraUrls(array $extraUrls, array $excludeProviders = ['users']): void
    {
        Seo::setSite(new SiteConfig(sitemap: new SitemapConfig(
            excludeProviders: $excludeProviders,
            extraUrls: $extraUrls,
        )));
    }

    public function testResolveUrl(): void
    {
        $this->assertSame('https://example.com/lp/a/', SitemapProvider::resolveUrl('/lp/a/', 'https://example.com/'));
        // Relative to the host root, also when WordPress lives in a subdirectory.
        $this->assertSame('https://example.com/lp/a/', SitemapProvider::resolveUrl('/lp/a/', 'https://example.com/blog/'));
        $this->assertSame('http://localhost:8080/lp/', SitemapProvider::resolveUrl('/lp/', 'http://localhost:8080/'));
        $this->assertSame('https://example.com/x/', SitemapProvider::resolveUrl('https://example.com/x/', 'https://example.org/'));
    }

    public function testIsSameSite(): void
    {
        $this->assertTrue(SitemapProvider::isSameSite('https://example.com/lp/', 'https://example.com/'));
        $this->assertTrue(SitemapProvider::isSameSite('http://EXAMPLE.com/lp/', 'https://example.com/blog/'));
        $this->assertTrue(SitemapProvider::isSameSite('/lp/', 'https://example.com/'));
        $this->assertFalse(SitemapProvider::isSameSite('https://www.example.com/lp/', 'https://example.com/'));
        $this->assertFalse(SitemapProvider::isSameSite('https://example.com:8443/lp/', 'https://example.com/'));
    }

    public function testListedDropsOtherHostsAndDuplicates(): void
    {
        $this->assertSame(
            [
                ['loc' => 'https://example.com/lp/a/', 'lastmod' => null],
                ['loc' => 'https://example.com/lp/b.html', 'lastmod' => '2026-10-01'],
            ],
            SitemapProvider::listed([
                ['url' => '/lp/a/', 'lastmod' => null],
                ['url' => 'https://other.example.org/lp/', 'lastmod' => null],
                ['url' => 'https://example.com/lp/b.html', 'lastmod' => '2026-10-01'],
                ['url' => 'https://example.com/lp/a/', 'lastmod' => '2026-01-01'],
            ], 'https://example.com/')
        );
    }

    public function testPathFromHome(): void
    {
        $this->assertSame('/lp/a/', SitemapProvider::pathFromHome('https://example.com/lp/a/', 'https://example.com/'));
        $this->assertSame('/lp/a/', SitemapProvider::pathFromHome('https://example.com/blog/lp/a/', 'https://example.com/blog/'));
        $this->assertSame('/', SitemapProvider::pathFromHome('https://example.com/blog', 'https://example.com/blog/'));
        $this->assertNull(SitemapProvider::pathFromHome('https://example.com/lp/a/', 'https://example.com/blog/'));
        $this->assertNull(SitemapProvider::pathFromHome('https://example.com/blogger/', 'https://example.com/blog'));
        $this->assertSame('/lp/日本語/', SitemapProvider::pathFromHome('https://example.com/lp/%E6%97%A5%E6%9C%AC%E8%AA%9E/?a=1', 'https://example.com/'));
    }

    public function testRootDir(): void
    {
        $this->assertSame('/var/www/html/', SitemapProvider::rootDir('https://example.com', 'https://example.com', '/var/www/html/'));
        // WordPress in /wp/, the site at the root: the root is ABSPATH without wp/.
        $this->assertSame('/var/www/html/', SitemapProvider::rootDir('https://example.com', 'http://example.com/wp', '/var/www/html/wp/'));
        // Unrelated URLs, or an ABSPATH that does not end in the subdirectory: ABSPATH.
        $this->assertSame('/var/www/html/', SitemapProvider::rootDir('https://example.com/blog', 'https://example.com', '/var/www/html'));
        $this->assertSame('/srv/core/', SitemapProvider::rootDir('https://example.com', 'https://example.com/wp', '/srv/core/'));
    }

    public function testLocalFileLooksForIndexHtmlThenIndexPhp(): void
    {
        $php = $this->file('lp/a/index.php');
        $this->assertSame($php, SitemapProvider::localFile('/lp/a/', $this->root));

        $html = $this->file('lp/a/index.html');
        $this->assertSame($html, SitemapProvider::localFile('/lp/a/', $this->root));

        $this->assertSame($html, SitemapProvider::localFile('/lp/a/index.html', $this->root));
    }

    public function testLocalFileForAFileName(): void
    {
        $file = $this->file('lp/recruit.html');

        $this->assertSame($file, SitemapProvider::localFile('/lp/recruit.html', $this->root));
        $this->assertNull(SitemapProvider::localFile('/lp/missing.html', $this->root));
        // A directory without a trailing slash is not a file.
        $this->assertNull(SitemapProvider::localFile('/lp', $this->root));
    }

    public function testLocalFileStaysInsideTheRoot(): void
    {
        $inside = $this->root . '/site';
        mkdir($inside);
        $this->file('secret.html');
        $this->file('site/lp/index.html');

        $this->assertNull(SitemapProvider::localFile('/../secret.html', $inside));
        $this->assertNull(SitemapProvider::localFile('/lp/../../secret.html', $inside));
        $this->assertNull(SitemapProvider::localFile('/lp/..\\..\\secret.html', $inside));
        $this->assertNull(SitemapProvider::localFile("/lp/index.html\0", $inside));
        $this->assertNotNull(SitemapProvider::localFile('/lp/', $inside));

        // A symbolic link pointing outside the root is not followed out.
        if (@symlink($this->root . '/secret.html', $inside . '/link.html')) {
            $this->assertNull(SitemapProvider::localFile('/link.html', $inside));
        }

        $this->assertNull(SitemapProvider::localFile('/lp/', $this->root . '/missing'));
    }

    public function testLastmod(): void
    {
        $file = $this->file('lp/index.html', gmmktime(1, 2, 3, 10, 15, 2026));

        $this->assertSame('2026-10-15T01:02:03+00:00', SitemapProvider::lastmod(null, $file));
        // The written value wins over the file.
        $this->assertSame('2026-10-01', SitemapProvider::lastmod('2026-10-01', $file));
        $this->assertNull(SitemapProvider::lastmod(null, null));
        $this->assertNull(SitemapProvider::lastmod(null, $this->root . '/missing.html'));
    }

    public function testPaging(): void
    {
        $items = range(1, 5);

        $this->assertSame([1, 2], SitemapProvider::page($items, 1, 2));
        $this->assertSame([5], SitemapProvider::page($items, 3, 2));
        $this->assertSame([], SitemapProvider::page($items, 4, 2));
        $this->assertSame([], SitemapProvider::page($items, 0, 2));

        $this->assertSame(3, SitemapProvider::pageCount(5, 2));
        $this->assertSame(1, SitemapProvider::pageCount(2000, 2000));
        $this->assertSame(2, SitemapProvider::pageCount(2001, 2000));
        $this->assertSame(0, SitemapProvider::pageCount(0, 2000));
    }

    public function testDescribe(): void
    {
        $this->file('lp/a/index.html', gmmktime(0, 0, 0, 10, 1, 2026));

        $this->assertSame(
            [
                [
                    'url' => '/lp/a/', 'loc' => 'https://example.com/lp/a/', 'listed' => true,
                    'file' => (string) realpath($this->root . '/lp/a/index.html'), 'lastmod' => '2026-10-01T00:00:00+00:00',
                ],
                [
                    'url' => '/lp/b/', 'loc' => 'https://example.com/lp/b/', 'listed' => true,
                    'file' => null, 'lastmod' => '2026-09-01',
                ],
                [
                    'url' => 'https://other.example.org/', 'loc' => 'https://other.example.org/', 'listed' => false,
                    'file' => null, 'lastmod' => null,
                ],
            ],
            SitemapProvider::describe([
                ['url' => '/lp/a/', 'lastmod' => null],
                ['url' => '/lp/b/', 'lastmod' => '2026-09-01'],
                ['url' => 'https://other.example.org/', 'lastmod' => null],
            ], 'https://example.com/', $this->root)
        );
    }

    public function testUrlListAndPageCount(): void
    {
        $GLOBALS['__tonkatsu_test_options'] = ['home' => 'https://example.com', 'siteurl' => 'https://example.com'];
        add_filter('wp_sitemaps_max_urls', static fn () => 2);
        $this->useExtraUrls([
            '/lp/a/',
            ['url' => '/lp/b/', 'lastmod' => '2026-10-01'],
            'https://other.example.org/lp/',
            '/lp/c.html',
        ]);

        $provider = new SitemapProvider();

        $this->assertSame(2, $provider->get_max_num_pages());
        $this->assertSame(
            [['loc' => 'https://example.com/lp/a/'], ['loc' => 'https://example.com/lp/b/', 'lastmod' => '2026-10-01']],
            $provider->get_url_list(1)
        );
        $this->assertSame([['loc' => 'https://example.com/lp/c.html']], $provider->get_url_list(2));
        $this->assertSame([], $provider->get_url_list(3));
    }

    /**
     * @return \WP_Sitemaps
     */
    private function server(): \WP_Sitemaps
    {
        return wp_sitemaps_get_server();
    }

    public function testRegisteredWhenThereAreUrls(): void
    {
        Sitemap::register();
        $this->useExtraUrls(['/lp/a/']);

        Sitemap::registerStaticProvider($this->server());

        $this->assertInstanceOf(SitemapProvider::class, $this->server()->registry->get_provider(Consts::SITEMAP_STATIC_PROVIDER));
    }

    public function testNotRegisteredWithoutListedUrls(): void
    {
        Sitemap::register();
        $this->useExtraUrls(['https://other.example.org/lp/']);
        Sitemap::registerStaticProvider($this->server());
        $this->assertNull($this->server()->registry->get_provider(Consts::SITEMAP_STATIC_PROVIDER));

        Seo::setSite(new SiteConfig());
        Sitemap::registerStaticProvider($this->server());
        $this->assertNull($this->server()->registry->get_provider(Consts::SITEMAP_STATIC_PROVIDER));
    }

    public function testNotRegisteredWhenExcluded(): void
    {
        Sitemap::register();
        $this->useExtraUrls(['/lp/a/'], ['users', 'static']);

        Sitemap::registerStaticProvider($this->server());

        $this->assertNull($this->server()->registry->get_provider(Consts::SITEMAP_STATIC_PROVIDER));
    }

    public function testNotRegisteredWhenSitemapsAreOff(): void
    {
        Sitemap::register();
        Seo::setSite(new SiteConfig(sitemap: new SitemapConfig(enabled: false, extraUrls: ['/lp/a/'])));
        Sitemap::registerStaticProvider($this->server());
        $this->assertNull($this->server()->registry->get_provider(Consts::SITEMAP_STATIC_PROVIDER));

        // Search engines discouraged.
        $this->useExtraUrls(['/lp/a/']);
        $GLOBALS['__tonkatsu_test_options']['blog_public'] = '0';
        Sitemap::registerStaticProvider($this->server());
        $this->assertNull($this->server()->registry->get_provider(Consts::SITEMAP_STATIC_PROVIDER));
    }

    public function testRegisterHooksTheProviderOnSitemapsInit(): void
    {
        Sitemap::register();

        $this->assertSame(10, has_action('wp_sitemaps_init', [Sitemap::class, 'registerStaticProvider']));
    }
}
