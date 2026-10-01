<?php

namespace TobiuoPlugin\Tests\Unit\Init;

use TobiuoPlugin\Helpers\Registry;
use TobiuoPlugin\Init\Registration;
use TobiuoPlugin\Init\Rewrite;
use TobiuoPlugin\Structure\PermalinkConfig;
use TobiuoPlugin\Structure\PostTypeConfig;
use TobiuoPlugin\Structure\TaxonomyConfig;
use TobiuoPlugin\Tests\Unit\BaseTestCase;

class RewriteTest extends BaseTestCase
{
    private const FEEDS = ['feed', 'rdf', 'rss', 'rss2', 'atom'];

    /**
     * @return array<string, array{string, string}>
     */
    public static function permastructs(): array
    {
        return [
            'postname'             => ['/%postname%/', 'case/%case%/'],
            'no trailing slash'    => ['/%postname%', 'case/%case%'],
            'post id only'         => ['/%post_id%/', 'case/%tobiuo_post_id_case%/'],
            'post id and postname' => ['/%post_id%/%postname%/', 'case/%post_id%/%case%/'],
            'taxonomy'             => ['/%case_category%/%postname%/', 'case/%tobiuo_term_case_category%/%case%/'],
            'two taxonomies'       => ['/%area%/%case_category%/%post_id%/', 'case/%tobiuo_term_area%/%tobiuo_term_case_category%/%tobiuo_post_id_case%/'],
            'core tags kept'       => ['/%year%/%monthnum%/%day%/%author%/%postname%/', 'case/%year%/%monthnum%/%day%/%author%/%case%/'],
            'literal text'         => ['/n-%post_id%.html', 'case/n-%tobiuo_post_id_case%.html'],
        ];
    }

    /**
     * @dataProvider permastructs
     */
    public function testPermastruct(string $structure, string $expected): void
    {
        $this->assertSame($expected, Rewrite::permastruct('case', 'case', new PermalinkConfig(structure: $structure)));
    }

    public function testPermastructTrimsTheSlug(): void
    {
        $this->assertSame('news/case/%case%/', Rewrite::permastruct('case', '/news/case/', new PermalinkConfig(structure: '/%postname%/')));
    }

    public function testTags(): void
    {
        $this->assertSame([
            '%tobiuo_term_area%'          => ['(.+?)', 'tobiuo_term_area='],
            '%tobiuo_term_case_category%' => ['(.+?)', 'tobiuo_term_case_category='],
            '%tobiuo_post_id_case%'       => ['([0-9]+)', 'post_type=case&p='],
        ], Rewrite::tags('case', new PermalinkConfig(structure: '/%area%/%case_category%/%post_id%/')));

        $this->assertSame([], Rewrite::tags('case', new PermalinkConfig(structure: '/%post_id%/%postname%/')));
    }

    public function testPermastructArgs(): void
    {
        $this->assertSame([
            'with_front'  => false,
            'ep_mask'     => 8,
            'paged'       => true,
            'feed'        => false,
            'forcomments' => false,
            'walk_dirs'   => false,
            'endpoints'   => true,
        ], Rewrite::permastructArgs(['slug' => 'case', 'with_front' => false, 'feeds' => false, 'ep_mask' => 8]));
    }

    public function testPermastructArgsDefaults(): void
    {
        $args = Rewrite::permastructArgs([]);

        $this->assertTrue($args['with_front']);
        $this->assertSame(EP_PERMALINK, $args['ep_mask']);
        $this->assertFalse($args['feed']);
        $this->assertFalse($args['walk_dirs']);
    }

    /**
     * @return array<string, array{mixed, array<string, mixed>, string, string, ?string}>
     */
    public static function archiveSlugs(): array
    {
        return [
            'no archive'          => [false, ['slug' => 'case', 'with_front' => true], '/', '', null],
            'empty string'        => ['', ['slug' => 'case', 'with_front' => true], '/', '', null],
            'true'                => [true, ['slug' => 'case', 'with_front' => false], '/blog/', '', 'case'],
            'string'              => ['cases', ['slug' => 'case', 'with_front' => false], '/blog/', '', 'cases'],
            'with front'          => [true, ['slug' => 'case', 'with_front' => true], '/blog/', '', 'blog/case'],
            'with front at root'  => [true, ['slug' => 'case', 'with_front' => true], '/', '', 'case'],
            'string with front'   => ['works/cases/', ['slug' => 'case', 'with_front' => true], '/blog/', '', 'blog/works/cases'],
            'pathinfo root'       => [true, ['slug' => 'case', 'with_front' => false], '/index.php/', 'index.php/', 'index.php/case'],
        ];
    }

