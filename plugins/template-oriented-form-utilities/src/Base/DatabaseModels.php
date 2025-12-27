<?php

namespace TofuPlugin\Base;

use TofuPlugin\Structure\DatabaseModelColumn;

abstract class DatabaseModels {
    const TABLE_SUFFIX = '';

    /** @var \wpdb */
    protected $wpdb;

    /** @var string */
    protected $table = '';

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = static::getTableName();
    }

    public static function getTableName()
    {
        global $wpdb;
        return esc_sql($wpdb->prefix . static::TABLE_SUFFIX);
    }

    /**
     * Specify the columns of the table
     *
     * @return DatabaseModelColumn[]
     */
    abstract protected static function columns(): array;

    /**
     * Parsing data before inserting/updating into the database
     *
     * @param array $data
     * @return array
     */
    protected static function parseData(array $data, $checkRequired = true): array
    {
        $values = [];
        $formats = [];
        $columns = static::columns();

        foreach ($columns as $column) {
            if (!array_key_exists($column->name, $data)) {
                if ($column->required && $checkRequired) {
                    throw new \InvalidArgumentException("Missing required column: {$column->name}");
                } else {
                    continue;
                }
            }

            switch ($column->type) {
                case DatabaseModelColumn::COLUMN_STRING:
                    $values[$column->name] = strval($data[$column->name]);
                    $formats[] = '%s';
                    break;
                case DatabaseModelColumn::COLUMN_INT:
                    if (!is_numeric($data[$column->name]) && isset($data[$column->name])) {
                        throw new \InvalidArgumentException("Invalid integer for column: {$column->name}");
                    }

                    $values[$column->name] = intval($data[$column->name]);
                    $formats[] = '%d';
                    break;
                case DatabaseModelColumn::COLUMN_FLOAT:
                    if (!is_numeric($data[$column->name]) && isset($data[$column->name])) {
                        throw new \InvalidArgumentException("Invalid number for column: {$column->name}");
                    }

                    $values[$column->name] = floatval($data[$column->name]);
                    $formats[] = '%f';
                    break;
                case DatabaseModelColumn::COLUMN_DATETIME:
                    // If the value is DateTime, convert it to string
                    if ($data[$column->name] instanceof \DateTime) {
                        $values[$column->name] = $data[$column->name]->format('Y-m-d H:i:s');
                    } else {
                        if (is_numeric($data[$column->name]) && $data[$column->name] > 0) {
                            // If it's a timestamp, convert it to a date string
                            $data[$column->name] = date('Y-m-d H:i:s', intval($data[$column->name]));
                        }

                        if (strtotime($data[$column->name]) === false) {
                            throw new \InvalidArgumentException("Invalid datetime for column: {$column->name}");
                        }

                        $values[$column->name] = date('Y-m-d H:i:s', strtotime($data[$column->name]));
                    }
                    $formats[] = '%s';
                    break;
                default:
                    throw new \InvalidArgumentException("Unsupported column type: {$column->type}");
            }
        }

        return [
            'values' => $values,
            'formats' => $formats,
        ];
    }

    /**
     * Insert a new record into the database
     *
     * @param array $data
     * @return int Inserted record ID
     */
    public static function insert(array $data)
    {
        global $wpdb;
        $table = static::getTableName();
        $parsedData = static::parseData($data);
        $wpdb->insert($table, $parsedData['values'], $parsedData['formats']);
        return $wpdb->insert_id;
    }

    /**
     * Update an existing record in the database
     *
     * @param array $data
     * @param array $where
     * @return int Number of rows affected
     */
    public static function update(array $data, array $where)
    {
        global $wpdb;
        $table = static::getTableName();
        $parsedData = static::parseData($data, false);
        $parsedWhere = static::parseData($where, false);
        return $wpdb->update($table, $parsedData['values'], $parsedWhere['values'], $parsedData['formats'], $parsedWhere['formats']);
    }

    /**
     * Delete records from the database
     *
     * @param array $where
     * @return int Number of rows deleted
     */
    public static function delete(array $where)
    {
        global $wpdb;
        $table = static::getTableName();
        $parsedWhere = static::parseData($where, false);
        return $wpdb->delete($table, $parsedWhere['values'], $parsedWhere['formats']);
    }
}
