<?php

use TofuPlugin\Base\Migration;
use TofuPlugin\Models\Session;

return new class extends Migration {
    public function sql()
    {
        global $wpdb;
        $sessionTable = Session::getTableName();

        return $wpdb->prepare(
            "CREATE TABLE IF NOT EXISTS %i (
                `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `form_id` VARCHAR(128) NOT NULL,
                `session_key` varchar(64) NOT NULL,
                `session_value` LONGTEXT NOT NULL,
                `expiration` DATETIME NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE `form_session` (`form_id`, `session_key`),
                INDEX `expiration` (`expiration`),
                PRIMARY KEY (`id`)
            ) " . $wpdb->get_charset_collate(),
            $sessionTable
        );
    }
};
