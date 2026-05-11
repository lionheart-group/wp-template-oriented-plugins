<?php

namespace TofuPlugin\Init;

use TofuPlugin\Logger;

class Migrate
{
    /**
     * Migrate table suffix
     */
    const TABLE_SUFFIX = 'tofu_migrate';

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
     * Execute migrations
     *
     * @return void
     */
    public static function migrate(): void
    {
        global $wpdb;
        static::checkMigrateTable();

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
            $migrateClass = require_once TOFU_PLUGIN_DIR . '/migrations/' . $migrate . '.php';
            $sql = $migrateClass->sql();
            Logger::info($sql);
            if ($migrateClass->useRawQuery()) {
                $wpdb->query($sql);
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
