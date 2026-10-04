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

        $this->assertSame(10, has_filter('pre_option_permalink_structure', [Posts::class, 'filterPermalinkStructure']));
        $this->assertSame(10, has_filter('post_type_archive_link', [Posts::class, 'filterArchiveLink']));
        $this->assertSame(10, has_filter('register_post_type_args', [Posts::class, 'filterPostTypeArgs']));
        $this->assertSame(10, has_action('admin_init', [Posts::class, 'addPermalinkSection']));
    }

    public function testThePermalinkStructureIsLeftToTheOptionUntilConfigured(): void
    {
        Posts::register();
        $GLOBALS['__tobiuo_test_options']['permalink_structure'] = '/%year%/%postname%/';

        $this->assertFalse(Posts::filterPermalinkStructure(false));
        $this->assertSame('/%year%/%postname%/', get_option('permalink_structure'));
    }

    public function testThePermalinkStructureComesFromTheConfig(): void
    {
        Posts::register();
        Registry::registerPosts(new PostsConfig(archive: 'news', permalink: new PermalinkConfig(structure: '/%postname%/')));

        $this->assertSame('/news/%postname%/', get_option('permalink_structure'));
    }

    public function testTheStructureIsAsWrittenWithoutAnArchive(): void
    {
        Posts::register();
        Registry::registerPosts(new PostsConfig(permalink: new PermalinkConfig(structure: '/%category%/%postname%/')));

        $this->assertSame('/%category%/%postname%/', get_option('permalink_structure'));
    }

    public function testAnArchiveAloneLeavesTheStructureToTheOption(): void
    {
        Posts::register();
        $GLOBALS['__tobiuo_test_options']['permalink_structure'] = '/%postname%/';
        Registry::registerPosts(new PostsConfig(archive: 'news'));

        $this->assertSame('/%postname%/', get_option('permalink_structure'));
    }

    public function testNothingIsFilteredWhileAnotherPluginHandlesPermalinks(): void
    {
        Registry::registerPosts(new PostsConfig(archive: 'news', permalink: new PermalinkConfig(structure: '/%postname%/')));

        $this->assertFalse(Posts::filterPermalinkStructure(false));
        $this->assertSame('https://example.com/', Posts::filterArchiveLink('https://example.com/', 'post'));
        $this->assertSame(['public' => true], Posts::filterPostTypeArgs(['public' => true], 'post'));
        $this->assertSame([], Posts::expectedRules());
    }

    public function testTheHandOverMovesWpRewriteToTheNewFront(): void
    {
        $rewrite = $this->useRewrite('/%postname%/');
        $this->registerCorePost();
        // Added on init before the hand-over, as core and other plugins do
        $rewrite->add_permastruct('category', 'category/%category%', ['with_front' => true]);
        $rewrite->add_permastruct('area', 'area/%area%', ['with_front' => false]);
        $rewrite->endpoints = [[1, 'amp', 'amp']];
        $rewrite->extra_rules = ['bottom/?$' => 'index.php?x=1'];
        $rewrite->non_wp_rules = ['legacy/?$' => 'old.php'];
        Posts::register();
        Registry::registerPosts(new PostsConfig(archive: 'news', permalink: new PermalinkConfig(structure: '/%postname%/')));

        Registration::handOver();

        $this->assertSame('/news/%postname%/', $rewrite->permalink_structure);
        $this->assertSame('/news/', $rewrite->front);
        $this->assertSame('/news/category/%category%', $rewrite->extra_permastructs['category']['struct']);
        $this->assertSame('area/%area%', $rewrite->extra_permastructs['area']['struct']);
        $this->assertSame([[1, 'amp', 'amp']], $rewrite->endpoints);
        $this->assertSame(['bottom/?$' => 'index.php?x=1'], $rewrite->extra_rules);
        $this->assertSame(['legacy/?$' => 'old.php'], $rewrite->non_wp_rules);
    }

    public function testCoreArchiveRulesOfOtherPostTypesMoveWithTheFront(): void
    {
        $rewrite = $this->useRewrite('/blog/%postname%/');
        $GLOBALS['__tobiuo_test_post_types']['event'] = new \WP_Post_Type('event', [
            'has_archive' => 'events',
            'rewrite'     => ['slug' => 'event', 'with_front' => true],
        ]);
        $GLOBALS['__tobiuo_test_post_types']['shop'] = new \WP_Post_Type('shop', [
            'has_archive' => true,
            'rewrite'     => ['slug' => 'shop', 'with_front' => false],
        ]);
        $rewrite->extra_rules_top = [
            'first/?$' => 'index.php?a=1',
            'blog/events/?$' => 'index.php?post_type=event',
            'blog/events/page/([0-9]{1,})/?$' => 'index.php?post_type=event&paged=$matches[1]',
            'shop/?$' => 'index.php?post_type=shop',
            'last/?$' => 'index.php?b=1',
        ];
        Posts::register();
        Registry::registerPosts(new PostsConfig(permalink: new PermalinkConfig(structure: '/%postname%/')));

        Posts::apply();

        $this->assertSame([
            'first/?$' => 'index.php?a=1',
            'events/?$' => 'index.php?post_type=event',
            'events/page/([0-9]{1,})/?$' => 'index.php?post_type=event&paged=$matches[1]',
            'shop/?$' => 'index.php?post_type=shop',
            'last/?$' => 'index.php?b=1',
        ], $rewrite->extra_rules_top);
    }

    public function testNothingIsReinitialisedWhenTheStructureAlreadyMatches(): void
    {
        $rewrite = $this->useRewrite('/news/%postname%/');
        $rewrite->endpoints = [[1, 'amp', 'amp']];
        $rewrite->add_permastruct('category', 'category/%category%');
        Posts::register();
        Registry::registerPosts(new PostsConfig(archive: 'news', permalink: new PermalinkConfig(structure: '/%postname%/')));

        Posts::apply();

        $this->assertSame('/news/category/%category%', $rewrite->extra_permastructs['category']['struct']);
        $this->assertSame([[1, 'amp', 'amp']], $rewrite->endpoints);
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
        $this->assertSame('/%post_id%/', $GLOBALS['wp_rewrite']->permalink_structure);
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

    public function testRebasePermastructs(): void
    {
        $structs = [
            'category'    => ['struct' => '/category/%category%', 'with_front' => true],
            'plain-front' => ['struct' => 'tag/%tag%', 'with_front' => true],
            'no-front'    => ['struct' => 'area/%area%', 'with_front' => false],
            'odd'         => ['struct' => '/elsewhere/%x%', 'with_front' => true],
            'legacy'      => 'old/%x%',
        ];

        $this->assertSame('/news/category/%category%', Posts::rebasePermastructs($structs, '/', '/news/', '', '')['category']['struct']);
        $this->assertSame('/news/tag/%tag%', Posts::rebasePermastructs($structs, '', '/news/', '', '')['plain-front']['struct']);
        $this->assertSame('area/%area%', Posts::rebasePermastructs($structs, '/', '/news/', '', '')['no-front']['struct']);
        $this->assertSame('index.php/area/%area%', Posts::rebasePermastructs($structs, '/', '/news/', '', 'index.php/')['no-front']['struct']);
        $this->assertSame('/elsewhere/%x%', Posts::rebasePermastructs($structs, '/blog/', '/news/', '', '')['odd']['struct']);
        $this->assertSame('old/%x%', Posts::rebasePermastructs($structs, '/', '/news/', '', '')['legacy']);
    }

    public function testRenameRulesKeepsTheOrder(): void
    {
        $this->assertSame(
            ['a' => '1', 'B' => '2', 'c' => '3'],
            Posts::renameRules(['a' => '1', 'b' => '2', 'c' => '3'], ['b' => 'B', 'x' => 'y'])
        );
    }

    public function testThePermalinkSectionIsAddedWhenTheThemeSetsTheStructure(): void
    {
        Posts::register();
        Registry::registerPosts(new PostsConfig(archive: 'news', permalink: new PermalinkConfig(structure: '/%postname%/')));

        Posts::addPermalinkSection();

        $this->assertSame(['permalink'], array_column($GLOBALS['__tobiuo_test_settings_sections'] ?? [], 'page'));
        $this->assertSame('tobiuo-content-structure', $GLOBALS['__tobiuo_test_settings_sections'][0]['id']);

        ob_start();
        Posts::renderPermalinkSection();
        $html = (string) ob_get_clean();
        $this->assertStringContainsString('<code>/news/%postname%/</code>', $html);
    }

    public function testNoPermalinkSectionWithoutAStructureFromTheTheme(): void
    {
        Posts::register();
        Registry::registerPosts(new PostsConfig(archive: 'news'));

        Posts::addPermalinkSection();

        $this->assertSame([], $GLOBALS['__tobiuo_test_settings_sections'] ?? []);
    }

    public function testNoPermalinkSectionForUsersWhoCannotManageOptions(): void
    {
        Posts::register();
        Registry::registerPosts(new PostsConfig(permalink: new PermalinkConfig(structure: '/%postname%/')));
        $GLOBALS['__tobiuo_test_cannot'] = ['manage_options'];

        Posts::addPermalinkSection();

        $this->assertSame([], $GLOBALS['__tobiuo_test_settings_sections'] ?? []);
    }

    public function testCustomPostTypesStillRegisterAfterThePosts(): void
    {
        $rewrite = $this->useRewrite('/%postname%/');
        Posts::register();
        Registry::registerPosts(new PostsConfig(archive: 'news', permalink: new PermalinkConfig(structure: '/%postname%/')));
        Registry::registerPostType(new PostTypeConfig(name: 'event', args: ['rewrite' => ['slug' => 'event', 'with_front' => true]]));

        Registration::handOver();

        // Registered after the front moved, so it is built on the new one
        $this->assertSame('/news/event/%event%', $rewrite->extra_permastructs['event']['struct']);
    }

    /**
     * Core's own `post`, as create_initial_post_types() registers it on init 0.
     */
    private function registerCorePost(): \WP_Post_Type
    {
        return $GLOBALS['__tobiuo_test_post_types']['post'] = new \WP_Post_Type('post', ['_builtin' => true, 'rewrite' => false]);
    }
}
