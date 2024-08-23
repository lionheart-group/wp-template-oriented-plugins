<?php

namespace TofuPlugin\Models;


abstract class AbstractModels {
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

    protected static function getTableName()
    {
        global $wpdb;
        return esc_sql($wpdb->prefix . static::TABLE_SUFFIX);
    }

    abstract public static function generateTable();
    abstract public static function dropTable();
}
