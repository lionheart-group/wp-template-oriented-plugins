<?php

namespace TofuPlugin\Init;

use TofuPlugin\Base\Migration;
use TofuPlugin\Logger;

class Migrate
{
    /**
     * Migrate table suffix
     */
    const TABLE_SUFFIX = 'tofu_migrate';

    /**
     * Option holding the plugin version whose migrations have all run.
     */
    const VERSION_OPTION = 'tofu_db_version';

    /**
     * Migration objects already loaded in this request, keyed by file path.
     *
     * @var array<string, Migration>
     */
    protected static array $loaded = [];

    /**
     * Get migrate table name
     *
     * @return string
     */
    public static function getTableName(): string
    {
        global $wpdb;
        return esc_sql($wpdb->prefix . static::TABLE_SUFFIX);
    }

    /**
     * Check and create migrate table if not exists
     *
     * @return void
     */
    protected static function checkMigrateTable(): void
    {
        global $wpdb;
        $table_name = static::getTableName();

        $sql = $wpdb->prepare(
            "CREATE TABLE IF NOT EXISTS %i (
                `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                `key` varchar(128) NOT NULL,
                `created_at` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                `updated_at` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                UNIQUE INDEX `key` (`key`),
                PRIMARY KEY  (id)
            ) " . $wpdb->get_charset_collate(),
            $table_name
        );

        Logger::info($sql);

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Check migrate key if done
     *
     * @param string $key
     * @return bool
     */
    protected static function checkDoneMigrateKey(string $key): bool
    {
        global $wpdb;
        $table_name = static::getTableName();
        $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE `key` = %s", $table_name, $key));
        return $result !== null;
    }

    /**
     * Run the migrations once per plugin version, on any request.
     *
     * Activation and `upgrader_process_complete` miss updates made by
     * replacing the plugin's files (FTP, deployments), so `init` compares the
     * stored version first — one autoloaded option, no query when it matches.
     * It runs on the front end too: a form saving a record must not meet a
     * table without the columns it writes. Migrations are idempotent, so two
     * requests running them at once do no harm.
     *
     * @param string $version The plugin's version (TOFU_VERSION).
     * @param ?callable(): bool $runner Runs the migrations; migrate() by default.
     * @return bool Whether the migrations ran.
     */
    public static function maybeMigrate(string $version, ?callable $runner = null): bool
    {
        if (!static::needsMigration(get_option(static::VERSION_OPTION), $version)) {
            return false;
        }

        $runner ??= [static::class, 'migrate'];
        if ($runner() === true) {
            update_option(static::VERSION_OPTION, $version, true);
        }

        return true;
    }

    /**
     * Whether the stored version calls for running the migrations.
     *
     * @param mixed $stored get_option() result (false when missing).
     * @param string $version
     * @return bool
     */
    public static function needsMigration(mixed $stored, string $version): bool
    {
        return $stored !== $version;
    }

    /**
     * Execute migrations
     *
     * @return bool True when every migration has been applied (now or before).
     */
    public static function migrate(): bool
    {
        global $wpdb;
        static::checkMigrateTable();
        $complete = true;

        foreach ([
            '2024-08-29_00-00-00_init-records',
            '2025-12-23_00-00-00_session-tables',
            '2026-05-10_00-00-00_records-add-data-column',
        ] as $migrate) {
            Logger::info("Migration {$migrate} start.");
            if (static::checkDoneMigrateKey($migrate)) {
                Logger::info("Migration {$migrate} already executed.");
                continue;
            }

            // Execute migration
            Logger::info("Get migration file: {$migrate}");
            $migrateClass = static::loadMigration(TOFU_PLUGIN_DIR . '/migrations/' . $migrate . '.php');
            if ($migrateClass === null) {
                Logger::error("Migration {$migrate} does not return a Migration object.");
                $complete = false;
                continue;
            }
            $sql = $migrateClass->sql();
            Logger::info($sql);
            if ($migrateClass->useRawQuery()) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- each migration's sql() builds its query with $wpdb->prepare()
                $result = $wpdb->query($sql);
                if ($result === false) {
                    Logger::error("Migration {$migrate} raw query failed: " . $wpdb->last_error);
                    $complete = false;
                    continue;
                }
            } else {
                dbDelta($sql);
            }

            // Save migration key
            $table_name = static::getTableName();
            $key = esc_sql($migrate);
            $created_at = current_time('mysql');
            $updated_at = current_time('mysql');
            $wpdb->insert($table_name, [
                'key' => $key,
                'created_at' => $created_at,
                'updated_at' => $updated_at,
            ]);
        }

        return $complete;
    }

    /**
     * Load a migration file once per request.
     *
     * A migration that failed is not marked as done, so migrate() may meet it
     * again in the same request (activation and upgrade). `require_once` would
     * then return true instead of the object, so the object is kept here.
     *
     * @param string $path
     * @return ?Migration Null when the file does not return a Migration.
     */
    public static function loadMigration(string $path): ?Migration
    {
        if (!isset(static::$loaded[$path])) {
            $migration = require $path;
            if (!$migration instanceof Migration) {
                return null;
            }
            static::$loaded[$path] = $migration;
        }

        return static::$loaded[$path];
    }

    /**
     * Drop migrate table
     *
     * @param string $table_name
     * @return void
     */
    public static function dropTable(string $table_name): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table_name));
    }
}
