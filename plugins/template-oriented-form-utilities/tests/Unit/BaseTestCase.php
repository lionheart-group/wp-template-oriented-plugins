<?php

namespace TofuPlugin\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TofuPlugin\Helpers\Session;

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
        $GLOBALS['__tofu_setcookie_calls'] = [];
        unset($GLOBALS['__tofu_wp_mail_result']);
        $this->resetSessionCookieState();
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
        $GLOBALS['__tofu_setcookie_calls'] = [];
        unset($GLOBALS['__tofu_wp_mail_result']);
        $this->resetSessionCookieState();
        parent::tearDown();
    }

    /**
     * Reset Helpers\Session's per-request statics.
     *
     * $cookieIssued in particular must not survive between tests: any test
     * that calls Session::save() (directly, or via processInput()/
     * processConfirm()) flips it permanently for the rest of the PHPUnit
     * process otherwise, silently suppressing every Set-Cookie a later test
     * expects to see.
     */
    private function resetSessionCookieState(): void
    {
        $cookieIssued = new \ReflectionProperty(Session::class, 'cookieIssued');
        $cookieIssued->setAccessible(true);
        $cookieIssued->setValue(null, false);

        $corsMode = new \ReflectionProperty(Session::class, 'corsMode');
        $corsMode->setAccessible(true);
        $corsMode->setValue(null, false);
    }
}
