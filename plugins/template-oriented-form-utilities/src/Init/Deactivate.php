<?php

namespace TofuPlugin\Init;

use TofuPlugin\Models\Record;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Deactivate {
    public static function deactivate() {
        // Deactivate step

        // Drop tables
        Record::dropTable();
    }
}
