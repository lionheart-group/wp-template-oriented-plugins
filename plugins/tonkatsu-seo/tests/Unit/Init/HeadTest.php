<?php

namespace TonkatsuPlugin\Tests\Unit\Init;

use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Init\Head;
use TonkatsuPlugin\Models\Context;
use TonkatsuPlugin\Models\Resolver;
use TonkatsuPlugin\Structure\OrganizationConfig;
use TonkatsuPlugin\Structure\PageConfig;
use TonkatsuPlugin\Structure\SiteConfig;
use TonkatsuPlugin\Tests\Unit\BaseTestCase;

class HeadTest extends BaseTestCase
{
    /**
     * Make $context the current request, as Head::resolver() would from the
     * main query.
     */
    private function useContext(Context $context): Resolver
    {
        $resolver = Resolver::fromContext($context);

        $property = new \ReflectionProperty(Head::class, 'resolver');
        $property->setAccessible(true);
        $property->setValue(null, $resolver);

        return $resolver;
    }

    private function aboutPage(): Context
    {
        return new Context(
            type: Context::TYPE_SINGULAR,
            path: 'company/about',
            url: 'https://example.com/company/about/',
            object: $this->makePost(),
            postType: 'page',
            title: 'About',
            breadcrumbs: [
                ['name' => 'Company', 'url' => 'https://example.com/company/'],
                ['name' => 'About', 'url' => 'https://example.com/company/about/'],
            ],
        );
    }

    private function render(): string
    {
        ob_start();
        Head::render();
        return (string) ob_get_clean();
    }

    // ------------------------------------------------------------------
    // Title
    // ------------------------------------------------------------------

    public function testDocumentTitleJoinsThePartsWithTheSeparator(): void
    {
        $this->assertSame('About | Example Site', Head::documentTitle(Resolver::fromContext($this->aboutPage())));

        Seo::setSite(new SiteConfig(siteName: 'Example', separator: '–'));
        Seo::registerPage('company/about', new PageConfig(title: 'About us'));

        $this->assertSame('About us – Example', Head::documentTitle(Resolver::fromContext($this->aboutPage())));
    }

    /**
     * Core's document_title callbacks return HTML (wptexturize, esc_html);
     * the admin screens want the text a visitor sees.
     */
    public function testDocumentTitleIsDecodedAfterTheDocumentTitleFilter(): void
    {
        add_filter('document_title', fn ($title) => htmlspecialchars('“' . $title . '” &', ENT_QUOTES));

        $this->assertSame('“About | Example Site” &', Head::documentTitle(Resolver::fromContext($this->aboutPage())));
    }

    public function testDocumentTitleIgnoresANonStringFilterResult(): void
    {
        add_filter('document_title', fn () => null);

        $this->assertSame('About | Example Site', Head::documentTitle(Resolver::fromContext($this->aboutPage())));
    }

    public function testParentTitlesAreOffByDefault(): void
    {
        $this->assertSame('About | Example Site', Head::documentTitle(Resolver::fromContext($this->aboutPage())));
    }

    public function testParentTitlesGoBetweenTheTitleAndTheSite(): void
    {
        Seo::setSite(new SiteConfig(includeParentTitles: true));

        $this->assertSame('About | Company | Example Site', Head::documentTitle(Resolver::fromContext($this->aboutPage())));
    }

    public function testParentTitlesFollowTheConfiguredTitleAndThePageNumber(): void
    {
        Seo::setSite(new SiteConfig(includeParentTitles: true));
        Seo::registerPage('company/about', new PageConfig(title: 'About us'));
        $this->useContext($this->aboutPage());

        $this->assertSame(
            ['title' => 'About us', 'page' => 'Page 2', 'tonkatsu_parent_1' => 'Company', 'site' => 'Example Site'],
            Head::filterTitleParts(['title' => 'About', 'page' => 'Page 2', 'site' => 'Example Site'])
        );
    }

