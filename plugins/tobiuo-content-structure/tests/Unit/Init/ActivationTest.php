<?php

namespace TobiuoPlugin\Tests\Unit\Init;

use TobiuoPlugin\Init\Activation;
use TobiuoPlugin\Tests\Unit\BaseTestCase;

class ActivationTest extends BaseTestCase
{
    public function testResetDeletesTheStoredRulesOnly(): void
    {
        $GLOBALS['__tobiuo_test_options'] = [
            'rewrite_rules'       => ['case/?$' => 'index.php?post_type=case'],
            'permalink_structure' => '/%postname%/',
        ];

        Activation::resetRewriteRules();

        $this->assertSame(['permalink_structure' => '/%postname%/'], $GLOBALS['__tobiuo_test_options']);
    }
}
