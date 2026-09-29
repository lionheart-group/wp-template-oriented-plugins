<?php

namespace ToroPlugin\Tests\Unit\Init;

use ToroPlugin\Helpers\Visibility;
use ToroPlugin\Init\SearchVisibilityNotice;
use ToroPlugin\Tests\Unit\BaseTestCase;

class SearchVisibilityNoticeTest extends BaseTestCase
{
    public function testDiscouragedOnlyWhenBlogPublicIsZero(): void
    {
        $GLOBALS['__toro_test_options']['blog_public'] = '0';
        $this->assertTrue(Visibility::searchEnginesDiscouraged());

        $GLOBALS['__toro_test_options']['blog_public'] = '1';
        $this->assertFalse(Visibility::searchEnginesDiscouraged());

        // Some installs store it as an integer.
        $GLOBALS['__toro_test_options']['blog_public'] = 0;
        $this->assertTrue(Visibility::searchEnginesDiscouraged());
    }

    public function testShownOnDashboardTheToroPageAndListScreensWithColumns(): void
    {
        $this->assertTrue(SearchVisibilityNotice::shouldShowOn('dashboard', 'dashboard', ''));
        $this->assertTrue(SearchVisibilityNotice::shouldShowOn('tools_page_toro-seo', 'tools_page_toro-seo', ''));
        $this->assertTrue(SearchVisibilityNotice::shouldShowOn('edit-page', 'edit', 'page'));
    }

    public function testNotShownElsewhere(): void
    {
        $this->assertFalse(SearchVisibilityNotice::shouldShowOn('edit-post', 'edit', 'post'));
        $this->assertFalse(SearchVisibilityNotice::shouldShowOn('page', 'post', 'page'));
        $this->assertFalse(SearchVisibilityNotice::shouldShowOn('options-general', 'options-general', ''));
    }

    public function testFollowsTheColumnPostTypesFilter(): void
    {
        add_filter('toro_admin_column_post_types', fn () => ['page', 'post']);

        $this->assertTrue(SearchVisibilityNotice::shouldShowOn('edit-post', 'edit', 'post'));
    }
}
