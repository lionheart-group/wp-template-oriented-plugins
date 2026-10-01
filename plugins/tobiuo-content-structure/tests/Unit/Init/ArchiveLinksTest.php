<?php

namespace TobiuoPlugin\Tests\Unit\Init;

use TobiuoPlugin\Helpers\Registry;
use TobiuoPlugin\Init\ArchiveLinks;
use TobiuoPlugin\Init\Registration;
use TobiuoPlugin\Structure\PermalinkConfig;
use TobiuoPlugin\Structure\PostTypeConfig;
use TobiuoPlugin\Tests\Unit\BaseTestCase;

class ArchiveLinksTest extends BaseTestCase
{
    private const PERMASTRUCTS = [
        'day'   => '/%year%/%monthnum%/%day%/',
        'month' => '/%year%/%monthnum%/',
        'year'  => '/%year%/',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->useRewrite();
        ArchiveLinks::register();
    }

    public function testDateLinks(): void
    {
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/', dateArchive: true));

        $this->assertSame('https://example.com/case/2024/', ArchiveLinks::dateLink('case', 2024));
        $this->assertSame('https://example.com/case/2024/05/', ArchiveLinks::dateLink('case', 2024, 5));
        $this->assertSame('https://example.com/case/2024/05/09/', ArchiveLinks::dateLink('case', 2024, 5, 9));
    }

    public function testTheGlobalFunctions(): void
    {
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/', dateArchive: true, authorArchive: true));
        $GLOBALS['__tobiuo_test_users'][3] = new \WP_User(3, 'jane');

        $this->assertSame('https://example.com/case/2024/', tobiuo_get_year_link('case', 2024));
        $this->assertSame('https://example.com/case/2024/05/', tobiuo_get_month_link('case', 2024, 5));
        $this->assertSame('https://example.com/case/2024/05/09/', tobiuo_get_day_link('case', 2024, 5, 9));
        $this->assertSame('https://example.com/case/author/jane/', tobiuo_get_author_link('case', 3));
        $this->assertSame('https://example.com/case/author/jane/', tobiuo_get_author_link('case', $GLOBALS['__tobiuo_test_users'][3]));
    }

    public function testDateLinksUseTheDateFrontAndTheArchiveSlug(): void
    {
        $this->registerCase(new PermalinkConfig(structure: '/%post_id%/', dateArchive: true), 'works');

        $this->assertSame('https://example.com/works/date/2024/05/', ArchiveLinks::dateLink('case', 2024, 5));
    }

    public function testDateLinksFollowTheSiteWideTrailingSlash(): void
    {
        $this->useRewrite('/%postname%');
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/', dateArchive: true));

        $this->assertSame('https://example.com/case/2024', ArchiveLinks::dateLink('case', 2024));
    }

    public function testDateLinksWithPlainPermalinks(): void
    {
        $this->useRewrite('');
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/', dateArchive: true));

        $this->assertSame('https://example.com/?post_type=case&year=2024&monthnum=5', ArchiveLinks::dateLink('case', 2024, 5));
    }

    public function testDateLinksAreEmptyWhenDisabled(): void
    {
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/'));

        $this->assertSame('', ArchiveLinks::dateLink('case', 2024));
        $this->assertSame('', ArchiveLinks::dateLink('news', 2024));
        $this->assertSame('', tobiuo_get_year_link('page', 2024));
    }

    public function testPostsUseCoresArchiveLinks(): void
    {
        $this->useRewrite('/news/%postname%/');

        $this->assertSame('https://example.com/news/2024/', tobiuo_get_year_link('post', 2024));
        $this->assertSame('https://example.com/news/2024/05/', tobiuo_get_month_link('post', 2024, 5));
        $this->assertSame('https://example.com/news/2024/05/09/', tobiuo_get_day_link('post', 2024, 5, 9));
        $this->assertSame('https://example.com/news/author/jane/', tobiuo_get_author_link('post', new \WP_User(3, 'jane')));
        $this->assertSame('', tobiuo_get_year_link('post', 99));
        $this->assertSame('', tobiuo_get_author_link('post', 404));
    }

