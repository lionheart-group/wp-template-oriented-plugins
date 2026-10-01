<?php

namespace TofuPlugin\Init;

use TofuPlugin\Models\Record;
use TofuPlugin\Models\Session;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Initializer
{
    /**
     * Activate step
     *
     * @return void
     */
    public static function activate(): void {
        // Activate step

        // Prepare tables
        Migrate::migrate();
    }

    /**
     * Deactivate step
     *
     * @return void
     */
    public static function deactivate(): void {
        // Deactivate step

        // Drop tables
        // Disabled drop table function to prevent data loss
        // Migrate::dropTable(Record::getTableName());
        // Migrate::dropTable(Session::getTableName());
        // Migrate::dropTable(Migrate::getTableName());
    }

    /**
     * Upgrade step
     *
     * @return void
     */
    public static function upgrade(): void {
        // Upgrade step

        // Prepare tables
        Migrate::migrate();
    }
}
