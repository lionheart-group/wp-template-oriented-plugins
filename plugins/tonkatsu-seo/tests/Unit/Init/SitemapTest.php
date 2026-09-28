<?php

namespace ToroPlugin\Tests\Unit\Init;

use ToroPlugin\Consts;
use ToroPlugin\Helpers\Seo;
use ToroPlugin\Init\Sitemap;
use ToroPlugin\Structure\SiteConfig;
use ToroPlugin\Structure\SitemapConfig;
use ToroPlugin\Tests\Unit\BaseTestCase;

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

    public function testQueryArgsAreUntouchedWithNothingToExclude(): void
    {
        $args = ['post_type' => 'page'];

        $this->assertSame($args, Sitemap::filterPostsQueryArgs($args, 'page'));
    }

    public function testExcludedIdsFilterIsMergedIntoTheQuery(): void
    {
        $received = null;
        add_filter('toro_sitemap_excluded_post_ids', function ($ids, $postType) use (&$received) {
            $received = $postType;
            return array_merge($ids, [12, '34', 12]);
        }, 10, 2);

        $args = Sitemap::filterPostsQueryArgs(['post_type' => 'page', 'post__not_in' => [5]], 'page');

        $this->assertSame('page', $received);
        $this->assertSame([5, 12, 34], $args['post__not_in']);
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
        add_filter('toro_sitemap_excluded_post_ids', fn () => $return);

        $this->assertSame([], Sitemap::excludedPostIds('page'));
    }

    public function testCoreServerIsMovedAfterTheThemesInit(): void
    {
        add_action('init', 'wp_sitemaps_get_server');

        Sitemap::deferCoreServer();

        $this->assertFalse(has_action('init', 'wp_sitemaps_get_server'));
        $this->assertArrayHasKey(Consts::SITEMAP_INIT_PRIORITY, $GLOBALS['__toro_hooks']['init']);
    }

    public function testCoreServerIsLeftAloneWhenAlreadyRemoved(): void
    {
        Sitemap::deferCoreServer();

        $this->assertArrayNotHasKey('init', $GLOBALS['__toro_hooks']);
    }
}
