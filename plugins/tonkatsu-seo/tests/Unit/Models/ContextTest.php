<?php

namespace TonkatsuPlugin\Tests\Unit\Models;

use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Models\Context;
use TonkatsuPlugin\Structure\ArchiveConfig;
use TonkatsuPlugin\Tests\Unit\BaseTestCase;

class ContextTest extends BaseTestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function relativePaths(): array
    {
        return [
            'root install'         => ['https://example.com/company/', 'https://example.com/', 'company'],
            'home itself'          => ['https://example.com/', 'https://example.com/', ''],
            'subdirectory install' => ['https://example.com/wp/company/', 'https://example.com/wp/', 'company'],
            'subdirectory home'    => ['https://example.com/wp/', 'https://example.com/wp/', ''],
            'request uri'          => ['/wp/news/page/2/?x=1', 'https://example.com/wp/', 'news/page/2'],
            'prefix is not a dir'  => ['https://example.com/wpx/', 'https://example.com/wp/', 'wpx'],
        ];
    }

    /**
     * @dataProvider relativePaths
     */
    public function testRelativePath(string $url, string $home, string $expected): void
    {
        $this->assertSame($expected, Context::relativePath($url, $home));
    }

    public function testRejectsAnUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Context(type: 'archive');
    }

    public function testStaticFrontPageIsSingularButThePostsPageIsNot(): void
    {
        $post = $this->makePost();

        $this->assertTrue((new Context(type: Context::TYPE_FRONT, object: $post))->isSingular());
        $this->assertFalse((new Context(type: Context::TYPE_FRONT))->isSingular());
        $this->assertFalse((new Context(type: Context::TYPE_HOME, object: $post))->isSingular());
        $this->assertTrue((new Context(type: Context::TYPE_SINGULAR, object: $post))->isSingular());
    }

    public function testPostIsOnlyReturnedForPosts(): void
    {
        $post = $this->makePost();

        $this->assertSame($post, (new Context(type: Context::TYPE_SINGULAR, object: $post))->post());
        $this->assertNull((new Context(type: Context::TYPE_TAXONOMY, object: new \stdClass()))->post());
    }

    public function testArchiveCrumbUsesTheRegisteredTitle(): void
    {
        Seo::registerArchive('post', new ArchiveConfig(title: 'News'));

        $this->assertSame(
            ['name' => 'News', 'url' => 'https://example.com/news/'],
            Context::archiveCrumb('post', 'https://example.com/news/', 'Posts', 'https://example.com/')
        );
    }

    public function testArchiveCrumbFallsBackToTheLabel(): void
    {
        Seo::registerArchive('case', new ArchiveConfig(description: 'Only a description'));

        $this->assertSame(
            ['name' => 'Cases', 'url' => 'https://example.com/case/'],
            Context::archiveCrumb('case', 'https://example.com/case/', 'Cases', 'https://example.com/')
        );
    }

    /**
     * @return array<string, array{?string, ?string}>
     */
    public static function noArchiveCrumb(): array
    {
        return [
            'no archive'        => [null, 'Posts'],
            'archive is home'   => ['https://example.com', 'Posts'],
            'no name'           => ['https://example.com/case/', null],
            'blank name'        => ['https://example.com/case/', ' '],
        ];
    }

    /**
     * @dataProvider noArchiveCrumb
     */
    public function testNoArchiveCrumb(?string $url, ?string $label): void
    {
        $this->assertNull(Context::archiveCrumb('case', $url, $label, 'https://example.com/'));
    }

    public function testArchiveLabelUsesThePostsPageTitleForPosts(): void
    {
        $this->assertSame('お知らせ', Context::archiveLabel('post', '投稿', 'お知らせ'));
        // No posts page, or a blank title: the label.
        $this->assertSame('投稿', Context::archiveLabel('post', '投稿', null));
        $this->assertSame('投稿', Context::archiveLabel('post', '投稿', ' '));
        // Only `post` has a posts page.
        $this->assertSame('Cases', Context::archiveLabel('case', 'Cases', 'お知らせ'));
    }

    public function testPostTypeArchiveCrumbNamesThePostArchiveAfterThePostsPage(): void
    {
        $GLOBALS['__tonkatsu_test_options'] = ['show_on_front' => 'page', 'page_for_posts' => 7];
        $GLOBALS['__tonkatsu_test_titles'][7] = 'お知らせ';
        $GLOBALS['__tonkatsu_test_archive_links']['post'] = 'https://example.com/news/';

        $this->assertSame(
            ['name' => 'お知らせ', 'url' => 'https://example.com/news/'],
            Context::postTypeArchiveCrumb('post', 'https://example.com/')
        );
    }

    public function testPostTypeArchiveCrumbPrefersTheRegisteredTitle(): void
    {
        $GLOBALS['__tonkatsu_test_options'] = ['show_on_front' => 'page', 'page_for_posts' => 7];
        $GLOBALS['__tonkatsu_test_titles'][7] = 'お知らせ';
        $GLOBALS['__tonkatsu_test_archive_links']['post'] = 'https://example.com/news/';
        Seo::registerArchive('post', new ArchiveConfig(title: 'News'));

        $this->assertSame(
            ['name' => 'News', 'url' => 'https://example.com/news/'],
            Context::postTypeArchiveCrumb('post', 'https://example.com/')
        );
    }

    public function testPostTypeArchiveCrumbIsComputedOncePerPostType(): void
    {
        $GLOBALS['__tonkatsu_test_archive_links']['case'] = 'https://example.com/case/';
        Seo::registerArchive('case', new ArchiveConfig(title: 'Cases'));

        $first = Context::postTypeArchiveCrumb('case', 'https://example.com/');
        $second = Context::postTypeArchiveCrumb('case', 'https://example.com/');
        // A post type without an archive is remembered too.
        Context::postTypeArchiveCrumb('page', 'https://example.com/');
        Context::postTypeArchiveCrumb('page', 'https://example.com/');

        $this->assertSame($first, $second);
        $this->assertSame(['case' => 1, 'page' => 1], $GLOBALS['__tonkatsu_test_archive_link_calls']);
    }

    public function testTaxonomyPostTypeNeedsExactlyOne(): void
    {
        $this->assertSame('case', Context::taxonomyPostType(['case']));
        $this->assertSame('case', Context::taxonomyPostType(['case', 'case']));
        $this->assertNull(Context::taxonomyPostType(['post', 'page']));
        $this->assertNull(Context::taxonomyPostType([]));
    }

    public function testWithArchiveCrumb(): void
    {
        $archive = ['name' => 'Cases', 'url' => 'https://example.com/case/'];
        $post = ['name' => 'First case', 'url' => 'https://example.com/case/first/'];

        $this->assertSame([$archive, $post], Context::withArchiveCrumb([$post], $archive));
        $this->assertSame([$post], Context::withArchiveCrumb([$post], null));
        // Nothing to put it in front of.
        $this->assertSame([], Context::withArchiveCrumb([], $archive));
        // Already there.
        $this->assertSame([$archive], Context::withArchiveCrumb([$archive], $archive));
    }
}
