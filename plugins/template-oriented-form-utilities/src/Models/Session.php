<?php

namespace TofuPlugin\Models;

use TofuPlugin\Base\DatabaseModels as AbstractModels;
use TofuPlugin\Logger;
use TofuPlugin\Structure\DatabaseModelColumn;

class Session extends AbstractModels {
    const TABLE_SUFFIX = 'tofu_sessions';

    protected static function columns(): array
    {
        return [
            new DatabaseModelColumn(
                name: 'form_id',
                type: DatabaseModelColumn::COLUMN_STRING,
                required: true,
            ),
            new DatabaseModelColumn(
                name: 'session_key',
                type: DatabaseModelColumn::COLUMN_STRING,
                required: true,
            ),
            new DatabaseModelColumn(
                name: 'session_value',
                type: DatabaseModelColumn::COLUMN_STRING,
                required: true,
            ),
            new DatabaseModelColumn(
                name: 'expiration',
                type: DatabaseModelColumn::COLUMN_DATETIME,
                required: true,
            ),
        ];
    }

    /**
     * Get session value by form ID and session key
     *
     * @param string $form_id
     * @param string $session_key
     * @return ?string
     */
    public static function get(string $form_id, string $session_key): ?string
    {
        global $wpdb;
        $table = static::getTableName();

        $row = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT session_value FROM %i WHERE form_id = %s AND session_key = %s AND expiration > %s",
                $table,
                $form_id,
                $session_key,
                \current_time('mysql')
            )
        );

        return $row;
    }

    /**
     * Check if a session exists by form ID and session key
     *
     * @param string $form_id
     * @param string $session_key
     * @return bool
     */
    public static function exists(string $form_id, string $session_key): bool
    {
        global $wpdb;
        $table = static::getTableName();

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE form_id = %s AND session_key = %s",
                $table,
                $form_id,
                $session_key
            )
        );

        return $count > 0;
    }

    /**
     * Clear expired sessions
     *
     * @return void
     */
    public static function clearExpired(): void
    {
        global $wpdb;
        $table = static::getTableName();

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM %i WHERE expiration <= %s",
                $table,
                \current_time('mysql')
            )
        );
    }
}
