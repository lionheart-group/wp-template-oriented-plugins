<?php

namespace TobiuoPlugin\Tests\Unit\Helpers;

use TobiuoPlugin\Helpers\Registry;
use TobiuoPlugin\Structure\PermalinkConfig;
use TobiuoPlugin\Structure\PostsConfig;
use TobiuoPlugin\Structure\PostTypeConfig;
use TobiuoPlugin\Structure\TaxonomyConfig;
use TobiuoPlugin\Tests\Unit\BaseTestCase;

class RegistryTest extends BaseTestCase
{
    public function testRegisterAndGetPostTypes(): void
    {
        $permalink = new PermalinkConfig(structure: '/%postname%/');
        $case = new PostTypeConfig(name: 'case', permalink: $permalink);
        $news = new PostTypeConfig(name: 'news');

        Registry::registerPostType($case);
        Registry::registerPostType($news);

        $this->assertSame($case, Registry::getPostType('case'));
        $this->assertNull(Registry::getPostType('post'));
        $this->assertSame(['case' => $case, 'news' => $news], Registry::getPostTypes());
        $this->assertSame($permalink, Registry::getPermalink('case'));
        $this->assertNull(Registry::getPermalink('news'));
        $this->assertNull(Registry::getPermalink('post'));
    }

    public function testRegisterAndGetTaxonomies(): void
    {
        $area = new TaxonomyConfig(name: 'area', objectTypes: ['case']);

        Registry::registerTaxonomy($area);

        $this->assertSame($area, Registry::getTaxonomy('area'));
        $this->assertNull(Registry::getTaxonomy('category'));
        $this->assertSame(['area' => $area], Registry::getTaxonomies());
    }

    public function testPostTypesAndTaxonomiesAreSeparateRegistries(): void
    {
        Registry::registerPostType(new PostTypeConfig(name: 'area'));
        Registry::registerTaxonomy(new TaxonomyConfig(name: 'area'));

        $this->assertCount(1, Registry::getPostTypes());
        $this->assertCount(1, Registry::getTaxonomies());
    }

    public function testDuplicatePostTypeRegistrationDies(): void
    {
        Registry::registerPostType(new PostTypeConfig(name: 'case'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Post type "case" is already registered.');

        Registry::registerPostType(new PostTypeConfig(name: 'case'));
    }

    public function testDuplicateTaxonomyRegistrationDies(): void
    {
        Registry::registerTaxonomy(new TaxonomyConfig(name: 'area'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Taxonomy "area" is already registered.');

        Registry::registerTaxonomy(new TaxonomyConfig(name: 'area'));
    }

    public function testRegistrationAfterTheHandOverDies(): void
    {
        Registry::markHandedOver();

        $this->assertTrue(Registry::isHandedOver());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Registry::registerPostType() was called for "case" after TOBIUO registered everything (init priority 99)');

        Registry::registerPostType(new PostTypeConfig(name: 'case'));
    }

    public function testTaxonomyRegistrationAfterTheHandOverDies(): void
    {
        Registry::markHandedOver();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Registry::registerTaxonomy()');

        Registry::registerTaxonomy(new TaxonomyConfig(name: 'area'));
    }

    public function testRegisterAndGetPosts(): void
    {
        $this->assertNull(Registry::getPosts());

        $posts = new PostsConfig(archive: 'news');
        Registry::registerPosts($posts);

        $this->assertSame($posts, Registry::getPosts());
        $this->assertSame([], Registry::getPostTypes());
    }

    public function testRegisteringPostsTwiceDies(): void
    {
        Registry::registerPosts(new PostsConfig(archive: 'news'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The posts configuration is already registered.');

        Registry::registerPosts(new PostsConfig(archive: 'blog'));
    }

    public function testRegisteringPostsAfterTheHandOverDies(): void
    {
        Registry::markHandedOver();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Registry::registerPosts() was called for "post" after TOBIUO registered everything');

        Registry::registerPosts(new PostsConfig(archive: 'news'));
    }

    public function testRegisteringPostAsAPostTypeDiesAndPointsToRegisterPosts(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Post type "post" is built into WordPress and cannot be registered with TOBIUO. Use Registry::registerPosts(');

        Registry::registerPostType(new PostTypeConfig(name: 'post', args: ['has_archive' => 'news']));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function builtinPostTypes(): array
    {
        $cases = [];
        foreach ((new \ReflectionClassConstant(\TobiuoPlugin\Consts::class, 'BUILTIN_POST_TYPES'))->getValue() as $name) {
            if ($name !== 'post') {
                $cases[$name] = [$name];
            }
        }

        return $cases;
    }

    /**
     * @dataProvider builtinPostTypes
     */
    public function testRegisteringABuiltinPostTypeDies(string $name): void
    {
        try {
            Registry::registerPostType(new PostTypeConfig(name: $name));
            $this->fail('Expected wp_die().');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString("Post type \"{$name}\" is built into WordPress", $e->getMessage());
            $this->assertStringNotContainsString('registerPosts', $e->getMessage());
        }

        $this->assertSame([], Registry::getPostTypes());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function builtinTaxonomies(): array
    {
        $cases = [];
        foreach ((new \ReflectionClassConstant(\TobiuoPlugin\Consts::class, 'BUILTIN_TAXONOMIES'))->getValue() as $name) {
            $cases[$name] = [$name];
        }

        return $cases;
    }

    /**
     * @dataProvider builtinTaxonomies
     */
    public function testRegisteringABuiltinTaxonomyDies(string $name): void
    {
        try {
            Registry::registerTaxonomy(new TaxonomyConfig(name: $name, objectTypes: ['case']));
            $this->fail('Expected wp_die().');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString("Taxonomy \"{$name}\" is built into WordPress", $e->getMessage());
        }

        $this->assertSame([], Registry::getTaxonomies());
    }

    public function testRefusingCategoryPointsToThePostTypeTaxonomiesArgument(): void
    {
        try {
            Registry::registerTaxonomy(new TaxonomyConfig(name: 'category', objectTypes: ['case']));
            $this->fail('Expected wp_die().');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('"taxonomies" argument', $e->getMessage());
        }
    }

    public function testRefusingNavMenuHasNoHint(): void
    {
        try {
            Registry::registerTaxonomy(new TaxonomyConfig(name: 'nav_menu'));
            $this->fail('Expected wp_die().');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('"taxonomies" argument', $e->getMessage());
        }
    }
}
