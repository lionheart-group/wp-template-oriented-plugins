<?php

namespace TofuPlugin\Base;

abstract class Models {
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

    abstract public static function dropTable();
}
