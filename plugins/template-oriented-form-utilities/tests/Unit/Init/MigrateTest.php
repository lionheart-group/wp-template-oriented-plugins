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

    public function testNeedsMigrationWhenTheStoredVersionDiffers(): void
    {
        $this->assertTrue(Migrate::needsMigration(false, '0.1.3'));
        $this->assertTrue(Migrate::needsMigration('0.1.2', '0.1.3'));
        $this->assertFalse(Migrate::needsMigration('0.1.3', '0.1.3'));
    }

    public function testMaybeMigrateRunsOnceAndStoresTheVersion(): void
    {
        $runs = 0;
        $runner = function () use (&$runs): bool {
            $runs++;
            return true;
        };

        $this->assertTrue(Migrate::maybeMigrate('0.1.3', $runner));
        $this->assertFalse(Migrate::maybeMigrate('0.1.3', $runner));

        $this->assertSame(1, $runs);
        $this->assertSame('0.1.3', $GLOBALS['__tofu_options'][Migrate::VERSION_OPTION]);
    }

    public function testAFailedMigrationIsRetriedOnTheNextRequest(): void
    {
        $runs = 0;
        $runner = function () use (&$runs): bool {
            $runs++;
            return $runs > 1;
        };

        Migrate::maybeMigrate('0.1.3', $runner);
        $this->assertArrayNotHasKey(Migrate::VERSION_OPTION, $GLOBALS['__tofu_options']);

        Migrate::maybeMigrate('0.1.3', $runner);
        $this->assertSame(2, $runs);
        $this->assertSame('0.1.3', $GLOBALS['__tofu_options'][Migrate::VERSION_OPTION]);
    }

    public function testANewVersionRunsTheMigrationsAgain(): void
    {
        $GLOBALS['__tofu_options'][Migrate::VERSION_OPTION] = '0.1.2';
        $runs = 0;

        Migrate::maybeMigrate('0.1.3', function () use (&$runs): bool {
            $runs++;
            return true;
        });

        $this->assertSame(1, $runs);
    }
}
