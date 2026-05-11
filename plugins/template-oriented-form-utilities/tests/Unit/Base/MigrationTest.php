<?php

namespace TofuPlugin\Tests\Unit\Base;

use TofuPlugin\Base\Migration;
use TofuPlugin\Tests\Unit\BaseTestCase;

class MigrationTest extends BaseTestCase
{
    /**
     * Concrete migration that does not override useRawQuery().
     */
    private function makeDefaultMigration(): Migration
    {
        return new class extends Migration {
            public function sql(): string
            {
                return 'CREATE TABLE IF NOT EXISTS `wp_test` (`id` INT)';
            }
        };
    }

    /**
     * Concrete migration that overrides useRawQuery() to return true.
     */
    private function makeRawQueryMigration(): Migration
    {
        return new class extends Migration {
            public function sql(): string
            {
                return "ALTER TABLE `wp_test` ADD COLUMN `col` VARCHAR(255) NULL";
            }

            public function useRawQuery(): bool
            {
                return true;
            }
        };
    }

    public function testUseRawQueryReturnsFalseByDefault(): void
    {
        $migration = $this->makeDefaultMigration();
        $this->assertFalse($migration->useRawQuery());
    }

    public function testUseRawQueryCanBeOverriddenToReturnTrue(): void
    {
        $migration = $this->makeRawQueryMigration();
        $this->assertTrue($migration->useRawQuery());
    }

    public function testSqlIsReturnedFromConcreteImplementation(): void
    {
        $migration = $this->makeDefaultMigration();
        $this->assertStringContainsString('CREATE TABLE', $migration->sql());
    }
}