    public function testDateLinksAreEmptyWhileAnotherPluginHandlesPermalinks(): void
    {
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/', dateArchive: true));
        (new \ReflectionProperty(ArchiveLinks::class, 'enabled'))->setValue(null, false);

        $this->assertSame('', ArchiveLinks::dateLink('case', 2024));
    }

    /**
     * @return array<string, array{int, ?int, ?int}>
     */
    public static function invalidDates(): array
    {
        return [
            'year 999'     => [999, null, null],
            'year 10000'   => [10000, null, null],
            'month 0'      => [2024, 0, null],
            'month 13'     => [2024, 13, null],
            'day 0'        => [2024, 5, 0],
            'day 32'       => [2024, 5, 32],
            'day no month' => [2024, null, 5],
        ];
    }

    /**
     * @dataProvider invalidDates
     */
    public function testInvalidDatesGiveNoLink(int $year, ?int $month, ?int $day): void
    {
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/', dateArchive: true));

        $this->assertSame('', ArchiveLinks::dateLink('case', $year, $month, $day));
    }

    public function testAuthorLinks(): void
    {
        $this->useRewrite('/blog/%postname%/');
        $GLOBALS['wp_rewrite']->author_base = 'writer';
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/', authorArchive: true), true, ['slug' => 'case', 'with_front' => true]);
        $GLOBALS['__tobiuo_test_users'][3] = new \WP_User(3, 'jane');

        $this->assertSame('https://example.com/blog/case/writer/jane/', ArchiveLinks::authorLink('case', 3));
        $this->assertSame('', ArchiveLinks::authorLink('case', 4));
        $this->assertSame('', ArchiveLinks::dateLink('case', 2024));
    }

    public function testAuthorLinksWithPlainPermalinks(): void
    {
        $this->useRewrite('');
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/', authorArchive: true));

        $this->assertSame('https://example.com/?post_type=case&author=3', ArchiveLinks::authorLink('case', new \WP_User(3, 'jane')));
    }

    public function testAuthorLinksAreEmptyWhenDisabled(): void
    {
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/', dateArchive: true));

        $this->assertSame('', ArchiveLinks::authorLink('case', new \WP_User(3, 'jane')));
    }

    /**
     * @return array<string, array{string, string, ?array<string, mixed>}>
     */
    public static function dateUrls(): array
    {
        return [
            'year'                 => ['https://example.com/2024/?post_type=case', 'https://example.com/', ['post_type' => 'case', 'year' => 2024, 'month' => null, 'day' => null, 'query' => []]],
            'month'                => ['https://example.com/2024/05/?post_type=case', 'https://example.com/', ['post_type' => 'case', 'year' => 2024, 'month' => 5, 'day' => null, 'query' => []]],
            'day'                  => ['https://example.com/2024/05/09/?post_type=case', 'https://example.com/', ['post_type' => 'case', 'year' => 2024, 'month' => 5, 'day' => 9, 'query' => []]],
            'escaped ampersand'    => ['https://example.com/2024/?lang=ja&#038;post_type=case', 'https://example.com/', ['post_type' => 'case', 'year' => 2024, 'month' => null, 'day' => null, 'query' => ['lang' => 'ja']]],
            'subdirectory'         => ['https://example.com/wp/2024/05/?post_type=case', 'https://example.com/wp/', ['post_type' => 'case', 'year' => 2024, 'month' => 5, 'day' => null, 'query' => []]],
            'outside subdirectory' => ['https://example.com/2024/05/?post_type=case', 'https://example.com/wp/', null],
            'no post type'         => ['https://example.com/2024/', 'https://example.com/', null],
            'empty post type'      => ['https://example.com/2024/?post_type=', 'https://example.com/', null],
            'post type array'      => ['https://example.com/2024/?post_type[]=case', 'https://example.com/', null],
            'a post'               => ['https://example.com/case/my-case/?post_type=case', 'https://example.com/', null],
            'weekly'               => ['https://example.com/?m=2024&w=19&post_type=case', 'https://example.com/', null],
        ];
    }

