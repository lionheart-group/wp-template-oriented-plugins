<?php

namespace TofuPlugin\Tests\Unit\Structure;

use TofuPlugin\Structure\DatabaseModelColumn;
use TofuPlugin\Tests\Unit\BaseTestCase;

/**
 * Test case for DatabaseModelColumn structure
 */
class DatabaseModelColumnTest extends BaseTestCase
{
    /**
     * Test that DatabaseModelColumn can be instantiated with required parameters
     */
    public function testCanBeInstantiatedWithRequiredParameters(): void
    {
        $column = new DatabaseModelColumn(
            'test_column',
            DatabaseModelColumn::COLUMN_STRING
        );

        $this->assertSame('test_column', $column->name);
        $this->assertSame(DatabaseModelColumn::COLUMN_STRING, $column->type);
        $this->assertFalse($column->required);
    }

    /**
     * Test that DatabaseModelColumn can be instantiated with all parameters
     */
    public function testCanBeInstantiatedWithAllParameters(): void
    {
        $column = new DatabaseModelColumn(
            'id',
            DatabaseModelColumn::COLUMN_INT,
            true
        );

        $this->assertSame('id', $column->name);
        $this->assertSame(DatabaseModelColumn::COLUMN_INT, $column->type);
        $this->assertTrue($column->required);
    }

    /**
     * Test column type constants are defined
     */
    public function testColumnTypeConstantsAreDefined(): void
    {
        $this->assertSame('string', DatabaseModelColumn::COLUMN_STRING);
        $this->assertSame('int', DatabaseModelColumn::COLUMN_INT);
        $this->assertSame('float', DatabaseModelColumn::COLUMN_FLOAT);
        $this->assertSame('datetime', DatabaseModelColumn::COLUMN_DATETIME);
    }

    /**
     * Test different column types
     *
     * @dataProvider columnTypesProvider
     */
    public function testDifferentColumnTypes(string $type): void
    {
        $column = new DatabaseModelColumn(
            'test',
            $type
        );

        $this->assertSame($type, $column->type);
    }

    /**
     * Data provider for column types
     *
     * @return array<array<string>>
     */
    public static function columnTypesProvider(): array
    {
        return [
            'string type' => [DatabaseModelColumn::COLUMN_STRING],
            'int type' => [DatabaseModelColumn::COLUMN_INT],
            'float type' => [DatabaseModelColumn::COLUMN_FLOAT],
            'datetime type' => [DatabaseModelColumn::COLUMN_DATETIME],
        ];
    }
}
