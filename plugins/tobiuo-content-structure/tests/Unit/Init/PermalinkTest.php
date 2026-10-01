<?php

namespace TobiuoPlugin\Tests\Unit\Init;

use TobiuoPlugin\Helpers\Registry;
use TobiuoPlugin\Init\Permalink;
use TobiuoPlugin\Init\Registration;
use TobiuoPlugin\Structure\PermalinkConfig;
use TobiuoPlugin\Structure\PostTypeConfig;
use TobiuoPlugin\Structure\TaxonomyConfig;
use TobiuoPlugin\Tests\Unit\BaseTestCase;

class PermalinkTest extends BaseTestCase
{
    private const CORE_LINK = 'https://example.com/case/%tobiuo_term_case_category%/my-case/';

    protected function setUp(): void
    {
        parent::setUp();
        $this->useRewrite();
    }

    public function testRegisterHooksPostTypeLink(): void
    {
        Permalink::register();

        $this->assertSame(10, has_filter('post_type_link', [Permalink::class, 'filterLink']));
    }

    public function testPostname(): void
    {
        $this->registerCase('/%postname%/');

        $this->assertSame('https://example.com/case/my-case/', $this->link($this->makePost()));
    }

    public function testTheStructureDecidesTheTrailingSlash(): void
    {
        $this->registerCase('/%postname%');

        $this->assertSame('https://example.com/case/my-case', $this->link($this->makePost()));
    }

    public function testPostId(): void
    {
        $this->registerCase('/%post_id%/');

        $this->assertSame('https://example.com/case/42/', $this->link($this->makePost(['ID' => 42])));
    }

    public function testLiteralText(): void
    {
        $this->registerCase('/n-%post_id%.html');

        $this->assertSame('https://example.com/case/n-42.html', $this->link($this->makePost(['ID' => 42])));
    }

    public function testDateTagsComeFromThePostDate(): void
    {
        $this->registerCase('/%year%/%monthnum%/%day%/%hour%%minute%%second%-%postname%/');

        $this->assertSame(
            'https://example.com/case/2024/05/12/090807-my-case/',
            $this->link($this->makePost(['post_date' => '2024-05-12 09:08:07']))
        );
    }

    public function testAPostWithoutADateGetsTheCurrentTime(): void
    {
        $this->registerCase('/%year%/%monthnum%/%postname%/');
        $GLOBALS['__tobiuo_test_now'] = '2026-10-01 12:00:00';

        $this->assertSame('https://example.com/case/2026/10/my-case/', $this->link($this->makePost(['post_date' => '0000-00-00 00:00:00'])));
    }

    public function testAuthor(): void
    {
        $this->registerCase('/%author%/%postname%/');
        $GLOBALS['__tobiuo_test_users'][3] = new \WP_User(3, 'jane');

        $this->assertSame('https://example.com/case/jane/my-case/', $this->link($this->makePost(['post_author' => 3])));
    }

    public function testAMissingAuthorFallsBackToThePlainLink(): void
    {
        $this->registerCase('/%author%/%postname%/');

        $this->assertSame('https://example.com/?case=my-case', $this->link($this->makePost(['post_author' => 99])));
    }

    public function testOneTerm(): void
    {
        $this->registerCase('/%case_category%/%postname%/');
        $post = $this->makePost();
        $this->makeTerm(5, 'consulting');
        $this->assignTerms($post, [5]);

        $this->assertSame('https://example.com/case/consulting/my-case/', $this->link($post));
    }

    public function testAChildTermGetsItsFullPath(): void
    {
        $this->registerCase('/%case_category%/%postname%/');
        $post = $this->makePost();
        $this->makeTerm(5, 'consulting');
        $this->makeTerm(6, 'strategy', parent: 5);
        $this->makeTerm(7, 'growth', parent: 6);
        $this->assignTerms($post, [7]);

        $this->assertSame('https://example.com/case/consulting/strategy/growth/my-case/', $this->link($post));
    }

    public function testAParentAssignedWithItsChildIsSkipped(): void
    {
        $this->registerCase('/%case_category%/%postname%/');
        $post = $this->makePost();
        $this->makeTerm(5, 'consulting');
        $this->makeTerm(9, 'strategy', parent: 5);
        $this->assignTerms($post, [5, 9]);

        $this->assertSame('https://example.com/case/consulting/strategy/my-case/', $this->link($post));
    }

    public function testAnAncestorAssignedWithAGrandchildIsSkipped(): void
    {
        $this->registerCase('/%case_category%/%postname%/');
        $post = $this->makePost();
        $this->makeTerm(5, 'consulting');
        $this->makeTerm(6, 'strategy', parent: 5);
        $this->makeTerm(9, 'growth', parent: 6);
        $this->assignTerms($post, [5, 9]);

        $this->assertSame('https://example.com/case/consulting/strategy/growth/my-case/', $this->link($post));
    }

