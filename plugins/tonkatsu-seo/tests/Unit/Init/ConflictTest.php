<?php

namespace ToroPlugin\Tests\Unit\Init;

use ToroPlugin\Init\Conflict;
use ToroPlugin\Tests\Unit\BaseTestCase;

class ConflictTest extends BaseTestCase
{
    public function testNoConflictByDefault(): void
    {
        $this->assertNull(Conflict::detect());
    }

    public function testDetectsADefinedConstant(): void
    {
        if (!defined('TORO_TEST_OTHER_SEO_VERSION')) {
            define('TORO_TEST_OTHER_SEO_VERSION', '1.0.0');
        }

        $this->assertSame('Other SEO', Conflict::detect([
            'TORO_TEST_UNDEFINED_SEO_VERSION' => 'Undefined SEO',
            'TORO_TEST_OTHER_SEO_VERSION'     => 'Other SEO',
        ]));
    }

    public function testKnownPluginsAreListed(): void
    {
        $plugins = (new \ReflectionClassConstant(\ToroPlugin\Consts::class, 'CONFLICTING_PLUGINS'))->getValue();

        foreach (['RANK_MATH_VERSION', 'WPSEO_VERSION', 'AIOSEO_VERSION', 'SEOPRESS_VERSION'] as $constant) {
            $this->assertArrayHasKey($constant, $plugins);
        }
    }
}