    public function testParentTitlesAreNearestFirst(): void
    {
        $context = new Context(
            type: Context::TYPE_SINGULAR,
            path: 'company/team/staff',
            url: 'https://example.com/company/team/staff/',
            object: $this->makePost(),
            postType: 'page',
            title: 'Staff',
            breadcrumbs: [
                ['name' => 'Company', 'url' => 'https://example.com/company/'],
                ['name' => 'Team', 'url' => 'https://example.com/company/team/'],
                ['name' => 'Staff', 'url' => 'https://example.com/company/team/staff/'],
            ],
        );

        $this->assertSame(['Team', 'Company'], Head::parentTitles($context));
    }

    public function testTopLevelPagesAndArchivesHaveNoParentTitles(): void
    {
        $topLevel = new Context(
            type: Context::TYPE_SINGULAR,
            url: 'https://example.com/company/',
            title: 'Company',
            breadcrumbs: [['name' => 'Company', 'url' => 'https://example.com/company/']],
        );
        $archive = new Context(
            type: Context::TYPE_TAXONOMY,
            url: 'https://example.com/category/child/',
            breadcrumbs: [
                ['name' => 'Parent', 'url' => 'https://example.com/category/parent/'],
                ['name' => 'Child', 'url' => 'https://example.com/category/child/'],
            ],
        );

        $this->assertSame([], Head::parentTitles($topLevel));
        $this->assertSame([], Head::parentTitles($archive));
    }

    public function testTitlePartsAreLeftAloneWhenNothingIsConfigured(): void
    {
        $this->useContext($this->aboutPage());
        $parts = ['title' => 'About', 'site' => 'Example Site'];

        $this->assertSame($parts, Head::filterTitleParts($parts));
    }

    public function testConfiguredTitleAndSiteNameReplaceTheirParts(): void
    {
        Seo::setSite(new SiteConfig(siteName: 'Example Inc.'));
        Seo::registerPage('company/about', new PageConfig(title: '会社概要'));
        $this->useContext($this->aboutPage());

        $this->assertSame(
            ['title' => '会社概要', 'page' => 'Page 2', 'site' => 'Example Inc.'],
            Head::filterTitleParts(['title' => 'About', 'page' => 'Page 2', 'site' => 'Example Site'])
        );
    }

    public function testFrontPageTitleReplacesTheTagline(): void
    {
        Seo::registerPage('', new PageConfig(title: 'Example — widgets since 1900'));
        $this->useContext(new Context(type: Context::TYPE_FRONT));

        $this->assertSame(
            ['title' => 'Example — widgets since 1900'],
            Head::filterTitleParts(['title' => 'Example Site', 'tagline' => 'Just another site'])
        );
    }

    public function testFrontPageWithoutATitleUsesTheConfiguredSiteName(): void
    {
        Seo::setSite(new SiteConfig(siteName: 'Example Inc.'));
        $this->useContext(new Context(type: Context::TYPE_FRONT));

        $this->assertSame(
            ['title' => 'Example Inc.', 'tagline' => 'Just another site'],
            Head::filterTitleParts(['title' => 'Example Site', 'tagline' => 'Just another site'])
        );
    }

    public function testNonArrayTitlePartsPassThrough(): void
    {
        $this->useContext($this->aboutPage());

        $this->assertSame('broken', Head::filterTitleParts('broken'));
    }

    public function testSeparator(): void
    {
        $this->assertSame('|', Head::filterSeparator('-'));

        Seo::setSite(new SiteConfig(separator: '｜'));
        $this->assertSame('｜', Head::filterSeparator('-'));
    }

    // ------------------------------------------------------------------
    // Robots, canonical
    // ------------------------------------------------------------------

    public function testRobotsAreLeftAloneWhenIndexable(): void
    {
        $this->useContext($this->aboutPage());
        $robots = ['max-image-preview' => 'large'];

        $this->assertSame($robots, Head::filterRobots($robots));
    }

    public function testRobotsNoindexNofollow(): void
    {
        Seo::registerPage('company/about', new PageConfig(noindex: true, nofollow: true));
        $this->useContext($this->aboutPage());

        $this->assertSame(
            ['max-image-preview' => 'large', 'noindex' => true, 'nofollow' => true],
            Head::filterRobots(['index' => true, 'follow' => true, 'max-image-preview' => 'large'])
        );
    }

