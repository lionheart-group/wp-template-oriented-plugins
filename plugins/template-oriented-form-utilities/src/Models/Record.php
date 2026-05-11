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
}