    public function testUnrelatedTermsGiveTheLowestTermId(): void
    {
        $this->registerCase('/%case_category%/%postname%/');
        $post = $this->makePost();
        $this->makeTerm(8, 'design');
        $this->makeTerm(4, 'branding');
        $this->makeTerm(6, 'web');
        $this->assignTerms($post, [8, 4, 6]);

        $this->assertSame('https://example.com/case/branding/my-case/', $this->link($post));
    }

    public function testTheMostSpecificTermWinsOverALowerTermId(): void
    {
        $this->registerCase('/%case_category%/%postname%/');
        $post = $this->makePost();
        $this->makeTerm(2, 'consulting');
        $this->makeTerm(3, 'design');
        $this->makeTerm(10, 'strategy', parent: 2);
        $this->assignTerms($post, [2, 3, 10]);

        // 2 is an ancestor of 10; of 3 and 10, 3 is lower
        $this->assertSame('https://example.com/case/design/my-case/', $this->link($post));
    }

    public function testATermParentLoopDoesNotHang(): void
    {
        $this->registerCase('/%case_category%/%postname%/');
        $post = $this->makePost();
        $this->makeTerm(5, 'a', parent: 6);
        $this->makeTerm(6, 'b', parent: 5);
        $this->assignTerms($post, [5, 6]);

        $this->assertMatchesRegularExpression('#^https://example\.com/case/[ab]/[ab]/my-case/$#', $this->link($post));
    }

    public function testTheFilterCanChooseAnotherTerm(): void
    {
        $this->registerCase('/%case_category%/%postname%/');
        $post = $this->makePost();
        $this->makeTerm(4, 'branding');
        $other = $this->makeTerm(8, 'design');
        $this->assignTerms($post, [4, 8]);

        $seen = null;
        add_filter('tobiuo_post_link_term', function ($term, $terms, $taxonomy, $p) use ($other, &$seen) {
            $seen = [$term->slug, count($terms), $taxonomy, $p->ID];
            return $other;
        }, 10, 4);

        $this->assertSame('https://example.com/case/design/my-case/', $this->link($post));
        $this->assertSame(['branding', 2, 'case_category', 1], $seen);
    }

