<?php

namespace TobiuoPlugin\Tests\Unit\Init;

use TobiuoPlugin\Helpers\Registry;
use TobiuoPlugin\Init\Registration;
use TobiuoPlugin\Structure\PermalinkConfig;
use TobiuoPlugin\Structure\PostTypeConfig;
use TobiuoPlugin\Structure\TaxonomyConfig;
use TobiuoPlugin\Tests\Unit\BaseTestCase;

class RegistrationTest extends BaseTestCase
{
    public function testHooksInitLate(): void
    {
        Registration::register();

        $this->assertSame(99, has_action('init', [Registration::class, 'handOver']));
    }

    public function testTaxonomiesAreRegisteredBeforePostTypesWhateverTheOrder(): void
    {
        Registry::registerPostType(new PostTypeConfig(name: 'case', args: ['public' => true]));
        Registry::registerTaxonomy(new TaxonomyConfig(name: 'case_category', objectTypes: ['case']));
        Registry::registerPostType(new PostTypeConfig(name: 'news'));
        Registry::registerTaxonomy(new TaxonomyConfig(name: 'area'));

        Registration::handOver();

        $this->assertSame(
            ['taxonomy:case_category', 'taxonomy:area', 'post_type:case', 'post_type:news'],
            $GLOBALS['__tobiuo_test_log']
        );
        $this->assertTrue(Registry::isHandedOver());
    }

    public function testArgumentsArePassedAsTheyAre(): void
    {
        Registry::registerPostType(new PostTypeConfig(name: 'case', args: ['label' => '事例', 'has_archive' => 'cases']));
        Registry::registerTaxonomy(new TaxonomyConfig(name: 'area', objectTypes: ['case'], args: ['label' => '地域']));

        Registration::handOver();

        $this->assertSame('事例', get_post_type_object('case')->label);
        $this->assertSame('cases', get_post_type_object('case')->has_archive);
        $this->assertSame(['case'], $GLOBALS['__tobiuo_test_taxonomies']['area']->object_type);
        $this->assertSame('地域', $GLOBALS['__tobiuo_test_taxonomies']['area']->label);
    }

    public function testFiresTobiuoRegisteredOnce(): void
    {
        $calls = 0;
        add_action('tobiuo_registered', function () use (&$calls) {
            $calls++;
        });

        Registration::handOver();
        Registration::handOver();

        $this->assertSame(1, $calls);
    }

    public function testACorePostTypeRegistrationErrorDies(): void
    {
        $GLOBALS['__tobiuo_test_registration_errors']['case'] = 'Reserved <name>.';
        Registry::registerPostType(new PostTypeConfig(name: 'case'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Post type "case" could not be registered: Reserved &lt;name&gt;.');

        Registration::handOver();
    }

    public function testACoreTaxonomyRegistrationErrorDies(): void
    {
        $GLOBALS['__tobiuo_test_registration_errors']['area'] = 'Reserved.';
        Registry::registerTaxonomy(new TaxonomyConfig(name: 'area'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Taxonomy "area" could not be registered: Reserved.');

        Registration::handOver();
    }

    public function testAnUnattachedTaxonomyTagDies(): void
    {
        $this->useRewrite();
        Registry::registerTaxonomy(new TaxonomyConfig(name: 'area', objectTypes: ['news']));
        Registry::registerPostType(new PostTypeConfig(
            name: 'case',
            permalink: new PermalinkConfig(structure: '/%area%/%postname%/'),
        ));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('the permalink tag %area% names the taxonomy &quot;area&quot;, which is not attached to the post type');

        Registration::handOver();
    }

    public function testATaxonomyAttachedByThePostTypeIsAccepted(): void
    {
        $this->useRewrite();
        Registry::registerTaxonomy(new TaxonomyConfig(name: 'area'));
        Registry::registerPostType(new PostTypeConfig(
            name: 'case',
            args: ['taxonomies' => ['area']],
            permalink: new PermalinkConfig(structure: '/%area%/%postname%/'),
        ));

        Registration::handOver();

        $this->assertTrue(Registry::isHandedOver());
    }

    public function testATaxonomyRegisteredElsewhereIsAccepted(): void
    {
        $this->useRewrite();
        register_taxonomy('area', ['case']);
        $GLOBALS['__tobiuo_test_log'] = [];
        Registry::registerPostType(new PostTypeConfig(
            name: 'case',
            permalink: new PermalinkConfig(structure: '/%area%/%postname%/'),
        ));

        Registration::handOver();

        $this->assertSame(['post_type:case'], $GLOBALS['__tobiuo_test_log']);
    }

    public function testPermalinkErrorsForAValidConfig(): void
    {
        $this->assertSame([], Registration::permalinkErrors(
            'case',
            new PermalinkConfig(structure: '/%area%/%postname%/', dateArchive: true, authorArchive: true),
            true,
            true,
            ['category', 'area'],
            ['area']
        ));
    }

    public function testPermalinkErrorsForAnUnknownTag(): void
    {
        $errors = Registration::permalinkErrors('case', new PermalinkConfig(structure: '/%pagename%/%postname%/'), true, false, ['category'], []);

        $this->assertSame(['Post type "case": the permalink tag %pagename% is not a core tag and no taxonomy "pagename" is registered.'], $errors);
    }

    public function testPermalinkErrorsWithoutRewrite(): void
    {
        $errors = Registration::permalinkErrors('case', new PermalinkConfig(structure: '/%postname%/'), false, false, [], []);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('"rewrite" argument is false', $errors[0]);
    }

    public function testPermalinkErrorsForArchivesWithoutHasArchive(): void
    {
        $errors = Registration::permalinkErrors(
            'case',
            new PermalinkConfig(structure: '/%postname%/', dateArchive: true, authorArchive: true),
            true,
            false,
            [],
            []
        );

        $this->assertCount(2, $errors);
        $this->assertStringContainsString('dateArchive needs the post type\'s "has_archive"', $errors[0]);
        $this->assertStringContainsString('authorArchive needs the post type\'s "has_archive"', $errors[1]);
    }

    public function testAllErrorsAreReportedTogetherAndEscaped(): void
    {
        $this->useRewrite();
        Registry::registerPostType(new PostTypeConfig(
            name: 'case',
            args: ['rewrite' => false],
            permalink: new PermalinkConfig(structure: '/%area%/%postname%/', dateArchive: true),
        ));

        try {
            Registration::handOver();
            $this->fail('Expected wp_die().');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('TOBIUO Permalink Configuration Error', $e->getMessage());
            $this->assertSame(2, substr_count($e->getMessage(), '<br>'));
            $this->assertStringContainsString('&quot;case&quot;', $e->getMessage());
        }
    }
}
