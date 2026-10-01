<?php

namespace TofuPlugin\Tests\Unit\Base;

use TofuPlugin\Base\DatabaseModels;
use TofuPlugin\Structure\DatabaseModelColumn;
use TofuPlugin\Tests\Unit\BaseTestCase;

/**
 * Concrete implementation of DatabaseModels for testing
 */
class TestDatabaseModel extends DatabaseModels
{
    const TABLE_SUFFIX = 'test_table';

    protected static function columns(): array
    {
        return [
            new DatabaseModelColumn('id', DatabaseModelColumn::COLUMN_INT, false),
            new DatabaseModelColumn('name', DatabaseModelColumn::COLUMN_STRING, true),
            new DatabaseModelColumn('email', DatabaseModelColumn::COLUMN_STRING, true),
            new DatabaseModelColumn('age', DatabaseModelColumn::COLUMN_INT, false),
            new DatabaseModelColumn('price', DatabaseModelColumn::COLUMN_FLOAT, false),
            new DatabaseModelColumn('created_at', DatabaseModelColumn::COLUMN_DATETIME, false),
        ];
    }
}

/**
 * Test case for DatabaseModels base class
 */
class DatabaseModelsTest extends BaseTestCase
{
    /**
     * Test getTableName returns correct table name
     */
    public function testGetTableNameReturnsCorrectTableName(): void
    {
        $tableName = TestDatabaseModel::getTableName();
        $this->assertSame('wp_test_table', $tableName);
    }

    /**
     * Test insert method with valid data
     */
    public function testInsertWithValidData(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ];

        $insertId = TestDatabaseModel::insert($data);
        $this->assertIsInt($insertId);
    }

    /**
     * Test insert throws exception for missing required column
     */
    public function testInsertThrowsExceptionForMissingRequiredColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required column: name');

        $data = [
            'email' => 'john@example.com',
        ];

        TestDatabaseModel::insert($data);
    }

    /**
     * Test insert throws exception for invalid integer
     */
    public function testInsertThrowsExceptionForInvalidInteger(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid integer for column: age');

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 'not-a-number',
        ];

        TestDatabaseModel::insert($data);
    }

    /**
     * Test insert throws exception for invalid float
     */
    public function testInsertThrowsExceptionForInvalidFloat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid number for column: price');

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'price' => 'not-a-number',
        ];

        TestDatabaseModel::insert($data);
    }

    /**
     * Test insert with DateTime object
     */
    public function testInsertWithDateTimeObject(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'created_at' => new \DateTime('2024-01-15 10:30:00'),
        ];

        $insertId = TestDatabaseModel::insert($data);
        $this->assertIsInt($insertId);
    }

    /**
     * Test insert with timestamp
     */
    public function testInsertWithTimestamp(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'created_at' => strtotime('2024-01-15 10:30:00'),
        ];

        $insertId = TestDatabaseModel::insert($data);
        $this->assertIsInt($insertId);
    }

    /**
     * Test insert with date string
     */
    public function testInsertWithDateString(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'created_at' => '2024-01-15 10:30:00',
        ];

        $insertId = TestDatabaseModel::insert($data);
        $this->assertIsInt($insertId);
    }

    /**
     * Test insert throws exception for invalid datetime
     */
    public function testInsertThrowsExceptionForInvalidDatetime(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid datetime for column: created_at');

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'created_at' => 'not-a-date',
        ];

        TestDatabaseModel::insert($data);
    }

    /**
     * Test update method
     */
    public function testUpdateMethod(): void
    {
        $data = [
            'name' => 'Jane Doe',
        ];
        $where = [
            'id' => 1,
        ];

        $result = TestDatabaseModel::update($data, $where);
        $this->assertIsInt($result);
    }

    /**
     * Test delete method
     */
    public function testDeleteMethod(): void
    {
        $where = [
            'id' => 1,
        ];

        $result = TestDatabaseModel::delete($where);
        $this->assertIsInt($result);
    }
}
