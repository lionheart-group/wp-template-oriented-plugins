<?php

namespace TonkatsuPlugin\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TonkatsuPlugin\Helpers\Seo;
use TonkatsuPlugin\Init\Head;
use TonkatsuPlugin\Init\Redirects;
use TonkatsuPlugin\Init\Sitemap;

/**
 * Base test case class for TonkatsuPlugin tests
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
        $GLOBALS['__tonkatsu_hooks'] = [];
        $GLOBALS['wpdb'] = new \TonkatsuTestWpdb();
        unset(
            $GLOBALS['__tonkatsu_test_locale'],
            $GLOBALS['__tonkatsu_test_home_url'],
            $GLOBALS['__tonkatsu_test_options'],
            $GLOBALS['__tonkatsu_test_permalinks'],
            $GLOBALS['__tonkatsu_test_sitemap_server']
        );

        $statics = [
            [Seo::class, 'site', null],
            [Seo::class, 'pages', []],
            [Seo::class, 'archives', []],
            [Seo::class, 'taxonomies', []],
            [Seo::class, 'redirects', []],
            [Head::class, 'resolver', null],
            [Redirects::class, 'redirecting', false],
            [Sitemap::class, 'excludedPostIds', []],
            [Sitemap::class, 'buildingUrlList', false],
        ];

        foreach ($statics as [$class, $property, $value]) {
            $reflection = new \ReflectionProperty($class, $property);
            $reflection->setAccessible(true);
            $reflection->setValue(null, $value);
        }
    }
}
