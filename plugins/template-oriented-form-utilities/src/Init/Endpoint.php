<?php

namespace TofuPlugin\Init;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class Endpoint {

    public static function init()
    {
        // Register endpoint
        add_action('init', function () {
            add_rewrite_endpoint('template-oriented-form-utilities', EP_PERMALINK | EP_PAGES);
        });

        // Handle endpoint
        add_action('template_redirect', function () {
            $var = get_query_var('template-oriented-form-utilities');
            if ($var) {
                echo 'template-oriented-form-utilities: ' . $var;
                exit;
            }
        });
    }

}
