<?php

namespace TofuPlugin\Init;

use TofuPlugin\Models\Record;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Activate {
    public static function activate() {
        // Activate step

        // Prepare tables
        Record::generateTable();
    }
}