    /**
     * @dataProvider archiveSlugs
     * @param array<string, mixed> $rewrite
     */
    public function testArchiveSlug(mixed $hasArchive, array $rewrite, string $front, string $root, ?string $expected): void
    {
        $this->assertSame($expected, Rewrite::archiveSlug($hasArchive, $rewrite, $front, $root));
    }

    /**
     * @return array<string, array{string, ?string, string}>
     */
    public static function dateFronts(): array
    {
        return [
            'postname'                 => ['/%postname%/', null, ''],
            'post id'                  => ['/%post_id%/', null, '/date'],
            'post id, no slash'        => ['/%post_id%', null, '/date'],
            'year and post id'         => ['/%year%/%post_id%/', null, '/date'],
            'year month post id'       => ['/%year%/%monthnum%/%post_id%/', null, '/date'],
            'four numeric segments'    => ['/%year%/%monthnum%/%day%/%post_id%/', null, ''],
            'year and postname'        => ['/%year%/%monthnum%/%postname%/', null, ''],
            'taxonomy and post id'     => ['/%case_category%/%post_id%/', null, ''],
            'post id with text'        => ['/%post_id%.html', null, ''],
            'joined numeric tags'      => ['/%year%%monthnum%/%post_id%/', null, '/date'],
            'explicit none'            => ['/%post_id%/', '', ''],
            'explicit front'           => ['/%postname%/', '/archive', '/archive'],
        ];
    }

    /**
     * @dataProvider dateFronts
     */
    public function testDateFront(string $structure, ?string $dateFront, string $expected): void
    {
        $this->assertSame($expected, Rewrite::dateFront(new PermalinkConfig(structure: $structure, dateFront: $dateFront)));
    }

