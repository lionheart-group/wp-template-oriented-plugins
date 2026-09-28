<?php

namespace ToroPlugin\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ToroPlugin\Helpers\Seo;
use ToroPlugin\Init\Head;
use ToroPlugin\Init\Sitemap;

/**
 * Base test case class for ToroPlugin tests
 */
abstract class BaseTestCase extends TestCase
{
    /**
     * Setup before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->reset();
    }

    /**
     * Cleanup after each test
     */
    protected function tearDown(): void
    {
        // Callbacks and registrations made by a test must not leak into the next one.
        $this->reset();
        parent::tearDown();
    }

    /**
     * Build a WP_Post stand-in (see tests/bootstrap.php).
     *
     * @param array<string, mixed> $fields
     */
    protected function makePost(array $fields = []): \WP_Post
    {
        return new \WP_Post((object) array_merge(['ID' => 1, 'post_type' => 'page'], $fields));
    }

    /**
     * Reset the hook registry and every per-request static.
     *
     * Seo is a registry that lives for the whole request by design, so
     * without this the first test's registerPage() would make every later
     * one hit the duplicate-registration guard.
     */
    private function reset(): void
    {
        $GLOBALS['__toro_hooks'] = [];
        unset($GLOBALS['__toro_test_locale'], $GLOBALS['__toro_test_home_url']);

        $statics = [
            [Seo::class, 'site', null],
            [Seo::class, 'pages', []],
            [Seo::class, 'archives', []],
            [Seo::class, 'taxonomies', []],
            [Head::class, 'resolver', null],
            [Sitemap::class, 'excludedPostIds', []],
        ];

        foreach ($statics as [$class, $property, $value]) {
            $reflection = new \ReflectionProperty($class, $property);
            $reflection->setAccessible(true);
            $reflection->setValue(null, $value);
        }
    }
}
