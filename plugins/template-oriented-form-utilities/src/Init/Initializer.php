<?php

namespace TofuPlugin\Init;

use TofuPlugin\Models\Record;
use TofuPlugin\Models\Session;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Initializer {
    public static function activate() {
        // Activate step

        // Prepare tables
        Migrate::migrate();
    }

    public static function deactivate() {
        // Deactivate step

        // Drop tables
        // Disabled drop table function to prevent data loss
        // Migrate::dropTable(Record::getTableName());
        // Migrate::dropTable(Session::getTableName());
        // Migrate::dropTable(Migrate::getTableName());
    }

    public static function upgrade() {
        // Upgrade step

        // Prepare tables
        Migrate::migrate();
    }
}
