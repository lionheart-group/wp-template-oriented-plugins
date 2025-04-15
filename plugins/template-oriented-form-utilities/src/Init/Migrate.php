<?php

namespace TofuPlugin\Init;

use TofuPlugin\Logger;

class Migrate
{
    const TABLE_SUFFIX = 'tofu_migrate';

    /** @var \wpdb */
    protected static $wpdb;

    public static function prepareWpdb()
    {
        if (!static::$wpdb) {
            /** @var \wpdb */
            global $wpdb;
            static::$wpdb = $wpdb;
        }
    }

    public static function getTableName()
    {
        return esc_sql(static::$wpdb->prefix . static::TABLE_SUFFIX);
    }

    protected static function checkMigrateTable()
    {
        $table_name = static::getTableName();
        $charset_collate = static::$wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `key` varchar(128) NOT NULL,
            `created_at` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            `updated_at` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            UNIQUE INDEX `key` (`key`),
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        Logger::info($sql);

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    protected static function getMigrateKey($key)
    {
        $table_name = static::getTableName();
        $key = esc_sql($key);
        $sql = "SELECT * FROM {$table_name} WHERE `key` = '{$key}'";
        return static::$wpdb->get_row($sql);
    }

    public static function migrate()
    {
        static::checkMigrateTable();

        foreach ([
            '2024-08-29_00-00-00_init-records',
        ] as $migrate) {
            Logger::info("Migration {$migrate} start.");
            $migrateKey = static::getMigrateKey($migrate);
            Logger::info(!$migrateKey);
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
            static::$wpdb->insert($table_name, [
                'key' => $key,
                'created_at' => $created_at,
                'updated_at' => $updated_at,
            ]);
        }
    }

    public static function dropTable($table_name)
    {
        $sql = "DROP TABLE IF EXISTS {$table_name};";
        static::$wpdb->query($sql);
    }
}
