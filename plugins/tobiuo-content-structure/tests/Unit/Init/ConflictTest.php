<?php

namespace TobiuoPlugin\Tests\Unit\Init;

use TobiuoPlugin\Init\Conflict;
use TobiuoPlugin\Tests\Unit\BaseTestCase;

class ConflictTest extends BaseTestCase
{
    public function testNoConflictByDefault(): void
    {
        $this->assertNull(Conflict::detect());
    }

    public function testDetectsADefinedConstant(): void
    {
        if (!defined('TOBIUO_TEST_OTHER_PERMALINKS_VERSION')) {
            define('TOBIUO_TEST_OTHER_PERMALINKS_VERSION', '1.0.0');
        }

        $this->assertSame('Other Permalinks', Conflict::detect([
            'TOBIUO_TEST_UNDEFINED_VERSION'        => 'Undefined',
            'TOBIUO_TEST_OTHER_PERMALINKS_VERSION' => 'Other Permalinks',
        ], []));
    }

    public function testDetectsALoadedClass(): void
    {
        $this->assertSame('Other Permalinks', Conflict::detect([], [
            'TobiuoTestUndefinedClass' => 'Undefined',
            self::class                => 'Other Permalinks',
        ]));
    }

    public function testCustomPostTypePermalinksIsListed(): void
    {
        $constants = (new \ReflectionClassConstant(\TobiuoPlugin\Consts::class, 'CONFLICTING_PLUGINS'))->getValue();
        $classes = (new \ReflectionClassConstant(\TobiuoPlugin\Consts::class, 'CONFLICTING_CLASSES'))->getValue();

        $this->assertArrayHasKey('CPTP_VERSION', $constants);
        $this->assertArrayHasKey('CPTP', $classes);
    }

    public function testNoticeIsShownOnThePluginsAndTobiuoScreensOnly(): void
    {
        $this->assertTrue(Conflict::shouldShowOn('plugins'));
        $this->assertTrue(Conflict::shouldShowOn('tools_page_tobiuo-content-structure'));

        $this->assertFalse(Conflict::shouldShowOn('dashboard'));
        $this->assertFalse(Conflict::shouldShowOn('edit-case'));
        $this->assertFalse(Conflict::shouldShowOn('options-permalink'));
    }

    public function testRegisterHooksTheNotice(): void
    {
        Conflict::register();

        $this->assertSame(10, has_action('admin_notices', [Conflict::class, 'renderNotice']));
    }
}