    /**
     * @dataProvider dateUrls
     * @param ?array<string, mixed> $expected
     */
    public function testParseDateUrl(string $url, string $home, ?array $expected): void
    {
        $this->assertSame($expected, ArchiveLinks::parseDateUrl($url, $home, self::PERMASTRUCTS));
    }

    public function testParseDateUrlWithADateFront(): void
    {
        $permastructs = [
            'day'   => '/date/%year%/%monthnum%/%day%/',
            'month' => '/date/%year%/%monthnum%/',
            'year'  => '/date/%year%/',
        ];

        $this->assertSame(2024, ArchiveLinks::parseDateUrl('https://example.com/date/2024/?post_type=case', 'https://example.com/', $permastructs)['year'] ?? null);
        $this->assertNull(ArchiveLinks::parseDateUrl('https://example.com/2024/?post_type=case', 'https://example.com/', $permastructs));
    }

    public function testWpGetArchivesLinksArePointedAtThePostTypeArchive(): void
    {
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/', dateArchive: true));
        $url = esc_url('https://example.com/2024/05/?post_type=case');

        $this->assertSame(
            "\t<li><a href='https://example.com/case/2024/05/'>May 2024</a></li>\n",
            ArchiveLinks::filterArchivesLink("\t<li><a href='{$url}'>May 2024</a></li>\n", $url)
        );
        $this->assertSame(
            "\t<option value='https://example.com/case/2024/05/'> May 2024 </option>\n",
            ArchiveLinks::filterArchivesLink("\t<option value='{$url}'> May 2024 </option>\n", $url)
        );
    }

    public function testOtherQueryArgumentsAreKept(): void
    {
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/', dateArchive: true));
        $url = esc_url('https://example.com/2024/?lang=ja&post_type=case');

        $this->assertSame(
            "<a href='https://example.com/case/2024/?lang=ja'>2024</a>",
            ArchiveLinks::filterArchivesLink("<a href='{$url}'>2024</a>", $url)
        );
    }

    public function testOnlyTheHrefIsReplaced(): void
    {
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/', dateArchive: true));
        $url = esc_url('https://example.com/2024/?post_type=case');
        $html = "<a href='{$url}' data-x='y'>{$url}</a>";

        $this->assertSame(
            "<a href='https://example.com/case/2024/' data-x='y'>{$url}</a>",
            ArchiveLinks::filterArchivesLink($html, $url)
        );
    }

    public function testUnmanagedArchivesAreLeftAlone(): void
    {
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/'));
        $html = "<a href='https://example.com/2024/?post_type=case'>2024</a>";

        $this->assertSame($html, ArchiveLinks::filterArchivesLink($html, 'https://example.com/2024/?post_type=case'));
        $this->assertSame($html, ArchiveLinks::filterArchivesLink($html, 'https://example.com/2024/'));
        $this->assertSame($html, ArchiveLinks::filterArchivesLink($html, 'https://example.com/2024/?post_type=news'));
        $this->assertSame($html, ArchiveLinks::filterArchivesLink($html));
        $this->assertNull(ArchiveLinks::filterArchivesLink(null, 'x'));
    }

    public function testRegisterHooksGetArchivesLink(): void
    {
        $this->assertSame(10, has_filter('get_archives_link', [ArchiveLinks::class, 'filterArchivesLink']));
    }

    /**
     * @param array<string, mixed> $rewrite
     */
    private function registerCase(PermalinkConfig $permalink, mixed $hasArchive = true, array $rewrite = ['slug' => 'case', 'with_front' => false]): void
    {
        Registry::registerPostType(new PostTypeConfig(
            name: 'case',
            args: ['public' => true, 'has_archive' => $hasArchive, 'rewrite' => $rewrite],
            permalink: $permalink,
        ));
        Registration::handOver();
    }
}
