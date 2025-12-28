<?php

namespace TofuPlugin\Structure;

/**
 * Database model column class.
 *
 * This class is used to define the configuration for a database model column.
 *
 * @package TofuPlugin\Structure
 */
class DatabaseModelColumn
{
    const COLUMN_STRING = 'string';
    const COLUMN_INT = 'int';
    const COLUMN_FLOAT = 'float';
    const COLUMN_DATETIME = 'datetime';

    public function __construct(
        /**
         * Column name.
         *
         * @var string
         */
        public readonly string $name,

        /**
         * Column type.
         *
         * @var string
         */
        public readonly string $type,

        /**
         * Is the column required.
         *
         * @var bool
         */
        public readonly bool $required = false,
    )
    {
    }
}
