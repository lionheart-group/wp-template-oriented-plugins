<?php

namespace TobiuoPlugin\Tests\Unit\Init;

use TobiuoPlugin\Helpers\Registry;
use TobiuoPlugin\Init\Redirect;
use TobiuoPlugin\Init\Registration;
use TobiuoPlugin\Structure\PermalinkConfig;
use TobiuoPlugin\Structure\PostTypeConfig;
use TobiuoPlugin\Structure\TaxonomyConfig;
use TobiuoPlugin\Tests\Unit\BaseTestCase;

class RedirectTest extends BaseTestCase
{
    private const PERMALINK = 'https://example.com/case/consulting/my-case/';

    public function testRegisterRunsBeforeCoresRedirectCanonical(): void
    {
        Redirect::register();

        $this->assertSame(9, has_action('template_redirect', [Redirect::class, 'maybeRedirect']));
    }

    /**
     * @return array<string, array{string, string, int, int, ?string}>
     */
    public static function targets(): array
    {
        return [
            'the permalink'                  => ['/case/consulting/my-case/', '', 0, 0, null],
            'without trailing slash'         => ['/case/consulting/my-case', '', 0, 0, null],
            'percent-encoded'                => ['/case/%63onsulting/my-case/', '', 0, 0, null],
            'doubled slashes'                => ['/case//consulting/my-case/', '', 0, 0, null],
            'wrong term'                     => ['/case/design/my-case/', '', 0, 0, self::PERMALINK],
            'missing parent term'            => ['/case/strategy/my-case/', '', 0, 0, self::PERMALINK],
            'query string kept'              => ['/case/design/my-case/', 'utm_source=x&a=1', 0, 0, self::PERMALINK . '?utm_source=x&a=1'],
            'query string, right path'       => ['/case/consulting/my-case/', 'utm_source=x', 0, 0, null],
            'page kept'                      => ['/case/design/my-case/2/', '', 2, 0, 'https://example.com/case/consulting/my-case/2/'],
            'page, right path'               => ['/case/consulting/my-case/2/', '', 2, 0, null],
            'page 1 dropped'                 => ['/case/design/my-case/1/', '', 1, 0, self::PERMALINK],
            'comment page kept'              => ['/case/design/my-case/comment-page-3/', '', 0, 3, 'https://example.com/case/consulting/my-case/comment-page-3/'],
            'comment page, right path'       => ['/case/consulting/my-case/comment-page-3', '', 0, 3, null],
            'page and comment page'          => ['/case/design/my-case/2/comment-page-3/', '', 2, 3, 'https://example.com/case/consulting/my-case/2/comment-page-3/'],
            'page not where expected'        => ['/case/design/my-case/', '', 2, 0, null],
            'comment page not where expected' => ['/case/design/my-case/', '', 0, 3, null],
        ];
    }

    /**
     * @dataProvider targets
     */
    public function testTarget(string $path, string $query, int $page, int $cpage, ?string $expected): void
    {
        $this->assertSame($expected, Redirect::target($path, $query, self::PERMALINK, $page, $cpage, 'comment-page'));
    }

    public function testTargetKeepsAStructureWithoutTrailingSlash(): void
    {
        $this->assertSame(
            'https://example.com/case/consulting/my-case/2',
            Redirect::target('/case/design/my-case/2', '', 'https://example.com/case/consulting/my-case', 2, 0, 'comment-page')
        );
    }

    public function testTargetInASubdirectoryInstall(): void
    {
        $this->assertNull(Redirect::target('/wp/case/a/x/', '', 'https://example.com/wp/case/a/x/', 0, 0, 'comment-page'));
        $this->assertSame('https://example.com/wp/case/a/x/', Redirect::target('/wp/case/b/x/', '', 'https://example.com/wp/case/a/x/', 0, 0, 'comment-page'));
    }

    public function testTargetWithMultibyteSlugs(): void
    {
        $permalink = 'https://example.com/case/%e4%ba%8b%e4%be%8b/my-case/';

        $this->assertNull(Redirect::target('/case/%E4%BA%8B%E4%BE%8B/my-case/', '', $permalink, 0, 0, 'comment-page'));
        $this->assertSame($permalink, Redirect::target('/case/other/my-case/', '', $permalink, 0, 0, 'comment-page'));
    }

    public function testAPlainPermalinkIsNeverATarget(): void
    {
        $this->assertNull(Redirect::target('/case/x/my-case/', '', 'https://example.com/?case=my-case', 0, 0, 'comment-page'));
    }

    public function testMaybeRedirectSendsAWrongTermToThePermalink(): void
    {
        $this->setUpRequest('/case/design/my-case/?ref=1');

        Redirect::maybeRedirect();

        $this->assertSame([self::PERMALINK . '?ref=1', 301], $GLOBALS['__tobiuo_test_redirect']);
    }

    public function testMaybeRedirectLeavesThePermalinkAlone(): void
    {
        $this->setUpRequest('/case/consulting/my-case/');

        Redirect::maybeRedirect();

        $this->assertArrayNotHasKey('__tobiuo_test_redirect', $GLOBALS);
    }

