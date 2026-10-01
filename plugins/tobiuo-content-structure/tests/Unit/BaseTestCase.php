<?php

namespace TobiuoPlugin\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TobiuoPlugin\Helpers\Registry;
use TobiuoPlugin\Init\ArchiveLinks;

/**
 * Base test case class for TobiuoPlugin tests
 */
abstract class BaseTestCase extends TestCase
{
    /**
     * Globals the bootstrap's stubs read; every one is cleared between tests.
     */
    private const TEST_GLOBALS = [
        '__tobiuo_test_home_url',
        '__tobiuo_test_options',
        '__tobiuo_test_now',
        '__tobiuo_test_posts',
        '__tobiuo_test_users',
        '__tobiuo_test_terms',
        '__tobiuo_test_post_terms',
        '__tobiuo_test_permalinks',
        '__tobiuo_test_post_types',
        '__tobiuo_test_taxonomies',
        '__tobiuo_test_rewrite_tags',
        '__tobiuo_test_rewrite_rules',
        '__tobiuo_test_log',
        '__tobiuo_test_registration_errors',
        '__tobiuo_test_conditionals',
        '__tobiuo_test_queried_object',
        '__tobiuo_test_query_vars',
        '__tobiuo_test_redirect',
        'wp_rewrite',
        'wp',
    ];

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
     * Build a WP_Post stand-in (see tests/bootstrap.php) and make get_post() find it.
     *
     * @param array<string, mixed> $fields
     */
    protected function makePost(array $fields = []): \WP_Post
    {
        $post = new \WP_Post((object) array_merge(['ID' => 1, 'post_type' => 'case', 'post_name' => 'my-case'], $fields));
        $GLOBALS['__tobiuo_test_posts'][$post->ID] = $post;

        return $post;
    }

    /**
     * Build a WP_Term stand-in and make get_term() / get_ancestors() find it.
     */
    protected function makeTerm(int $id, string $slug, string $taxonomy = 'case_category', int $parent = 0): \WP_Term
    {
        return $GLOBALS['__tobiuo_test_terms'][$id] = new \WP_Term((object) [
            'term_id'  => $id,
            'slug'     => $slug,
            'taxonomy' => $taxonomy,
            'parent'   => $parent,
        ]);
    }

    /**
     * Assign terms to a post, for get_the_terms().
     *
     * @param int[] $termIds
     */
    protected function assignTerms(\WP_Post $post, array $termIds, string $taxonomy = 'case_category'): void
    {
        $GLOBALS['__tobiuo_test_post_terms'][$post->ID][$taxonomy] = $termIds;
    }

    /**
     * Install a WP_Rewrite stand-in with the given site-wide structure ('' for plain permalinks).
     */
    protected function useRewrite(string $permalinkStructure = '/%postname%/'): \WP_Rewrite
    {
        return $GLOBALS['wp_rewrite'] = new \WP_Rewrite($permalinkStructure);
    }

    /**
     * Reset the hook registry, the stub globals and every per-request static.
     *
     * Registry lives for the whole request by design, so without this the
     * first test's registerPostType() would make every later one hit the
     * duplicate-registration guard.
     */
    private function reset(): void
    {
        $GLOBALS['__tobiuo_hooks'] = [];
        foreach (self::TEST_GLOBALS as $global) {
            unset($GLOBALS[$global]);
        }
        unset($_SERVER['REQUEST_URI']);

        $statics = [
            [Registry::class, 'postTypes', []],
            [Registry::class, 'taxonomies', []],
            [Registry::class, 'handedOver', false],
            [ArchiveLinks::class, 'enabled', false],
        ];

        foreach ($statics as [$class, $property, $value]) {
            $reflection = new \ReflectionProperty($class, $property);
            $reflection->setAccessible(true);
            $reflection->setValue(null, $value);
        }
    }
}
