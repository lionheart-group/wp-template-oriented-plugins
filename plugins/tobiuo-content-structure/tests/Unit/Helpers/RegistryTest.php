<?php

namespace TobiuoPlugin\Tests\Unit\Helpers;

use TobiuoPlugin\Helpers\Registry;
use TobiuoPlugin\Structure\PermalinkConfig;
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
}
