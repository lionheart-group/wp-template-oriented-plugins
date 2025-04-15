<?php

namespace TofuPlugin\Models;

use TofuPlugin\Base\Models as AbstractModels;
use TofuPlugin\Logger;

class Record extends AbstractModels {
    const TABLE_SUFFIX = 'tofu_records';

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
