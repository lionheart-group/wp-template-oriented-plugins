<?php

use TofuPlugin\Base\Migration;
use TofuPlugin\Models\Record;

return new class extends Migration {
    public function sql()
    {
        $recordTable = Record::getTableName();

        return <<< SQL
CREATE TABLE IF NOT EXISTS {$recordTable} (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `form_id` VARCHAR(128) NOT NULL,
    INDEX `form_id` (`form_id`),
    PRIMARY KEY (`id`)
) {$this->wpdb->get_charset_collate()};
SQL;
    }
};