    public function testTheFilterCanSupplyATermWhenThePostHasNone(): void
    {
        $this->registerCase('/%case_category%/%postname%/');
        $term = $this->makeTerm(4, 'branding');
        add_filter('tobiuo_post_link_term', fn () => $term);

        $this->assertSame('https://example.com/case/branding/my-case/', $this->link($this->makePost()));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function wrongFilterValues(): array
    {
        return [
            'null'        => [null],
            'false'       => [false],
            'slug'        => ['design'],
            'term id'     => [8],
            'array'       => [['slug' => 'design']],
        ];
    }

    /**
     * @dataProvider wrongFilterValues
     */
    public function testAWrongFilterValueFallsBack(mixed $value): void
    {
        $this->registerCase('/%case_category%/%postname%/');
        $post = $this->makePost();
        $this->makeTerm(4, 'branding');
        $this->makeTerm(8, 'design');
        $this->assignTerms($post, [4, 8]);
        add_filter('tobiuo_post_link_term', fn () => $value);

        $this->assertSame('https://example.com/case/branding/my-case/', $this->link($post));
    }

    public function testATermOfAnotherTaxonomyFromTheFilterFallsBack(): void
    {
        $this->registerCase('/%case_category%/%postname%/');
        $post = $this->makePost();
        $this->makeTerm(4, 'branding');
        $tag = $this->makeTerm(8, 'design', 'post_tag');
        $this->assignTerms($post, [4]);
        add_filter('tobiuo_post_link_term', fn () => $tag);

        $this->assertSame('https://example.com/case/branding/my-case/', $this->link($post));
    }

    public function testTheDefaultTermIsUsedWhenThePostHasNone(): void
    {
        $this->registerCase('/%case_category%/%postname%/');
        $this->makeTerm(1, 'uncategorized');
        $GLOBALS['__tobiuo_test_options']['default_term_case_category'] = '1';

        $this->assertSame('https://example.com/case/uncategorized/my-case/', $this->link($this->makePost()));
    }

    public function testAMissingDefaultTermFallsBackToThePlainLink(): void
    {
        $this->registerCase('/%case_category%/%postname%/');
        $GLOBALS['__tobiuo_test_options']['default_term_case_category'] = 99;

        $this->assertSame('https://example.com/?case=my-case', $this->link($this->makePost()));
    }

    public function testCategoriesUseDefaultCategory(): void
    {
        $this->makeTerm(1, 'uncategorized', 'category');
        $GLOBALS['__tobiuo_test_options']['default_category'] = 1;

        $this->assertSame('uncategorized', Permalink::defaultTerm('category')?->slug);
        $this->assertNull(Permalink::defaultTerm('case_category'));
    }

    public function testNoTermGivesThePlainQueryVarLink(): void
    {
        $this->registerCase('/%case_category%/%postname%/');

        $link = $this->link($this->makePost());

        $this->assertSame('https://example.com/?case=my-case', $link);
        $this->assertStringNotContainsString('%', $link);
    }

    public function testNoTermAndNoQueryVarGivesThePostTypeAndIdLink(): void
    {
        $this->registerCase('/%case_category%/%postname%/', ['query_var' => false]);

        $this->assertSame('https://example.com/?post_type=case&p=1', $this->link($this->makePost()));
    }

    public function testHierarchicalPostTypesGetTheirParents(): void
    {
        $this->registerCase('/%postname%/', ['hierarchical' => true]);
        $this->makePost(['ID' => 10, 'post_name' => 'parent']);
        $this->makePost(['ID' => 11, 'post_name' => 'child', 'post_parent' => 10]);
        $post = $this->makePost(['ID' => 12, 'post_name' => 'grandchild', 'post_parent' => 11]);

        $this->assertSame('https://example.com/case/parent/child/grandchild/', $this->link($post));
    }

    public function testHierarchicalPlainLinksGetTheirParentsToo(): void
    {
        $this->registerCase('/%case_category%/%postname%/', ['hierarchical' => true]);
        $this->makePost(['ID' => 10, 'post_name' => 'parent']);
        $post = $this->makePost(['ID' => 12, 'post_name' => 'child', 'post_parent' => 10]);

        $this->assertSame('https://example.com/?case=parent%2Fchild', $this->link($post));
    }

    public function testAParentLoopDoesNotHang(): void
    {
        $this->registerCase('/%postname%/', ['hierarchical' => true]);
        $this->makePost(['ID' => 10, 'post_name' => 'a', 'post_parent' => 11]);
        $post = $this->makePost(['ID' => 11, 'post_name' => 'b', 'post_parent' => 10]);

        $this->assertSame('https://example.com/case/a/b/', $this->link($post));
    }

    public function testAMissingParentEndsThePath(): void
    {
        $this->registerCase('/%postname%/', ['hierarchical' => true]);
        $post = $this->makePost(['ID' => 11, 'post_name' => 'b', 'post_parent' => 404]);

        $this->assertSame('https://example.com/case/b/', $this->link($post));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonViewableStatuses(): array
    {
        return [
            'draft'      => ['draft'],
            'pending'    => ['pending'],
            'auto-draft' => ['auto-draft'],
            'future'     => ['future'],
        ];
    }

    /**
     * @dataProvider nonViewableStatuses
     */
    public function testNonViewablePostsKeepCoresLink(string $status): void
    {
        $this->registerCase('/%case_category%/%postname%/');
        $coreLink = 'https://example.com/?post_type=case&p=1';

        $this->assertSame($coreLink, Permalink::filterLink($coreLink, $this->makePost(['post_status' => $status])));
    }

    public function testTheSamplePermalinkKeepsThePostTypeTag(): void
    {
        $this->registerCase('/%case_category%/%postname%/', ['hierarchical' => true]);
        $this->makePost(['ID' => 10, 'post_name' => 'parent']);
        $post = $this->makePost(['ID' => 12, 'post_name' => 'child', 'post_parent' => 10, 'post_status' => 'draft']);
        $this->makeTerm(5, 'consulting');
        $this->assignTerms($post, [5]);

        // Core's get_sample_permalink() puts the parents in front of %case% itself.
        $this->assertSame('https://example.com/case/consulting/%case%/', Permalink::filterLink(self::CORE_LINK, $post, true, true));
    }

    public function testLeavenameWithoutSampleStillBuildsThePath(): void
    {
        $this->registerCase('/%post_id%/%postname%/');

        $this->assertSame('https://example.com/case/1/%case%/', Permalink::filterLink(self::CORE_LINK, $this->makePost(), true));
    }

    public function testPlainPermalinksKeepCoresLink(): void
    {
        $this->useRewrite('');
        $this->registerCase('/%postname%/');

        $this->assertSame('https://example.com/?case=my-case', Permalink::filterLink('https://example.com/?case=my-case', $this->makePost()));
    }

    public function testPostTypesWithoutPermalinkConfigKeepCoresLink(): void
    {
        Registry::registerPostType(new PostTypeConfig(name: 'news'));
        Registration::handOver();

        $this->assertSame('https://example.com/news/a/', Permalink::filterLink('https://example.com/news/a/', $this->makePost(['post_type' => 'news'])));
        $this->assertSame('https://example.com/b/', Permalink::filterLink('https://example.com/b/', $this->makePost(['post_type' => 'post'])));
    }

    public function testOtherArgumentsPassThrough(): void
    {
        $this->registerCase('/%postname%/');

        $this->assertNull(Permalink::filterLink(null, $this->makePost()));
        $this->assertSame('x', Permalink::filterLink('x', 'not a post'));
    }

    public function testWithFrontAddsTheFront(): void
    {
        $this->useRewrite('/blog/%postname%/');
        $this->registerCase('/%postname%/', ['rewrite' => ['slug' => 'case', 'with_front' => true]]);

        $this->assertSame('https://example.com/blog/case/my-case/', $this->link($this->makePost()));
    }

    public function testWithoutFrontLeavesItOut(): void
    {
        $this->useRewrite('/blog/%postname%/');
        $this->registerCase('/%postname%/');

        $this->assertSame('https://example.com/case/my-case/', $this->link($this->makePost()));
    }

    public function testPathinfoPermalinksKeepIndexPhp(): void
    {
        $rewrite = $this->useRewrite('/index.php/%postname%/');
        $rewrite->root = 'index.php/';
        $this->registerCase('/%postname%/');

        $this->assertSame('https://example.com/index.php/case/my-case/', $this->link($this->makePost()));
    }

    public function testANestedSlug(): void
    {
        $this->registerCase('/%postname%/', ['rewrite' => ['slug' => 'works/case', 'with_front' => false]]);

        $this->assertSame('https://example.com/works/case/my-case/', $this->link($this->makePost()));
    }

    public function testASubdirectoryInstall(): void
    {
        $GLOBALS['__tobiuo_test_home_url'] = 'https://example.com/wp';
        $this->registerCase('/%postname%/');

        $this->assertSame('https://example.com/wp/case/my-case/', $this->link($this->makePost()));
    }

    public function testBuildPathNeedsEveryTag(): void
    {
        $permalink = new PermalinkConfig(structure: '/%case_category%/%postname%/');

        $this->assertSame('/case/a/b/', Permalink::buildPath('case', $permalink, ['%case_category%' => 'a', '%postname%' => 'b']));
        $this->assertNull(Permalink::buildPath('case', $permalink, ['%postname%' => 'b']));
        $this->assertNull(Permalink::buildPath('case', $permalink, ['%case_category%' => '', '%postname%' => 'b']));
    }

    public function testBuildPathReplacesEachTagOnce(): void
    {
        $permalink = new PermalinkConfig(structure: '/%post_id%/%postname%/');

        $this->assertSame('/case/%postname%/x/', Permalink::buildPath('case', $permalink, ['%post_id%' => '%postname%', '%postname%' => 'x']));
    }

    public function testChooseTerm(): void
    {
        $a = $this->makeTerm(3, 'a');
        $b = $this->makeTerm(2, 'b');
        $c = $this->makeTerm(9, 'c', parent: 2);

        $this->assertNull(Permalink::chooseTerm([], []));
        $this->assertSame($b, Permalink::chooseTerm([$a, $b], []));
        $this->assertSame($a, Permalink::chooseTerm([$a, $b, $c], [9 => [2]]));
        $this->assertSame($c, Permalink::chooseTerm([$b, $c], [9 => [2]]));
    }

    public function testDateParts(): void
    {
        $this->assertSame([
            '%year%'     => '2024',
            '%monthnum%' => '05',
            '%day%'      => '12',
            '%hour%'     => '09',
            '%minute%'   => '08',
            '%second%'   => '07',
        ], Permalink::dateParts('2024-05-12 09:08:07'));

        $GLOBALS['__tobiuo_test_now'] = '2026-10-01 12:34:56';
        $this->assertSame('2026', Permalink::dateParts('')['%year%']);
        $this->assertSame('2026', Permalink::dateParts('0000-00-00 00:00:00')['%year%']);
    }

    /**
     * Register `case` with `case_category`, the way a theme would.
     *
     * @param array<string, mixed> $args Merged over public + archive + rewrite slug 'case' without front.
     */
    private function registerCase(string $structure, array $args = []): void
    {
        Registry::registerTaxonomy(new TaxonomyConfig(name: 'case_category', objectTypes: ['case']));
        Registry::registerPostType(new PostTypeConfig(
            name: 'case',
            args: $args + ['public' => true, 'has_archive' => true, 'rewrite' => ['slug' => 'case', 'with_front' => false]],
            permalink: new PermalinkConfig(structure: $structure),
        ));
        Registration::handOver();
    }

    private function link(\WP_Post $post): string
    {
        return (string) Permalink::filterLink(self::CORE_LINK, $post, false, false);
    }
}
