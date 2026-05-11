<?php

namespace TofuPlugin\Models;

use TofuPlugin\Base\DatabaseModels as AbstractModels;
use TofuPlugin\Helpers\Encryptor;
use TofuPlugin\Logger;
use TofuPlugin\Structure\DatabaseModelColumn;

class Record extends AbstractModels {
    /**
     * Table suffix for the Record model.
     */
    const TABLE_SUFFIX = 'tofu_records';

    /**
     * Define the database table columns for the Record model.
     *
     * @return DatabaseModelColumn[]
     */
    protected static function columns(): array
    {
        return [
            new DatabaseModelColumn(
                name: 'form_id',
                type: DatabaseModelColumn::COLUMN_STRING,
                required: true,
            ),
            new DatabaseModelColumn(
                name: 'data',
                type: DatabaseModelColumn::COLUMN_STRING,
                required: false,
            ),
            new DatabaseModelColumn(
                name: 'submitted_at',
                type: DatabaseModelColumn::COLUMN_DATETIME,
                required: false,
            ),
        ];
    }

    /**
     * Encrypt and persist a form submission record.
     *
     * @param string   $formKey      The form key (stored as `form_id`).
     * @param array    $values       All validated field values.
     * @param string[] $recordFields Fields to include; empty = all fields in $values.
     * @return int|false Inserted row ID on success, false on failure.
     */
    public static function saveRecord(string $formKey, array $values, array $recordFields = []): int|false
    {
        $payload = !empty($recordFields)
            ? array_intersect_key($values, array_flip($recordFields))
            : $values;

        $encrypted = Encryptor::encrypt($payload);

        return static::insert([
            'form_id'      => $formKey,
            'data'         => $encrypted,
            'submitted_at' => current_time('mysql', true),
        ]);
    }

    /**
     * Query recorded submissions with optional form_id filter and pagination.
     *
     * @param string|null $formId  Filter by form_id; null = all forms.
     * @param int         $perPage Rows per page.
     * @param int         $page    1-based page number.
     * @return array{ items: object[], total: int }
     */
    public static function getRecords(?string $formId = null, int $perPage = 25, int $page = 1): array
    {
        global $wpdb;
        $table  = static::getTableName();
        $offset = ($page - 1) * $perPage;

        if ($formId !== null) {
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM %i WHERE form_id = %s ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
                $table, $formId, $perPage, $offset
            ));
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE form_id = %s",
                $table, $formId
            ));
        } else {
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM %i ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
                $table, $perPage, $offset
            ));
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i",
                $table
            ));
        }

        return ['items' => $items ?? [], 'total' => $total];
    }

    /**
     * Return distinct form_id values present in the records table.
     *
     * @return string[]
     */
    public static function getFormIds(): array
    {
        global $wpdb;
        $table = static::getTableName();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT form_id FROM %i ORDER BY form_id ASC",
            $table
        ));

        if (empty($rows)) {
            return [];
        }

        return array_column((array) $rows, 'form_id');
    }

    /**
     * Fetch a single record row by primary key.
     *
     * @param int $id Row ID.
     * @return object|null The row object, or null if not found.
     */
    public static function getRecord(int $id): ?object
    {
        global $wpdb;
        $table = static::getTableName();

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE id = %d",
            $table, $id
        ));

        return $row instanceof \stdClass ? $row : null;
    }
}