    public function testCanonicalFilterIgnoresNonPosts(): void
    {
        $this->assertSame('https://example.com/x/', Head::filterCanonicalUrl('https://example.com/x/', null));
    }

    // ------------------------------------------------------------------
    // OGP / Twitter
    // ------------------------------------------------------------------

    public function testOgTags(): void
    {
        Seo::setSite(new SiteConfig(twitterSite: '@example', locale: 'ja_JP'));
        Seo::registerPage('company/about', new PageConfig(description: 'About us.'));
        $resolver = $this->useContext($this->aboutPage());

        $this->assertSame([
            'og:site_name'   => 'Example Site',
            'og:title'       => 'About',
            'og:description' => 'About us.',
            'og:type'        => 'article',
            'og:url'         => 'https://example.com/company/about/',
            'og:locale'      => 'ja_JP',
            'twitter:card'   => 'summary',
            'twitter:site'   => '@example',
        ], Head::ogTags($resolver));
    }

    public function testTwitterCardIsLargeWithAnImage(): void
    {
        Seo::setSite(new SiteConfig(defaultOgImage: '/ogp.png'));
        $resolver = $this->useContext($this->aboutPage());

        $tags = Head::ogTags($resolver);

        $this->assertSame('https://example.com/ogp.png', $tags['og:image']);
        $this->assertSame('summary_large_image', $tags['twitter:card']);
    }

    public function testOgTagsFilterReceivesTheContext(): void
    {
        $context = $this->aboutPage();
        $resolver = $this->useContext($context);
        $received = null;

        add_filter('tonkatsu_og_tags', function ($tags, $ctx) use (&$received) {
            $received = $ctx;
            $tags['og:image'] = ['https://example.com/a.png', 'https://example.com/b.png'];
            unset($tags['og:locale']);
            return $tags;
        }, 10, 2);

        $tags = Head::ogTags($resolver);

        $this->assertSame($context, $received);
        $this->assertCount(2, $tags['og:image']);
        $this->assertArrayNotHasKey('og:locale', $tags);
    }

    public function testOgTagsFilterReturningANonArrayKeepsTheDefaults(): void
    {
        $resolver = $this->useContext($this->aboutPage());
        $defaults = Head::ogTags($resolver);

        add_filter('tonkatsu_og_tags', fn () => 'broken');

        $this->assertSame($defaults, Head::ogTags($resolver));
    }

    // ------------------------------------------------------------------
    // JSON-LD
    // ------------------------------------------------------------------

    public function testJsonLdGraph(): void
    {
        Seo::setSite(new SiteConfig(
            organization: new OrganizationConfig(name: 'Example Inc.', logo: '/logo.png', sameAs: ['https://x.com/example']),
        ));
        Seo::registerPage('company/about', new PageConfig(title: '会社概要'));
        $resolver = $this->useContext($this->aboutPage());

        $graph = Head::jsonLd($resolver);
        $this->assertSame(['WebSite', 'Organization', 'BreadcrumbList'], array_column($graph, '@type'));

        [$website, $organization, $breadcrumbs] = $graph;
        $this->assertSame(['@id' => 'https://example.com/#organization'], $website['publisher']);
        $this->assertSame('https://example.com/logo.png', $organization['logo']['url']);
        $this->assertSame(['https://x.com/example'], $organization['sameAs']);

        $this->assertSame(
            [
                [1, 'Example Site', 'https://example.com/'],
                [2, 'Company', 'https://example.com/company/'],
                // The configured title names the current crumb.
                [3, '会社概要', 'https://example.com/company/about/'],
            ],
            array_map(fn ($item) => [$item['position'], $item['name'], $item['item']], $breadcrumbs['itemListElement'])
        );
    }

    public function testNoOrganizationOrBreadcrumbsUnlessThereAreAny(): void
    {
        $resolver = $this->useContext(new Context(type: Context::TYPE_FRONT, url: 'https://example.com/'));

        $graph = Head::jsonLd($resolver);

        $this->assertSame(['WebSite'], array_column($graph, '@type'));
        $this->assertArrayNotHasKey('publisher', $graph[0]);
    }

