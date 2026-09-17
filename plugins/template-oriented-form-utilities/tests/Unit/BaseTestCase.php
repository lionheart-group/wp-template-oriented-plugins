<?php

namespace TofuPlugin\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Base test case class for TofuPlugin tests
 */
abstract class BaseTestCase extends TestCase
{
    /**
     * Setup before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__tofu_hooks'] = [];
        $GLOBALS['__tofu_wp_mail_calls'] = [];
        $GLOBALS['__tofu_redirects'] = [];
        unset($GLOBALS['__tofu_wp_mail_result']);
    }

    /**
     * Cleanup after each test
     */
    protected function tearDown(): void
    {
        // Callbacks registered by a test must not leak into the next one.
        $GLOBALS['__tofu_hooks'] = [];
        $GLOBALS['__tofu_wp_mail_calls'] = [];
        $GLOBALS['__tofu_redirects'] = [];
        unset($GLOBALS['__tofu_wp_mail_result']);
        parent::tearDown();
    }
}
