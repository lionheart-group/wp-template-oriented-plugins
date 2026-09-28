<?php

namespace ToroPlugin\Tests\Unit\Models;

use ToroPlugin\Helpers\Seo;
use ToroPlugin\Models\Context;
use ToroPlugin\Models\Resolver;
use ToroPlugin\Structure\ArchiveConfig;
use ToroPlugin\Structure\PageConfig;
use ToroPlugin\Structure\SiteConfig;
use ToroPlugin\Tests\Unit\BaseTestCase;

class ResolverTest extends BaseTestCase
{
    private function singular(array $overrides = []): Context
    {
        return new Context(...array_merge([
            'type'        => Context::TYPE_SINGULAR,
            'path'        => 'about',
            'url'         => 'https://example.com/about/',
            'object'      => $this->makePost(['ID' => 7]),
            'postType'    => 'page',
            'title'       => 'About (post title)',
            'description' => 'From the excerpt.',
            'image'       => 'https://example.com/featured.jpg',
        ], $overrides));
    }

    private function newsArchive(): Context
    {
        return new Context(
            type: Context::TYPE_POST_TYPE_ARCHIVE,
            path: 'news',
            url: 'https://example.com/news/',
            postType: 'news',
            title: 'News',
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function filterReturns(mixed $values): void
    {
        add_filter('toro_post_values', fn () => $values);
    }

    // ------------------------------------------------------------------
    // Priority order
    // ------------------------------------------------------------------

    public function testTitlePriority(): void
    {
        // Nothing configured: keep WordPress's title.
        $this->assertNull(Resolver::fromContext($this->singular())->title());

        Seo::registerPage('about', new PageConfig(title: 'From PageConfig'));
        $this->assertSame('From PageConfig', Resolver::fromContext($this->singular())->title());

        $this->filterReturns(['title' => 'From filter']);
        $this->assertSame('From filter', Resolver::fromContext($this->singular())->title());
    }

    public function testArchiveTitleIsBelowPageConfig(): void
    {
        Seo::registerArchive('news', new ArchiveConfig(title: 'From ArchiveConfig'));
        $this->assertSame('From ArchiveConfig', Resolver::fromContext($this->newsArchive())->title());

        Seo::registerPage('news', new PageConfig(title: 'From PageConfig'));
        $this->assertSame('From PageConfig', Resolver::fromContext($this->newsArchive())->title());
    }

    public function testDescriptionPriority(): void
    {
        Seo::setSite(new SiteConfig(defaultDescription: 'Site default.'));

        $context = $this->singular(['description' => null]);
        $this->assertSame('Site default.', Resolver::fromContext($context)->description());

        $this->assertSame('From the excerpt.', Resolver::fromContext($this->singular())->description());

        Seo::registerPage('about', new PageConfig(description: 'From PageConfig.'));
        $this->assertSame('From PageConfig.', Resolver::fromContext($this->singular())->description());

        $this->filterReturns(['description' => 'From filter.']);
        $this->assertSame('From filter.', Resolver::fromContext($this->singular())->description());
    }

    public function testTaxonomyDescriptionIsAboveTheTermDescription(): void
    {
        $context = new Context(
            type: Context::TYPE_TAXONOMY,
            path: 'category/info',
            taxonomy: 'category',
            description: 'Term description.',
        );

        $this->assertSame('Term description.', Resolver::fromContext($context)->description());

        Seo::registerTaxonomy('category', new ArchiveConfig(description: 'From ArchiveConfig.'));
        $this->assertSame('From ArchiveConfig.', Resolver::fromContext($context)->description());
    }

    public function testCanonicalPriority(): void
    {
        $this->assertNull(Resolver::fromContext($this->singular())->canonicalOverride());
        $this->assertSame('https://example.com/about/', Resolver::fromContext($this->singular())->canonical());

        Seo::registerPage('about', new PageConfig(canonical: '/company/about/'));
        $this->assertSame('https://example.com/company/about/', Resolver::fromContext($this->singular())->canonical());

        $this->filterReturns(['canonical' => 'https://other.example.com/about/']);
        $this->assertSame('https://other.example.com/about/', Resolver::fromContext($this->singular())->canonical());
    }

    public function testOgImagePriority(): void
    {
        Seo::setSite(new SiteConfig(defaultOgImage: '/default.png'));

        $this->assertSame('https://example.com/default.png', Resolver::fromContext($this->singular(['image' => null]))->ogImage());
        $this->assertSame('https://example.com/featured.jpg', Resolver::fromContext($this->singular())->ogImage());

        Seo::registerPage('about', new PageConfig(ogImage: 'https://example.com/page.png'));
        $this->assertSame('https://example.com/page.png', Resolver::fromContext($this->singular())->ogImage());

        $this->filterReturns(['og_image' => 'https://example.com/filter.png']);
        $this->assertSame('https://example.com/filter.png', Resolver::fromContext($this->singular())->ogImage());
    }

    public function testEmptyPageValuesDoNotShadowLowerLevels(): void
    {
        Seo::setSite(new SiteConfig(defaultDescription: 'Site default.'));
        Seo::registerPage('about', new PageConfig(title: '', description: '  '));

        $resolver = Resolver::fromContext($this->singular(['description' => null]));

        $this->assertNull($resolver->title());
        $this->assertSame('Site default.', $resolver->description());
    }

    // ------------------------------------------------------------------
    // Robots
    // ------------------------------------------------------------------

    public function testIndexableByDefault(): void
    {
        $resolver = Resolver::fromContext($this->singular());

        $this->assertFalse($resolver->noindex());
        $this->assertFalse($resolver->nofollow());
    }

    public function testPageConfigRobots(): void
    {
        Seo::registerPage('about', new PageConfig(noindex: true, nofollow: true));
        $resolver = Resolver::fromContext($this->singular());

        $this->assertTrue($resolver->noindex());
        $this->assertTrue($resolver->nofollow());
    }

    public function testFilterCanAddNoindex(): void
    {
        $this->filterReturns(['noindex' => true, 'nofollow' => true]);
        $resolver = Resolver::fromContext($this->singular());

        $this->assertTrue($resolver->noindex());
        $this->assertTrue($resolver->nofollow());
    }

    /**
     * false is an empty value, so it cannot switch off a noindex configured
     * below it.
     */
    public function testFilterFalseDoesNotOverrideAConfiguredNoindex(): void
    {
        Seo::registerPage('about', new PageConfig(noindex: true));
        $this->filterReturns(['noindex' => false]);

        $this->assertTrue(Resolver::fromContext($this->singular())->noindex());
    }

    public function testArchiveNoindex(): void
    {
        Seo::registerArchive('news', new ArchiveConfig(noindex: true));

        $this->assertTrue(Resolver::fromContext($this->newsArchive())->noindex());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unindexedTypes(): array
    {
        return [
            'search' => [Context::TYPE_SEARCH],
            '404'    => [Context::TYPE_404],
        ];
    }

    /**
     * @dataProvider unindexedTypes
     */
    public function testSearchAnd404AreNoindexWithoutACanonical(string $type): void
    {
        $resolver = Resolver::fromContext(new Context(type: $type, path: 'anything', url: 'https://example.com/anything/'));

        $this->assertTrue($resolver->noindex());
        $this->assertNull($resolver->canonical());
    }

    /**
     * A 404 at a registered path must not pick up that page's values: the
     * page it describes is exactly what does not exist.
     */
    public function testSearchAnd404IgnoreRegisteredPages(): void
    {
        Seo::registerPage('gone', new PageConfig(title: 'Gone'));

        $this->assertNull(Resolver::fromContext(new Context(type: Context::TYPE_404, path: 'gone'))->title());
    }

    // ------------------------------------------------------------------
    // toro_post_values: fallbacks
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableFilterReturns(): array
    {
        return [
            'null'   => [null],
            'string' => ['Title'],
            'true'   => [true],
            'object' => [new \stdClass()],
        ];
    }

    /**
     * @dataProvider unusableFilterReturns
     */
    public function testUnusableFilterReturnFallsBackToTheConfiguration(mixed $return): void
    {
        Seo::registerPage('about', new PageConfig(title: 'From PageConfig', noindex: true));
        $this->filterReturns($return);

        $resolver = Resolver::fromContext($this->singular());

        $this->assertSame([], $resolver->postValues);
        $this->assertSame('From PageConfig', $resolver->title());
        $this->assertTrue($resolver->noindex());
    }

    public function testWronglyTypedKeysAreIgnoredIndividually(): void
    {
        Seo::registerPage('about', new PageConfig(title: 'From PageConfig', canonical: '/about/'));
        $this->filterReturns([
            'title'       => 123,
            'description' => 'Kept.',
            'canonical'   => 'not a url',
            'og_image'    => ['https://example.com/a.png'],
            'noindex'     => 'yes',
            'nofollow'    => 1,
            'unknown'     => 'ignored',
        ]);

        $resolver = Resolver::fromContext($this->singular());

        $this->assertSame(['description' => 'Kept.'], $resolver->postValues);
        $this->assertSame('From PageConfig', $resolver->title());
        $this->assertSame('https://example.com/about/', $resolver->canonical());
        $this->assertFalse($resolver->noindex());
        $this->assertFalse($resolver->nofollow());
    }

    public function testFilterReceivesTheQueriedPost(): void
    {
        $received = null;
        add_filter('toro_post_values', function ($values, $post) use (&$received) {
            $received = $post;
            return $values;
        }, 10, 2);

        $context = $this->singular();
        Resolver::fromContext($context);

        $this->assertSame($context->object, $received);
    }

    public function testFilterDoesNotRunWithoutAPost(): void
    {
        $calls = 0;
        add_filter('toro_post_values', function ($values) use (&$calls) {
            $calls++;
            return ['title' => 'Should not apply'];
        });

        $resolver = Resolver::fromContext($this->newsArchive());

        $this->assertSame(0, $calls);
        $this->assertNull($resolver->title());
    }

    // ------------------------------------------------------------------
    // Other values
    // ------------------------------------------------------------------

    public function testThePostsPageUsesThePostArchiveConfig(): void
    {
        Seo::registerArchive('post', new ArchiveConfig(title: 'Blog'));

        $context = new Context(type: Context::TYPE_HOME, path: 'blog', object: $this->makePost(['ID' => 3]));

        $this->assertSame('Blog', Resolver::fromContext($context)->title());
    }

    public function testSiteNameFallsBackToTheBlogName(): void
    {
        $this->assertSame('Example Site', Resolver::fromContext($this->singular())->siteName());

        Seo::setSite(new SiteConfig(siteName: 'Configured'));
        $this->assertSame('Configured', Resolver::fromContext($this->singular())->siteName());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function locales(): array
    {
        return [
            'en_US'        => ['en_US', 'en_US'],
            'ja'           => ['ja', 'ja_JP'],
            'de_DE_formal' => ['de_DE_formal', 'de_DE'],
            'unmapped'     => ['eo', 'eo'],
        ];
    }

    /**
     * @dataProvider locales
     */
    public function testLocaleIsDerivedFromWordPress(string $wpLocale, string $expected): void
    {
        $GLOBALS['__toro_test_locale'] = $wpLocale;

        $this->assertSame($expected, Resolver::fromContext($this->singular())->locale());
    }

    public function testConfiguredLocaleWins(): void
    {
        $GLOBALS['__toro_test_locale'] = 'en_US';
        Seo::setSite(new SiteConfig(locale: 'ja_JP'));

        $this->assertSame('ja_JP', Resolver::fromContext($this->singular())->locale());
    }

    public function testOgType(): void
    {
        $this->assertSame('article', Resolver::fromContext($this->singular())->ogType());
        $this->assertSame('website', Resolver::fromContext(new Context(type: Context::TYPE_FRONT, object: $this->makePost()))->ogType());
        $this->assertSame('website', Resolver::fromContext($this->newsArchive())->ogType());
    }

    public function testOgTitleFallbacks(): void
    {
        $this->assertSame('About (post title)', Resolver::fromContext($this->singular())->ogTitle());

        // The front page is named after the site, not its page's post title.
        $front = new Context(type: Context::TYPE_FRONT, object: $this->makePost(), title: 'Home');
        $this->assertSame('Example Site', Resolver::fromContext($front)->ogTitle());
    }

    public function testToArray(): void
    {
        Seo::registerPage('about', new PageConfig(title: 'About', noindex: true));

        $this->assertSame([
            'title'       => 'About',
            'description' => 'From the excerpt.',
            'canonical'   => 'https://example.com/about/',
            'noindex'     => true,
            'nofollow'    => false,
            'og_image'    => 'https://example.com/featured.jpg',
            'og_type'     => 'article',
            'og_url'      => 'https://example.com/about/',
        ], Resolver::fromContext($this->singular())->toArray());
    }
}
