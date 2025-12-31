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
    }

    /**
     * Cleanup after each test
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