    public function testDateRules(): void
    {
        $day = 'case/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})';
        $month = 'case/([0-9]{4})/([0-9]{1,2})';
        $year = 'case/([0-9]{4})';
        $feed = '(feed|rdf|rss|rss2|atom)';

        $this->assertSame([
            "{$day}/feed/{$feed}/?$"       => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]&post_type=case',
            "{$day}/{$feed}/?$"            => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]&post_type=case',
            "{$day}/page/?([0-9]{1,})/?$"  => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&paged=$matches[4]&post_type=case',
            "{$day}/?$"                    => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&post_type=case',
            "{$month}/feed/{$feed}/?$"     => 'index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]&post_type=case',
            "{$month}/{$feed}/?$"          => 'index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]&post_type=case',
            "{$month}/page/?([0-9]{1,})/?$" => 'index.php?year=$matches[1]&monthnum=$matches[2]&paged=$matches[3]&post_type=case',
            "{$month}/?$"                  => 'index.php?year=$matches[1]&monthnum=$matches[2]&post_type=case',
            "{$year}/feed/{$feed}/?$"      => 'index.php?year=$matches[1]&feed=$matches[2]&post_type=case',
            "{$year}/{$feed}/?$"           => 'index.php?year=$matches[1]&feed=$matches[2]&post_type=case',
            "{$year}/page/?([0-9]{1,})/?$" => 'index.php?year=$matches[1]&paged=$matches[2]&post_type=case',
            "{$year}/?$"                   => 'index.php?year=$matches[1]&post_type=case',
        ], Rewrite::dateRules('case', 'case', 'page', self::FEEDS));
    }

    public function testDateRulesFollowTheRewriteBases(): void
    {
        $rules = Rewrite::dateRules('case/date', 'case', 'seite', ['rss2', 'atom']);

        $this->assertArrayHasKey('case/date/([0-9]{4})/seite/?([0-9]{1,})/?$', $rules);
        $this->assertArrayHasKey('case/date/([0-9]{4})/feed/(rss2|atom)/?$', $rules);
        $this->assertArrayNotHasKey('case/([0-9]{4})/?$', $rules);
    }

    public function testDateRulesWithoutFeeds(): void
    {
        $rules = Rewrite::dateRules('case', 'case', 'page', []);

        $this->assertCount(6, $rules);
        foreach (array_keys($rules) as $regex) {
            $this->assertStringNotContainsString('feed', $regex);
        }
    }

    public function testDateRulesMatchDatesAndNothingElse(): void
    {
        $rules = Rewrite::dateRules('case', 'case', 'page', self::FEEDS);
        $match = function (string $path) use ($rules): ?string {
            foreach ($rules as $regex => $query) {
                if (preg_match("#^{$regex}#", $path, $m) === 1) {
                    return (string) preg_replace_callback('/\$matches\[(\d+)\]/', fn ($i) => $m[(int) $i[1]], $query);
                }
            }
            return null;
        };

        $this->assertSame('index.php?year=2024&post_type=case', $match('case/2024/'));
        $this->assertSame('index.php?year=2024&monthnum=05&post_type=case', $match('case/2024/05'));
        $this->assertSame('index.php?year=2024&monthnum=5&day=12&post_type=case', $match('case/2024/5/12/'));
        $this->assertSame('index.php?year=2024&monthnum=05&paged=3&post_type=case', $match('case/2024/05/page/3/'));
        $this->assertSame('index.php?year=2024&feed=rss2&post_type=case', $match('case/2024/feed/rss2/'));
        $this->assertSame('index.php?year=2024&feed=atom&post_type=case', $match('case/2024/atom/'));
        $this->assertNull($match('case/my-case/'));
        $this->assertNull($match('case/24/'));
        $this->assertNull($match('case/2024/05/my-case/'));
        $this->assertNull($match('cases/2024/'));
    }

    public function testAuthorRules(): void
    {
        $this->assertSame([
            'case/author/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?author_name=$matches[1]&feed=$matches[2]&post_type=case',
            'case/author/([^/]+)/(feed|rdf|rss|rss2|atom)/?$'      => 'index.php?author_name=$matches[1]&feed=$matches[2]&post_type=case',
            'case/author/([^/]+)/page/?([0-9]{1,})/?$'             => 'index.php?author_name=$matches[1]&paged=$matches[2]&post_type=case',
            'case/author/([^/]+)/?$'                               => 'index.php?author_name=$matches[1]&post_type=case',
        ], Rewrite::authorRules('case/author', 'case', 'page', self::FEEDS));
    }

    public function testQuoteEscapesMetacharactersButNotSlashesOrHyphens(): void
    {
        $this->assertSame('case-study/a\\.b', Rewrite::quote('case-study/a.b'));
        $this->assertSame('\\(x\\)\\+\\?\\$', Rewrite::quote('(x)+?$'));
        $this->assertSame('事例', Rewrite::quote('事例'));
    }

    public function testApplyReplacesThePermastructAndMovesItLast(): void
    {
        $rewrite = $this->useRewrite('/blog/%postname%/');
        $this->registerCase(new PermalinkConfig(structure: '/%case_category%/%postname%/'));
        $rewrite->add_permastruct('case_category', 'case-category/%case_category%', ['with_front' => false]);

        Rewrite::apply();

        $this->assertSame(['case_category', 'case'], array_keys($rewrite->extra_permastructs));
        $this->assertSame('case/%tobiuo_term_case_category%/%case%/', $rewrite->extra_permastructs['case']['struct']);
        $this->assertFalse($rewrite->extra_permastructs['case']['walk_dirs']);
        $this->assertTrue($rewrite->extra_permastructs['case']['feed']);
        $this->assertSame(['(.+?)', 'tobiuo_term_case_category='], $GLOBALS['__tobiuo_test_rewrite_tags']['%tobiuo_term_case_category%']);
    }

    public function testApplyKeepsWithFront(): void
    {
        $rewrite = $this->useRewrite('/blog/%postname%/');
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/'), ['slug' => 'case', 'with_front' => true]);

        Rewrite::apply();

        $this->assertSame('/blog/case/%case%/', $rewrite->extra_permastructs['case']['struct']);
    }

    public function testApplyAddsArchiveRulesAtTheTop(): void
    {
        $this->useRewrite();
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/', dateArchive: true, authorArchive: true));

        Rewrite::apply();

        $rules = $GLOBALS['__tobiuo_test_rewrite_rules']['top'];
        $this->assertArrayHasKey('case/([0-9]{4})/?$', $rules);
        $this->assertArrayHasKey('case/author/([^/]+)/?$', $rules);
        $this->assertArrayHasKey('case/([0-9]{4})/feed/(feed|rdf|rss|rss2|atom)/?$', $rules);
        $this->assertCount(16, $rules);
    }

    public function testApplyUsesTheHasArchiveSlugAndDateFront(): void
    {
        $this->useRewrite();
        $this->registerCase(new PermalinkConfig(structure: '/%post_id%/', dateArchive: true), ['slug' => 'case', 'with_front' => false], 'works');

        Rewrite::apply();

        $this->assertArrayHasKey('works/date/([0-9]{4})/?$', $GLOBALS['__tobiuo_test_rewrite_rules']['top']);
        $this->assertSame(['([0-9]+)', 'post_type=case&p='], $GLOBALS['__tobiuo_test_rewrite_tags']['%tobiuo_post_id_case%']);
    }

    public function testArchiveRulesHaveNoFeedsWhenThePostTypeHasNone(): void
    {
        $this->useRewrite();
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/', dateArchive: true), ['slug' => 'case', 'with_front' => false, 'feeds' => false]);

        Rewrite::apply();

        $this->assertCount(6, $GLOBALS['__tobiuo_test_rewrite_rules']['top']);
        $this->assertFalse($GLOBALS['wp_rewrite']->extra_permastructs['case']['feed']);
    }

    public function testApplyLeavesOtherPostTypesAlone(): void
    {
        $rewrite = $this->useRewrite();
        Registry::registerPostType(new PostTypeConfig(name: 'news', args: ['has_archive' => true]));
        Registry::registerPostType(new PostTypeConfig(name: 'hidden', args: ['rewrite' => false]));
        Registration::handOver();

        Rewrite::apply();

        $this->assertSame('/news/%news%', $rewrite->extra_permastructs['news']['struct']);
        $this->assertArrayNotHasKey('hidden', $rewrite->extra_permastructs);
        $this->assertArrayNotHasKey('__tobiuo_test_rewrite_rules', $GLOBALS);
        $this->assertSame([], Rewrite::managedPostTypes());
    }

    public function testRegisterHooksTheHandOver(): void
    {
        Rewrite::register();

        $this->assertSame(10, has_action('tobiuo_registered', [Rewrite::class, 'apply']));
    }

    public function testExpectedRulesUseTheStoredMatchesStyle(): void
    {
        $rewrite = $this->useRewrite();
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/', dateArchive: true));
        Rewrite::apply();

        $rules = Rewrite::expectedRules();

        $this->assertSame('index.php?struct=$matches[1]', $rules['case/%case%/?$']);
        $this->assertArrayHasKey('case/([0-9]{4})/?$', $rules);
        $this->assertSame('', $rewrite->matches);
    }

    public function testExpectedRulesPassThroughThePermastructFilter(): void
    {
        $this->useRewrite();
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/'));
        Rewrite::apply();
        add_filter('case_rewrite_rules', fn ($rules) => ['custom/?$' => 'index.php?x=1']);

        $this->assertSame(['custom/?$' => 'index.php?x=1'], Rewrite::expectedRules());
    }

    public function testExpectedRulesAreEmptyWithPlainPermalinks(): void
    {
        $this->useRewrite('');
        $this->registerCase(new PermalinkConfig(structure: '/%postname%/'));

        $this->assertSame([], Rewrite::expectedRules());
    }

    public function testMissingRules(): void
    {
        $expected = ['a/?$' => 'index.php?a=1', 'b/?$' => 'index.php?b=1', 'c/?$' => 'index.php?c=1'];

        $this->assertSame([], Rewrite::missingRules($expected, $expected + ['d/?$' => 'index.php?d=1']));
        $this->assertSame(['b/?$', 'c/?$'], Rewrite::missingRules($expected, ['a/?$' => 'index.php?a=1', 'c/?$' => 'index.php?other=1']));
        $this->assertSame(['a/?$', 'b/?$', 'c/?$'], Rewrite::missingRules($expected, ''));
        $this->assertSame(['a/?$', 'b/?$', 'c/?$'], Rewrite::missingRules($expected, false));
        $this->assertSame([], Rewrite::missingRules([], false));
    }

    /**
     * Register `case` (with `case_category`) through the registry, as a theme would.
     *
     * @param array<string, mixed> $rewrite
     */
    private function registerCase(PermalinkConfig $permalink, array $rewrite = ['slug' => 'case', 'with_front' => false], mixed $hasArchive = true): void
    {
        Registry::registerTaxonomy(new TaxonomyConfig(name: 'case_category', objectTypes: ['case']));
        Registry::registerPostType(new PostTypeConfig(
            name: 'case',
            args: ['has_archive' => $hasArchive, 'rewrite' => $rewrite],
            permalink: $permalink,
        ));
        Registration::handOver();
    }
}
