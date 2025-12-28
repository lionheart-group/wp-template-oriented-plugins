<?php

namespace TofuPlugin\Models;

use TofuPlugin\Base\DatabaseModels as AbstractModels;
use TofuPlugin\Logger;
use TofuPlugin\Structure\DatabaseModelColumn;

class Record extends AbstractModels {
    const TABLE_SUFFIX = 'tofu_records';

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
