<?php

namespace TofuPlugin\Tests\Unit\Init;

use TofuPlugin\Base\Migration;
use TofuPlugin\Init\Migrate;
use TofuPlugin\Tests\Unit\BaseTestCase;

class MigrateTest extends BaseTestCase
{
    private function migrationPath(string $name): string
    {
        return dirname(__DIR__, 3) . '/migrations/' . $name . '.php';
    }

    /**
     * A failed migration is met again by a second migrate() in the same
     * request; loading it again must give the object, not `true`.
     */
    public function testLoadingTheSameMigrationTwiceReturnsTheSameObject(): void
    {
        $path = $this->migrationPath('2026-05-10_00-00-00_records-add-data-column');

        $first = Migrate::loadMigration($path);
        $second = Migrate::loadMigration($path);

        $this->assertInstanceOf(Migration::class, $first);
        $this->assertSame($first, $second);
        $this->assertTrue($second->useRawQuery());
    }

    public function testEveryMigrationFileReturnsAMigration(): void
    {
        $files = glob(dirname(__DIR__, 3) . '/migrations/*.php') ?: [];
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $this->assertInstanceOf(Migration::class, Migrate::loadMigration($file), basename($file));
        }
    }

    public function testAFileNotReturningAMigrationGivesNull(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'tofu-migration');
        file_put_contents($file, '<?php return 1;');

        try {
            $this->assertNull(Migrate::loadMigration($file));
        } finally {
            unlink($file);
        }
    }
}
