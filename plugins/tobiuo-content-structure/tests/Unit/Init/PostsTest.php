<?php

namespace TobiuoPlugin\Tests\Unit\Init;

use TobiuoPlugin\Helpers\Registry;
use TobiuoPlugin\Init\Posts;
use TobiuoPlugin\Init\Registration;
use TobiuoPlugin\Init\Rewrite;
use TobiuoPlugin\Structure\PermalinkConfig;
use TobiuoPlugin\Structure\PostsConfig;
use TobiuoPlugin\Structure\PostTypeConfig;
use TobiuoPlugin\Tests\Unit\BaseTestCase;

class PostsTest extends BaseTestCase
{
    private const FEEDS = ['feed', 'rdf', 'rss', 'rss2', 'atom'];

    public function testRegisterHooksTheFilters(): void
    {
        Posts::register();

        $this->assertSame(10, has_filter('post_type_archive_link', [Posts::class, 'filterArchiveLink']));
        $this->assertSame(10, has_filter('register_post_type_args', [Posts::class, 'filterPostTypeArgs']));
    }

    public function testTheStoredStructureIsNeverReplaced(): void
    {
        $rewrite = $this->useRewrite('/%postname%/');
        Posts::register();
        Registry::registerPosts(new PostsConfig(archive: 'news', permalink: new PermalinkConfig(structure: '/%postname%/')));

        Registration::handOver();

        $this->assertSame('/%postname%/', get_option('permalink_structure'));
        $this->assertSame('/%postname%/', $rewrite->permalink_structure);
    }

    public function testNothingIsFilteredWhileAnotherPluginHandlesPermalinks(): void
    {
        Registry::registerPosts(new PostsConfig(archive: 'news', permalink: new PermalinkConfig(structure: '/%postname%/')));

        $this->assertSame('https://example.com/', Posts::filterArchiveLink('https://example.com/', 'post'));
        $this->assertSame(['public' => true], Posts::filterPostTypeArgs(['public' => true], 'post'));
        $this->assertSame([], Posts::expectedRules());
    }

    public function testTheArchiveIsSetOnThePostObjectAndGetsRules(): void
    {
        $this->useRewrite('/news/%postname%/');
        $post = $this->registerCorePost();
        Posts::register();
        Registry::registerPosts(new PostsConfig(archive: 'news'));

        Registration::handOver();

        $this->assertSame('news', $post->has_archive);
        $this->assertSame([
            'news/?$' => 'index.php?post_type=post',
            'news/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?post_type=post&feed=$matches[1]',
            'news/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?post_type=post&feed=$matches[1]',
            'news/page/([0-9]{1,})/?$' => 'index.php?post_type=post&paged=$matches[1]',
        ], $GLOBALS['__tobiuo_test_rewrite_rules']['top']);
    }

    public function testWithoutAnArchiveThePostObjectIsLeftAlone(): void
    {
        $this->useRewrite('/%postname%/');
        $post = $this->registerCorePost();
        Posts::register();
        Registry::registerPosts(new PostsConfig(permalink: new PermalinkConfig(structure: '/%post_id%/')));

        Registration::handOver();

        $this->assertFalse($post->has_archive);
        $this->assertArrayNotHasKey('__tobiuo_test_rewrite_rules', $GLOBALS);
        $this->assertSame('/%postname%/', $GLOBALS['wp_rewrite']->permalink_structure);
    }

    public function testArchiveRules(): void
    {
        $this->assertSame([
            'info/news/?$' => 'index.php?post_type=post',
            'info/news/feed/(rss2|atom)/?$' => 'index.php?post_type=post&feed=$matches[1]',
            'info/news/(rss2|atom)/?$' => 'index.php?post_type=post&feed=$matches[1]',
            'info/news/seite/([0-9]{1,})/?$' => 'index.php?post_type=post&paged=$matches[1]',
        ], Posts::archiveRules('info/news', 'seite', ['rss2', 'atom']));

        $this->assertSame([
            'news/?$' => 'index.php?post_type=post',
            'news/page/([0-9]{1,})/?$' => 'index.php?post_type=post&paged=$matches[1]',
        ], Posts::archiveRules('news', 'page', []));
    }

    public function testArchiveRulesFollowTheRoot(): void
    {
        $rewrite = $this->useRewrite('/index.php/%postname%/');
        $rewrite->root = 'index.php/';
        Posts::register();
        Registry::registerPosts(new PostsConfig(archive: 'news'));

        $this->assertArrayHasKey('index.php/news/?$', Posts::expectedRules());
    }

