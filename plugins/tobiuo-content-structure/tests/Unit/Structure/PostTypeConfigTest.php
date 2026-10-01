<?php

namespace TobiuoPlugin\Tests\Unit\Structure;

use TobiuoPlugin\Structure\PermalinkConfig;
use TobiuoPlugin\Structure\PostTypeConfig;
use TobiuoPlugin\Tests\Unit\BaseTestCase;

class PostTypeConfigTest extends BaseTestCase
{
    public function testDefaults(): void
    {
        $config = new PostTypeConfig(name: 'case');

        $this->assertSame('case', $config->name);
        $this->assertSame([], $config->args);
        $this->assertNull($config->permalink);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validNames(): array
    {
        return [
            'letters'        => ['case'],
            'underscore'     => ['case_study'],
            'hyphen'         => ['case-study'],
            'digits'         => ['news2'],
            '20 characters'  => [str_repeat('a', 20)],
        ];
    }

    /**
     * @dataProvider validNames
     */
    public function testValidNames(string $name): void
    {
        $this->assertSame($name, (new PostTypeConfig(name: $name))->name);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNames(): array
    {
        return [
            'empty'          => [''],
            'uppercase'      => ['Case'],
            'space'          => ['case study'],
            'multibyte'      => ['事例'],
            '21 characters'  => [str_repeat('a', 21)],
            'markup'         => ['<b>'],
        ];
    }

    /**
     * @dataProvider invalidNames
     */
    public function testInvalidNames(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("PostTypeConfig: name '" . esc_html($name) . "'");

        new PostTypeConfig(name: $name);
    }

    public function testFromArrayWithAPermalinkArray(): void
    {
        $config = PostTypeConfig::fromArray([
            'name'      => 'case',
            'args'      => ['public' => true],
            'permalink' => ['structure' => '/%postname%/', 'dateArchive' => true],
        ]);

        $this->assertSame('case', $config->name);
        $this->assertSame(['public' => true], $config->args);
        $this->assertInstanceOf(PermalinkConfig::class, $config->permalink);
        $this->assertTrue($config->permalink->dateArchive);
    }

    public function testFromArrayWithAPermalinkObject(): void
    {
        $permalink = new PermalinkConfig(structure: '/%post_id%/');

        $this->assertSame($permalink, PostTypeConfig::fromArray(['name' => 'case', 'permalink' => $permalink])->permalink);
    }

    public function testFromArrayWithoutPermalink(): void
    {
        $this->assertNull(PostTypeConfig::fromArray(['name' => 'case'])->permalink);
        $this->assertNull(PostTypeConfig::fromArray(['name' => 'case', 'permalink' => null])->permalink);
    }

    public function testFromArrayRejectsUnknownKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PostTypeConfig &#039;case.php&#039;: unknown key(s) "post_type&quot;, &quot;taxonomies"');

        PostTypeConfig::fromArray(['post_type' => 'case', 'taxonomies' => []], 'case.php');
    }

    public function testFromArrayRequiresAName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"name" is required');

        PostTypeConfig::fromArray(['args' => []]);
    }

    public function testFromArrayRejectsArgsThatAreNotAnArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"args" must be an array, got string');

        PostTypeConfig::fromArray(['name' => 'case', 'args' => 'public=1']);
    }

    public function testFromArrayRejectsAPermalinkOfAnotherType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"permalink" must be a PermalinkConfig, an array or null, got string');

        PostTypeConfig::fromArray(['name' => 'case', 'permalink' => '/%postname%/']);
    }

    public function testPermalinkErrorsNameThePostType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PermalinkConfig &#039;case&#039;: unknown key(s) "date_archive"');

        PostTypeConfig::fromArray(['name' => 'case', 'permalink' => ['structure' => '/%postname%/', 'date_archive' => true]]);
    }

    public function testPermalinkErrorsNameTheLabelWhenGiven(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PermalinkConfig &#039;settings/case.php&#039;');

        PostTypeConfig::fromArray(['name' => 'case', 'permalink' => ['dateArchive' => true]], 'settings/case.php');
    }
}