    public function testJsonLdFilter(): void
    {
        $resolver = $this->useContext($this->aboutPage());

        add_filter('tonkatsu_json_ld', function ($graph) {
            $graph[] = ['@type' => 'LocalBusiness', 'name' => 'Example Shop'];
            return $graph;
        });

        $this->assertSame('LocalBusiness', Head::jsonLd($resolver)[2]['@type']);
    }

    public function testJsonLdFilterReturningANonArrayKeepsTheDefaults(): void
    {
        $resolver = $this->useContext($this->aboutPage());
        $defaults = Head::jsonLd($resolver);

        add_filter('tonkatsu_json_ld', fn () => null);

        $this->assertSame($defaults, Head::jsonLd($resolver));
    }

    // ------------------------------------------------------------------
    // Output
    // ------------------------------------------------------------------

    public function testRenderEscapesEverything(): void
    {
        Seo::registerPage('company/about', new PageConfig(
            title: 'A "quoted" </script><script>alert(1)</script>',
            description: 'Tom & "Jerry" <b>',
        ));
        $this->useContext($this->aboutPage());

        $html = $this->render();

        $this->assertStringContainsString('<meta name="description" content="Tom &amp; &quot;Jerry&quot; &lt;b&gt;" />', $html);
        $this->assertStringContainsString('<meta property="og:title" content="A &quot;quoted&quot; &lt;/script&gt;', $html);

        // Exactly one closing script tag: the JSON-LD block's own.
        $this->assertSame(1, substr_count($html, '</script>'));
        $this->assertStringContainsString('</script>', $html);
        // Slashes and non-ASCII stay readable.
        $this->assertStringContainsString('"https://example.com/"', $html);
    }

    public function testRenderUsesNameForTwitterAndPropertyForOgp(): void
    {
        $this->useContext($this->aboutPage());

        $html = $this->render();

        $this->assertStringContainsString('<meta name="twitter:card" content="summary" />', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article" />', $html);
    }

    public function testRenderEscapesUrlsWithEscUrl(): void
    {
        Seo::registerPage('company/about', new PageConfig(ogImage: 'https://example.com/ogp.php?a=1&b=2'));
        $this->useContext($this->aboutPage());

        $this->assertStringContainsString(
            '<meta property="og:image" content="https://example.com/ogp.php?a=1&#038;b=2" />',
            $this->render()
        );
    }

    public function testRenderPrintsListValuesOnceEach(): void
    {
        $this->useContext($this->aboutPage());
        add_filter('tonkatsu_og_tags', fn ($tags) => array_merge($tags, [
            'og:image' => ['https://example.com/a.png', 'https://example.com/b.png'],
            'og:bad'   => [['nested']],
        ]));

        $html = $this->render();

        $this->assertSame(2, substr_count($html, 'property="og:image"'));
        $this->assertStringNotContainsString('og:bad', $html);
    }

    /**
     * Core prints the canonical of singular requests itself.
     */
    public function testCanonicalIsOnlyPrintedForNonSingularRequests(): void
    {
        $this->useContext($this->aboutPage());
        $this->assertStringNotContainsString('rel="canonical"', $this->render());

        $this->useContext(new Context(type: Context::TYPE_POST_TYPE_ARCHIVE, path: 'news', url: 'https://example.com/news/page/2/', postType: 'news'));
        $this->assertStringContainsString('<link rel="canonical" href="https://example.com/news/page/2/" />', $this->render());
    }

    public function testNoCanonicalForSearch(): void
    {
        $this->useContext(new Context(type: Context::TYPE_SEARCH));

        $this->assertStringNotContainsString('rel="canonical"', $this->render());
    }

    public function testRenderOmitsJsonLdWhenTheFilterEmptiesIt(): void
    {
        $this->useContext($this->aboutPage());
        add_filter('tonkatsu_json_ld', fn () => []);

        $this->assertStringNotContainsString('application/ld+json', $this->render());
    }
}
