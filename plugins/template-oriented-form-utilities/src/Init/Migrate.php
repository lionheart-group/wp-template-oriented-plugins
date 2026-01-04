<?php

namespace TofuPlugin\Init;

use TofuPlugin\Logger;

class Migrate
{
    const TABLE_SUFFIX = 'tofu_migrate';

    public static function getTableName()
    {
        global $wpdb;
        return esc_sql($wpdb->prefix . static::TABLE_SUFFIX);
    }

    protected static function checkMigrateTable()
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

    protected static function getMigrateKey($key)
    {
        global $wpdb;
        $table_name = static::getTableName();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE `key` = %s", $table_name, $key));
    }

    public static function migrate()
    {
        global $wpdb;
        static::checkMigrateTable();

        foreach ([
            '2024-08-29_00-00-00_init-records',
            '2025-12-23_00-00-00_session-tables',
        ] as $migrate) {
            Logger::info("Migration {$migrate} start.");
            $migrateKey = static::getMigrateKey($migrate);
            if ($migrateKey) {
                Logger::info("Migration {$migrate} already executed.");
                continue;
            }

            // Execute migration
            Logger::info("Get migration file: {$migrate}");
            $migrateClass = require_once TOFU_PLUGIN_DIR . '/migrations/' . $migrate . '.php';
            $sql = $migrateClass->sql();
            Logger::info($sql);
            dbDelta($sql);

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

    public static function dropTable($table_name)
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table_name));
    }
}