    public function testTheMissingRulesCheckIncludesTheArchiveRules(): void
    {
        $this->useRewrite('/news/%postname%/');
        Posts::register();
        Registry::registerPosts(new PostsConfig(archive: 'news'));

        $expected = Rewrite::expectedRules();

        $this->assertSame(Posts::archiveRules('news', 'page', self::FEEDS), $expected);
        $this->assertSame(['news/page/([0-9]{1,})/?$'], Rewrite::missingRules($expected, array_slice($expected, 0, 3)));
    }

    public function testArchiveLink(): void
    {
        $this->useRewrite('/news/%postname%/');
        Posts::register();
        Registry::registerPosts(new PostsConfig(archive: 'news'));

        $this->assertSame('https://example.com/news/', Posts::filterArchiveLink('https://example.com/', 'post'));
        $this->assertSame('https://example.com/case/', Posts::filterArchiveLink('https://example.com/case/', 'case'));
    }

    public function testArchiveLinkFollowsTheTrailingSlashSetting(): void
    {
        $this->useRewrite('/news/%postname%');
        Posts::register();
        Registry::registerPosts(new PostsConfig(archive: 'info/news'));

        $this->assertSame('https://example.com/info/news', Posts::filterArchiveLink('https://example.com/', 'post'));
    }

    public function testArchiveLinkWithPlainPermalinks(): void
    {
        $this->useRewrite('');
        Posts::register();
        Registry::registerPosts(new PostsConfig(archive: 'news'));

        $this->assertSame('https://example.com/?post_type=post', Posts::filterArchiveLink('https://example.com/', 'post'));
    }

    public function testArchiveLinkWithoutAnArchiveIsCores(): void
    {
        $this->useRewrite();
        Posts::register();
        Registry::registerPosts(new PostsConfig(permalink: new PermalinkConfig(structure: '/%postname%/')));

        $this->assertSame('https://example.com/blog/', Posts::filterArchiveLink('https://example.com/blog/', 'post'));
    }

    public function testPostTypeArgsGetTheArchive(): void
    {
        Posts::register();
        Registry::registerPosts(new PostsConfig(archive: 'news'));

        $this->assertSame(['public' => true, 'has_archive' => 'news'], Posts::filterPostTypeArgs(['public' => true], 'post'));
        $this->assertSame(['public' => true], Posts::filterPostTypeArgs(['public' => true], 'page'));
        $this->assertSame('x', Posts::filterPostTypeArgs('x', 'post'));
    }

    public function testCustomPostTypesAreBuiltOnTheStoredFront(): void
    {
        $rewrite = $this->useRewrite('/%postname%/');
        Posts::register();
        Registry::registerPosts(new PostsConfig(archive: 'news', permalink: new PermalinkConfig(structure: '/%postname%/')));
        Registry::registerPostType(new PostTypeConfig(name: 'event', args: ['rewrite' => ['slug' => 'event', 'with_front' => true]]));

        Registration::handOver();

        $this->assertSame('/event/%event%', $rewrite->extra_permastructs['event']['struct']);
    }

    public function testNoMismatchWithoutAnExpectedStructure(): void
    {
        $this->assertNull(Posts::structureMismatch(null, '/%postname%/'));
        $this->assertNull(Posts::structureMismatch(new PostsConfig(archive: 'news'), '/%postname%/'));
    }

    public function testNoMismatchWhenTheStoredStructureMatches(): void
    {
        $config = new PostsConfig(archive: 'news', permalink: new PermalinkConfig(structure: '/%postname%/'));

        $this->assertNull(Posts::structureMismatch($config, '/news/%postname%/'));
    }

    public function testAMismatchReturnsTheExpectedStructure(): void
    {
        $config = new PostsConfig(archive: 'news', permalink: new PermalinkConfig(structure: '/%postname%/'));

        $this->assertSame('/news/%postname%/', Posts::structureMismatch($config, '/%postname%/'));
        $this->assertSame('/news/%postname%/', Posts::structureMismatch($config, ''));
        $this->assertSame('/news/%postname%/', Posts::structureMismatch($config, '/news/%postname%'));
    }

    public function testTheExpectedStructureWithoutAnArchiveIsAsWritten(): void
    {
        $config = new PostsConfig(permalink: new PermalinkConfig(structure: '/%category%/%postname%/'));

        $this->assertNull(Posts::structureMismatch($config, '/%category%/%postname%/'));
        $this->assertSame('/%category%/%postname%/', Posts::structureMismatch($config, '/news/%postname%/'));
    }

    /**
     * Core's own `post`, as create_initial_post_types() registers it on init 0.
     */
    private function registerCorePost(): \WP_Post_Type
    {
        return $GLOBALS['__tobiuo_test_post_types']['post'] = new \WP_Post_Type('post', ['_builtin' => true, 'rewrite' => false]);
    }
}
