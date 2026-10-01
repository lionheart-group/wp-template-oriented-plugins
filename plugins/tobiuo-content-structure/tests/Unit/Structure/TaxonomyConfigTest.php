<?php

namespace TobiuoPlugin\Tests\Unit\Structure;

use TobiuoPlugin\Structure\TaxonomyConfig;
use TobiuoPlugin\Tests\Unit\BaseTestCase;

class TaxonomyConfigTest extends BaseTestCase
{
    public function testDefaults(): void
    {
        $config = new TaxonomyConfig(name: 'case_category');

        $this->assertSame('case_category', $config->name);
        $this->assertSame([], $config->objectTypes);
        $this->assertSame([], $config->args);
    }

    public function testThirtyTwoCharacterNamesAreAccepted(): void
    {
        $name = str_repeat('t', 32);

        $this->assertSame($name, (new TaxonomyConfig(name: $name))->name);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNames(): array
    {
        return [
            'empty'          => [''],
            'uppercase'      => ['Area'],
            '33 characters'  => [str_repeat('t', 33)],
        ];
    }

    /**
     * @dataProvider invalidNames
     */
    public function testInvalidNames(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("TaxonomyConfig: name '" . esc_html($name) . "'");

        new TaxonomyConfig(name: $name);
    }

    public function testObjectTypesMustBePostTypeNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("TaxonomyConfig 'area': objectTypes must hold post type names, got 'Case'");

        new TaxonomyConfig(name: 'area', objectTypes: ['case', 'Case']);
    }

    public function testObjectTypesMustBeStrings(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("got 'int'");

        new TaxonomyConfig(name: 'area', objectTypes: [1]);
    }

    public function testObjectTypesMustBeAList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a list');

        new TaxonomyConfig(name: 'area', objectTypes: ['a' => 'case']);
    }

    public function testFromArray(): void
    {
        $config = TaxonomyConfig::fromArray([
            'name'        => 'area',
            'objectTypes' => ['case', 'post'],
            'args'        => ['hierarchical' => true],
        ]);

        $this->assertSame('area', $config->name);
        $this->assertSame(['case', 'post'], $config->objectTypes);
        $this->assertSame(['hierarchical' => true], $config->args);
    }

    public function testFromArrayRejectsUnknownKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TaxonomyConfig &#039;area.php&#039;: unknown key(s) "object_types"');

        TaxonomyConfig::fromArray(['name' => 'area', 'object_types' => ['case']], 'area.php');
    }

    public function testFromArrayRejectsAStringObjectType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"objectTypes" must be an array of strings, got string');

        TaxonomyConfig::fromArray(['name' => 'area', 'objectTypes' => 'case']);
    }

    public function testFromArrayRequiresAName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"name" is required');

        TaxonomyConfig::fromArray(['objectTypes' => ['case']]);
    }
}
