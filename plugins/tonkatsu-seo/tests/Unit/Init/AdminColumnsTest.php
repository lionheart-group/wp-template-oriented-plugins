<?php

namespace TonkatsuPlugin\Tests\Unit\Init;

use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Init\AdminColumns;
use TonkatsuPlugin\Models\Context;
use TonkatsuPlugin\Models\Resolver;
use TonkatsuPlugin\Structure\PageConfig;
use TonkatsuPlugin\Structure\SiteConfig;
use TonkatsuPlugin\Tests\Unit\BaseTestCase;

class AdminColumnsTest extends BaseTestCase
{
    private function page(array $overrides = []): Context
    {
        return new Context(...array_merge([
            'type'     => Context::TYPE_SINGULAR,
            'path'     => 'about',
            'url'      => 'https://example.com/about/',
            'object'   => $this->makePost(['ID' => 7]),
            'postType' => 'page',
            'title'    => 'About',
        ], $overrides));
    }

    /**
     * @return array<string, array{value: string, note: ?string}>
     */
    private function cells(Context $context, bool $discouraged = false): array
    {
        return AdminColumns::cells(Resolver::fromContext($context), $context, $discouraged);
    }

    // ------------------------------------------------------------------
    // Columns
    // ------------------------------------------------------------------

    public function testColumnsAreInsertedAfterTheTitle(): void
    {
        $columns = AdminColumns::addColumns(['cb' => '', 'title' => 'Title', 'date' => 'Date']);

        $this->assertSame(
            ['cb', 'title', AdminColumns::COLUMN_TITLE, AdminColumns::COLUMN_DESCRIPTION, AdminColumns::COLUMN_ROBOTS, 'date'],
            array_keys($columns)
        );
    }

    public function testColumnsAreAppendedWhenThereIsNoTitleColumn(): void
    {
        $columns = AdminColumns::addColumns(['cb' => '', 'date' => 'Date']);

        $this->assertSame(
            ['cb', 'date', AdminColumns::COLUMN_TITLE, AdminColumns::COLUMN_DESCRIPTION, AdminColumns::COLUMN_ROBOTS],
            array_keys($columns)
        );
    }

    public function testPostTypesDefaultToPages(): void
    {
        $this->assertSame(['page'], AdminColumns::postTypes());
    }

    public function testPostTypesCanBeFiltered(): void
    {
        add_filter('tonkatsu_admin_column_post_types', fn () => ['page', 'post', '', 3]);

        $this->assertSame(['page', 'post'], AdminColumns::postTypes());
    }

    public function testUnusablePostTypesFilterFallsBackToPages(): void
    {
        add_filter('tonkatsu_admin_column_post_types', fn () => 'post');

        $this->assertSame(['page'], AdminColumns::postTypes());
    }

    // ------------------------------------------------------------------
    // Cells
    // ------------------------------------------------------------------

    public function testTitleIsTheFinishedTitleTagBuiltFromThePageTitle(): void
    {
        $cell = $this->cells($this->page())[AdminColumns::COLUMN_TITLE];

        $this->assertSame('About | Example Site', $cell['value']);
        $this->assertSame(AdminColumns::badge(AdminColumns::TONE_INFO, 'From the page title'), $cell['badge']);
    }

    public function testConfiguredTitleAndSeparatorAreApplied(): void
    {
        Seo::setSite(new SiteConfig(separator: '-'));
        Seo::registerPage('about', new PageConfig(title: 'About us'));

        $cell = $this->cells($this->page())[AdminColumns::COLUMN_TITLE];

        $this->assertSame('About us - Example Site', $cell['value']);
        $this->assertNull($cell['badge']);
    }

    public function testFrontPageTitleIsTheSiteName(): void
    {
        Seo::setSite(new SiteConfig(siteName: 'Example'));

        $cell = $this->cells($this->page(['type' => Context::TYPE_FRONT, 'path' => '']))[AdminColumns::COLUMN_TITLE];

        $this->assertSame('Example', $cell['value']);
        $this->assertSame(AdminColumns::badge(AdminColumns::TONE_INFO, 'From the site name'), $cell['badge']);
    }

    public function testMissingDescriptionIsShownAsNotOutput(): void
    {
        $cell = $this->cells($this->page())[AdminColumns::COLUMN_DESCRIPTION];

        $this->assertSame('—', $cell['value']);
        $this->assertSame(AdminColumns::badge(AdminColumns::TONE_WARNING, 'Not output'), $cell['badge']);
    }

    public function testSiteDefaultDescriptionIsNoted(): void
    {
        Seo::setSite(new SiteConfig(defaultDescription: 'Site-wide.'));

        $cell = $this->cells($this->page())[AdminColumns::COLUMN_DESCRIPTION];

        $this->assertSame('Site-wide.', $cell['value']);
        $this->assertSame(AdminColumns::badge(AdminColumns::TONE_INFO, 'Site default'), $cell['badge']);
    }

    public function testPageDescriptionHasNoNote(): void
    {
        Seo::setSite(new SiteConfig(defaultDescription: 'Site-wide.'));
        Seo::registerPage('about', new PageConfig(description: 'About the company.'));

        $cell = $this->cells($this->page())[AdminColumns::COLUMN_DESCRIPTION];

        $this->assertSame('About the company.', $cell['value']);
        $this->assertNull($cell['badge']);
    }

    public function testRobotsFollowTheResolvedValues(): void
    {
        Seo::registerPage('about', new PageConfig(noindex: true));

        $cell = $this->cells($this->page())[AdminColumns::COLUMN_ROBOTS];

        $this->assertSame('noindex, follow', $cell['value']);
        $this->assertNull($cell['badge']);
    }

    /**
     * Core prints noindex, nofollow everywhere while the Reading setting is
     * on, so the column must not claim a page is indexable.
     */
    public function testDiscouragedSearchEnginesOverrideRobots(): void
    {
        $cell = $this->cells($this->page(), true)[AdminColumns::COLUMN_ROBOTS];

        $this->assertSame('noindex, nofollow', $cell['value']);
        $this->assertSame(AdminColumns::badge(AdminColumns::TONE_WARNING, 'Search engines discouraged'), $cell['badge']);
    }

    public function testCellHtmlEscapesValueAndBadge(): void
    {
        $this->assertSame(
            '&lt;b&gt;<br><span class="tonkatsu-badge tonkatsu-badge--warning">&lt;i&gt;</span>',
            AdminColumns::cellHtml(['value' => '<b>', 'badge' => AdminColumns::badge(AdminColumns::TONE_WARNING, '<i>')])
        );
        $this->assertSame('plain', AdminColumns::cellHtml(['value' => 'plain', 'badge' => null]));
    }

    public function testUnknownBadgeToneFallsBackToInfo(): void
    {
        $this->assertSame(
            '<span class="tonkatsu-badge tonkatsu-badge--info">x</span>',
            AdminColumns::badgeHtml(['tone' => '" onmouseover="', 'label' => 'x'])
        );
    }

    public function testSeoScreensAreTheTonkatsuPageAndColumnListScreens(): void
    {
        $this->assertTrue(AdminColumns::isSeoScreen('tools_page_tonkatsu-seo', 'tools_page_tonkatsu-seo', ''));
        $this->assertTrue(AdminColumns::isSeoScreen('edit-page', 'edit', 'page'));
        $this->assertFalse(AdminColumns::isSeoScreen('edit-post', 'edit', 'post'));
        $this->assertFalse(AdminColumns::isSeoScreen('dashboard', 'dashboard', ''));
    }
}
