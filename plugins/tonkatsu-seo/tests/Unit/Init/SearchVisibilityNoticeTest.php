<?php

namespace TonkatsuPlugin\Tests\Unit\Init;

use TonkatsuPlugin\Helpers\Visibility;
use TonkatsuPlugin\Init\SearchVisibilityNotice;
use TonkatsuPlugin\Tests\Unit\BaseTestCase;

class SearchVisibilityNoticeTest extends BaseTestCase
{
    public function testDiscouragedOnlyWhenBlogPublicIsZero(): void
    {
        $GLOBALS['__tonkatsu_test_options']['blog_public'] = '0';
        $this->assertTrue(Visibility::searchEnginesDiscouraged());

        $GLOBALS['__tonkatsu_test_options']['blog_public'] = '1';
        $this->assertFalse(Visibility::searchEnginesDiscouraged());

        // Some installs store it as an integer.
        $GLOBALS['__tonkatsu_test_options']['blog_public'] = 0;
        $this->assertTrue(Visibility::searchEnginesDiscouraged());
    }

    public function testShownOnTheTonkatsuPageAndListScreensWithColumns(): void
    {
        $this->assertTrue(SearchVisibilityNotice::shouldShowOn('tools_page_tonkatsu-seo', 'tools_page_tonkatsu-seo', ''));
        $this->assertTrue(SearchVisibilityNotice::shouldShowOn('edit-page', 'edit', 'page'));
    }

    public function testNotShownElsewhere(): void
    {
        // Guideline 11: the notice must not take over the whole admin, dashboard included.
        $this->assertFalse(SearchVisibilityNotice::shouldShowOn('dashboard', 'dashboard', ''));
        $this->assertFalse(SearchVisibilityNotice::shouldShowOn('edit-post', 'edit', 'post'));
        $this->assertFalse(SearchVisibilityNotice::shouldShowOn('page', 'post', 'page'));
        $this->assertFalse(SearchVisibilityNotice::shouldShowOn('options-general', 'options-general', ''));
    }

    public function testFollowsTheColumnPostTypesFilter(): void
    {
        add_filter('tonkatsu_admin_column_post_types', fn () => ['page', 'post']);

        $this->assertTrue(SearchVisibilityNotice::shouldShowOn('edit-post', 'edit', 'post'));
    }
}
