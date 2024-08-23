<?php

namespace TofuPlugin\Models;

use TofuPlugin\Logger;

class Record extends AbstractModels {
    const TABLE_SUFFIX = 'tofu_records';

    public static function generateTable()
    {
        /** @var \wpdb */
        global $wpdb;
        $tableName = static::getTableName();

        $sql = <<< SQL
CREATE TABLE IF NOT EXISTS {$tableName} (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `form_id` VARCHAR(20) NOT NULL,
    INDEX `form_id` (`form_id`),
    PRIMARY KEY (`id`)
) {$wpdb->get_charset_collate()};
SQL;

        Logger::info('Create table', ['sql' => $sql]);

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta($sql);
    }

    public static function dropTable()
    {
        /** @var \wpdb */
        global $wpdb;
        $tableName = static::getTableName();

        $sql = "DROP TABLE IF EXISTS `{$tableName}`;";

        Logger::info('Drop table', ['sql' => $sql]);
        $wpdb->query($sql);
    }
}
