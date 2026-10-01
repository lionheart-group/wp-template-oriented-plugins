<?php

namespace TonkatsuPlugin\Tests\Unit\Init;

use TonkatsuPlugin\Consts;
use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Init\Sitemap;
use TonkatsuPlugin\Structure\SiteConfig;
use TonkatsuPlugin\Structure\SitemapConfig;
use TonkatsuPlugin\Tests\Unit\BaseTestCase;

class SitemapTest extends BaseTestCase
{
    private function useSitemap(SitemapConfig $sitemap): void
    {
        Seo::setSite(new SiteConfig(sitemap: $sitemap));
    }

    public function testEnabled(): void
    {
        $this->assertTrue(Sitemap::filterEnabled(true));
        // A site that is not public stays without sitemaps.
        $this->assertFalse(Sitemap::filterEnabled(false));

        $this->useSitemap(new SitemapConfig(enabled: false));
        $this->assertFalse(Sitemap::filterEnabled(true));
    }

    public function testUsersProviderIsRemovedByDefault(): void
    {
        $provider = new \stdClass();

        $this->assertFalse(Sitemap::filterProvider($provider, 'users'));
        $this->assertSame($provider, Sitemap::filterProvider($provider, 'posts'));
    }

    public function testExcludedProviders(): void
    {
        $this->useSitemap(new SitemapConfig(excludeProviders: ['taxonomies']));
        $provider = new \stdClass();

        $this->assertFalse(Sitemap::filterProvider($provider, 'taxonomies'));
        $this->assertSame($provider, Sitemap::filterProvider($provider, 'users'));
    }

    public function testExcludedPostTypesAndTaxonomies(): void
    {
        $this->useSitemap(new SitemapConfig(excludePostTypes: ['attachment', 'news'], excludeTaxonomies: ['post_tag']));

        $this->assertSame(
            ['post', 'page'],
            array_keys(Sitemap::filterPostTypes(['post' => 1, 'page' => 2, 'news' => 3]))
        );
        $this->assertSame(['category'], array_keys(Sitemap::filterTaxonomies(['category' => 1, 'post_tag' => 2])));
        $this->assertSame('broken', Sitemap::filterPostTypes('broken'));
    }

    /**
     * Install a stand-in for core's sitemap server whose posts provider
     * returns $entries, and record the calls it receives.
     *
     * @param list<array<string, string>> $entries
     * @param array<int, array{int, string}> $calls
     */
    private function fakeProvider(array $entries, array &$calls): void
    {
        wp_sitemaps_get_server()->registry->providers['posts'] = new class ($entries, $calls) extends \WP_Sitemaps_Provider {
            /** @param list<array<string, string>> $entries */
            public function __construct(private array $entries, private array &$calls)
            {
            }

            /** @return list<array<string, string>> */
            public function get_url_list($page_num, $object_subtype = ''): array
            {
                $this->calls[] = [$page_num, $object_subtype];

                // Core applies wp_sitemaps_posts_pre_url_list inside get_url_list();
                // calling back in must not recurse.
                if (Sitemap::filterPreUrlList(null, $object_subtype, $page_num) !== null) {
                    throw new \LogicException('filterPreUrlList() re-entered.');
                }

                return $this->entries;
            }
        };
    }

    public function testCoreBuildsTheListWhenNothingIsExcluded(): void
    {
        $calls = [];
        $this->fakeProvider([['loc' => 'https://example.com/a/']], $calls);

        $this->assertNull(Sitemap::filterPreUrlList(null, 'page', 1));
        $this->assertSame([], $calls);
    }

    public function testExcludedPostsAreRemovedFromCoresList(): void
    {
        $received = null;
        add_filter('tonkatsu_sitemap_excluded_post_ids', function ($ids, $postType) use (&$received) {
            $received = $postType;
            return array_merge($ids, [12, '34', 12]);
        }, 10, 2);
        $GLOBALS['__tonkatsu_test_permalinks'] = [
            12 => 'https://example.com/contact/confirm/',
            34 => 'https://example.com/contact/result/',
        ];

        $calls = [];
        $this->fakeProvider([
            ['loc' => 'https://example.com/'],
            ['loc' => 'https://example.com/contact/'],
            ['loc' => 'https://example.com/contact/confirm/', 'lastmod' => '2026-01-01T00:00:00+00:00'],
            ['loc' => 'https://example.com/contact/result/'],
        ], $calls);

        $this->assertSame(
            [['loc' => 'https://example.com/'], ['loc' => 'https://example.com/contact/']],
            Sitemap::filterPreUrlList(null, 'page', 2)
        );
        $this->assertSame('page', $received);
        $this->assertSame([[2, 'page']], $calls);
    }

    public function testAListBuiltByAnotherCallbackIsLeftAlone(): void
    {
        add_filter('tonkatsu_sitemap_excluded_post_ids', fn () => [12]);
        $calls = [];
        $this->fakeProvider([], $calls);

        $list = [['loc' => 'https://example.com/other/']];
        $this->assertSame($list, Sitemap::filterPreUrlList($list, 'page', 1));
        $this->assertSame([], $calls);
    }

    public function testWithoutAProviderCoreIsLeftToBuildTheList(): void
    {
        add_filter('tonkatsu_sitemap_excluded_post_ids', fn () => [12]);

        $this->assertNull(Sitemap::filterPreUrlList(null, 'page', 1));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableIdLists(): array
    {
        return [
            'string'  => ['12,34'],
            'null'    => [null],
            'invalid' => [[0, -1, 'abc', 1.5, [3], null]],
        ];
    }

    /**
     * @dataProvider unusableIdLists
     */
    public function testUnusableExcludedIdsAreIgnored(mixed $return): void
    {
        add_filter('tonkatsu_sitemap_excluded_post_ids', fn () => $return);

        $this->assertSame([], Sitemap::excludedPostIds('page'));
    }

    public function testCoreServerIsMovedAfterTheThemesInit(): void
    {
        add_action('init', 'wp_sitemaps_get_server');

        Sitemap::deferCoreServer();

        $this->assertFalse(has_action('init', 'wp_sitemaps_get_server'));
        $this->assertArrayHasKey(Consts::SITEMAP_INIT_PRIORITY, $GLOBALS['__tonkatsu_hooks']['init']);
    }

    public function testCoreServerIsLeftAloneWhenAlreadyRemoved(): void
    {
        Sitemap::deferCoreServer();

        $this->assertArrayNotHasKey('init', $GLOBALS['__tonkatsu_hooks']);
    }
}
