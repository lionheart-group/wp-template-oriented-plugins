<?php

namespace TobiuoPlugin\Tests\Unit\Init;

use TobiuoPlugin\Helpers\Registry;
use TobiuoPlugin\Init\AdminPage;
use TobiuoPlugin\Init\Registration;
use TobiuoPlugin\Structure\PermalinkConfig;
use TobiuoPlugin\Structure\PostTypeConfig;
use TobiuoPlugin\Tests\Unit\BaseTestCase;

class AdminPageTest extends BaseTestCase
{
    public function testCapabilityDefaultsToManageOptions(): void
    {
        $this->assertSame('manage_options', AdminPage::capability());
    }

    public function testCapabilityCanBeFiltered(): void
    {
        add_filter('tobiuo_admin_page_capability', fn () => 'edit_pages');

        $this->assertSame('edit_pages', AdminPage::capability());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function wrongCapabilities(): array
    {
        return [
            'empty'  => [''],
            'null'   => [null],
            'array'  => [['edit_pages']],
            'true'   => [true],
        ];
    }

    /**
     * @dataProvider wrongCapabilities
     */
    public function testAWrongCapabilityFallsBack(mixed $value): void
    {
        add_filter('tobiuo_admin_page_capability', fn () => $value);

        $this->assertSame('manage_options', AdminPage::capability());
    }

    public function testScreenId(): void
    {
        $this->assertSame('tools_page_tobiuo-content-structure', AdminPage::screenId());
    }

    public function testRegisterHooksTheMenuAndTheStyles(): void
    {
        AdminPage::register();

        $this->assertSame(10, has_action('admin_menu', [AdminPage::class, 'addMenuPage']));
        $this->assertSame(10, has_action('admin_enqueue_scripts', [AdminPage::class, 'enqueueStyles']));
    }

    public function testBadgeHtmlIsEscapedAndSurvivesKses(): void
    {
        $html = AdminPage::badgeHtml(AdminPage::TONE_WARNING, '<b>3 rules</b> missing');

        $this->assertSame('<span class="tobiuo-badge tobiuo-badge--warning">&lt;b&gt;3 rules&lt;/b&gt; missing</span>', $html);
        $this->assertSame($html, wp_kses($html, AdminPage::ALLOWED_HTML));
    }

    public function testAnUnknownToneIsInfo(): void
    {
        $this->assertStringContainsString('tobiuo-badge--info', AdminPage::badgeHtml('"><script>', 'x'));
    }

    public function testStructureForDisplay(): void
    {
        $this->useRewrite('/blog/%postname%/');
        Registry::registerPostType(new PostTypeConfig(
            name: 'case',
            args: ['rewrite' => ['slug' => 'case', 'with_front' => true]],
            permalink: new PermalinkConfig(structure: '/%post_id%/'),
        ));
        Registry::registerPostType(new PostTypeConfig(name: 'news'));
        Registration::handOver();

        $this->assertSame('/blog/case/%post_id%/', AdminPage::structureForDisplay(get_post_type_object('case')));
        $this->assertSame('', AdminPage::structureForDisplay(get_post_type_object('news')));
    }
}
