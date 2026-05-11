<?php

use TofuPlugin\Base\Migration;
use TofuPlugin\Models\Record;

return new class extends Migration {
    /**
     * Use $wpdb->query() instead of dbDelta() because ALTER TABLE is not
     * handled reliably by dbDelta().
     */
    public function useRawQuery(): bool
    {
        return true;
    }

    /**
     * Add `data` (encrypted payload) and `submitted_at` (UTC timestamp) columns
     * to the records table. Each column addition is guarded by a SHOW COLUMNS
     * check so the migration is idempotent on all supported MySQL versions.
     * Combined into a single ALTER TABLE when both columns are missing.
     */
    public function sql(): string
    {
        global $wpdb;
        $table = Record::getTableName();

        $dataExists = !empty($wpdb->get_results(
            $wpdb->prepare("SHOW COLUMNS FROM %i LIKE 'data'", $table)
        ));
        $submittedAtExists = !empty($wpdb->get_results(
            $wpdb->prepare("SHOW COLUMNS FROM %i LIKE 'submitted_at'", $table)
        ));

        if (!$dataExists && !$submittedAtExists) {
            return $wpdb->prepare(
                "ALTER TABLE %i ADD COLUMN `data` LONGTEXT NULL, ADD COLUMN `submitted_at` DATETIME NULL",
                $table
            );
        }

        if (!$dataExists) {
            return $wpdb->prepare(
                "ALTER TABLE %i ADD COLUMN `data` LONGTEXT NULL",
                $table
            );
        }

        if (!$submittedAtExists) {
            return $wpdb->prepare(
                "ALTER TABLE %i ADD COLUMN `submitted_at` DATETIME NULL",
                $table
            );
        }

        // Both columns already exist — no-op.
        return 'SELECT 1';
    }
};
