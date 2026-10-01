<?php

namespace TonkatsuPlugin\Tests\Unit\Init;

use TonkatsuPlugin\Init\Conflict;
use TonkatsuPlugin\Tests\Unit\BaseTestCase;

class ConflictTest extends BaseTestCase
{
    public function testNoConflictByDefault(): void
    {
        $this->assertNull(Conflict::detect());
    }

    public function testDetectsADefinedConstant(): void
    {
        if (!defined('TONKATSU_TEST_OTHER_SEO_VERSION')) {
            define('TONKATSU_TEST_OTHER_SEO_VERSION', '1.0.0');
        }

        $this->assertSame('Other SEO', Conflict::detect([
            'TONKATSU_TEST_UNDEFINED_SEO_VERSION' => 'Undefined SEO',
            'TONKATSU_TEST_OTHER_SEO_VERSION'     => 'Other SEO',
        ]));
    }

    public function testKnownPluginsAreListed(): void
    {
        $plugins = (new \ReflectionClassConstant(\TonkatsuPlugin\Consts::class, 'CONFLICTING_PLUGINS'))->getValue();

        foreach (['RANK_MATH_VERSION', 'WPSEO_VERSION', 'AIOSEO_VERSION', 'SEOPRESS_VERSION'] as $constant) {
            $this->assertArrayHasKey($constant, $plugins);
        }
    }

    public function testNoticeIsShownOnThePluginsAndTonkatsuScreensOnly(): void
    {
        $this->assertTrue(Conflict::shouldShowOn('plugins'));
        $this->assertTrue(Conflict::shouldShowOn('tools_page_tonkatsu-seo'));

        $this->assertFalse(Conflict::shouldShowOn('dashboard'));
        $this->assertFalse(Conflict::shouldShowOn('edit-page'));
        $this->assertFalse(Conflict::shouldShowOn('options-general'));
    }
}
