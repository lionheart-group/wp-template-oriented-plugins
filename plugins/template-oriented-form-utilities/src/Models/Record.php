<?php

namespace TofuPlugin\Models;

use TofuPlugin\Base\DatabaseModels as AbstractModels;
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
        ];
    }
}