    public function testTheFilterCanTurnTheRedirectOff(): void
    {
        $this->setUpRequest('/case/design/my-case/');
        $seen = null;
        add_filter('tobiuo_redirect_canonical', function ($redirect, $post) use (&$seen) {
            $seen = [$redirect, $post->ID];
            return false;
        }, 10, 2);

        Redirect::maybeRedirect();

        $this->assertSame([true, 1], $seen);
        $this->assertArrayNotHasKey('__tobiuo_test_redirect', $GLOBALS);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function wrongFilterValues(): array
    {
        return [
            'null'   => [null],
            'zero'   => [0],
            'string' => ['no'],
        ];
    }

    /**
     * @dataProvider wrongFilterValues
     */
    public function testAWrongFilterValueKeepsTheRedirect(mixed $value): void
    {
        $this->setUpRequest('/case/design/my-case/');
        add_filter('tobiuo_redirect_canonical', fn () => $value);

        Redirect::maybeRedirect();

        $this->assertSame(self::PERMALINK, $GLOBALS['__tobiuo_test_redirect'][0]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function skippedConditionals(): array
    {
        return [
            'feed'       => ['is_feed'],
            'embed'      => ['is_embed'],
            'trackback'  => ['is_trackback'],
            'preview'    => ['is_preview'],
            'attachment' => ['is_attachment'],
        ];
    }

    /**
     * @dataProvider skippedConditionals
     */
    public function testSkippedRequests(string $conditional): void
    {
        $this->setUpRequest('/case/design/my-case/');
        $GLOBALS['__tobiuo_test_conditionals'][$conditional] = true;

        Redirect::maybeRedirect();

        $this->assertArrayNotHasKey('__tobiuo_test_redirect', $GLOBALS);
    }

    public function testNonSingularRequestsAreSkipped(): void
    {
        $this->setUpRequest('/case/design/my-case/');
        $GLOBALS['__tobiuo_test_conditionals']['is_singular'] = false;

        Redirect::maybeRedirect();

        $this->assertArrayNotHasKey('__tobiuo_test_redirect', $GLOBALS);
    }

    public function testQueryStringRequestsAreLeftToCore(): void
    {
        $this->setUpRequest('/?case=my-case');
        $GLOBALS['wp']->matched_rule = '';

        Redirect::maybeRedirect();

        $this->assertArrayNotHasKey('__tobiuo_test_redirect', $GLOBALS);
    }

    public function testEndpointRequestsAreSkipped(): void
    {
        $this->setUpRequest('/case/design/my-case/amp/');
        $GLOBALS['wp_rewrite']->endpoints = [[1, 'amp', 'amp']];
        $GLOBALS['wp']->query_vars['amp'] = '';

        Redirect::maybeRedirect();

        $this->assertArrayNotHasKey('__tobiuo_test_redirect', $GLOBALS);
    }

    public function testStructuresWithoutContextTagsAreSkipped(): void
    {
        $this->setUpRequest('/case/elsewhere/', '/%postname%/');
        $GLOBALS['__tobiuo_test_permalinks'][1] = 'https://example.com/case/my-case/';

        Redirect::maybeRedirect();

        $this->assertArrayNotHasKey('__tobiuo_test_redirect', $GLOBALS);
    }

    public function testUnmanagedPostTypesAreSkipped(): void
    {
        $this->setUpRequest('/news/design/my-news/');
        $GLOBALS['__tobiuo_test_queried_object'] = $this->makePost(['ID' => 2, 'post_type' => 'news']);
        $GLOBALS['__tobiuo_test_permalinks'][2] = 'https://example.com/news/my-news/';

        Redirect::maybeRedirect();

        $this->assertArrayNotHasKey('__tobiuo_test_redirect', $GLOBALS);
    }

    public function testPlainPermalinksAreSkipped(): void
    {
        $this->setUpRequest('/case/design/my-case/');
        $GLOBALS['wp_rewrite']->permalink_structure = '';

        Redirect::maybeRedirect();

        $this->assertArrayNotHasKey('__tobiuo_test_redirect', $GLOBALS);
    }

    /**
     * A singular request for post 1 of `case`, matched by a rewrite rule.
     */
    private function setUpRequest(string $uri, string $structure = '/%case_category%/%postname%/'): void
    {
        $this->useRewrite();
        Registry::registerTaxonomy(new TaxonomyConfig(name: 'case_category', objectTypes: ['case']));
        Registry::registerPostType(new PostTypeConfig(
            name: 'case',
            args: ['public' => true, 'rewrite' => ['slug' => 'case', 'with_front' => false]],
            permalink: new PermalinkConfig(structure: $structure),
        ));
        Registration::handOver();

        $GLOBALS['wp'] = new \WP();
        $GLOBALS['wp']->matched_rule = 'case/(.+?)/([^/]+)(?:/([0-9]+))?/?$';
        $GLOBALS['__tobiuo_test_conditionals']['is_singular'] = true;
        $GLOBALS['__tobiuo_test_queried_object'] = $this->makePost();
        $GLOBALS['__tobiuo_test_permalinks'][1] = self::PERMALINK;
        $_SERVER['REQUEST_URI'] = $uri;
    }
}
