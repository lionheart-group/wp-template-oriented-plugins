<?php

use TofuPlugin\Base\Migration;
use TofuPlugin\Models\Record;

return new class extends Migration {
    public function sql()
    {
        global $wpdb;
        $recordTable = Record::getTableName();

        return $wpdb->prepare(
            "CREATE TABLE IF NOT EXISTS %i (
                `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `form_id` VARCHAR(128) NOT NULL,
                INDEX `form_id` (`form_id`),
                PRIMARY KEY (`id`)
            ) " . $wpdb->get_charset_collate(),
            $recordTable
        );
    }
};
