<?php

namespace TofuPlugin\Init;

use TofuPlugin\Consts;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class Endpoint {

    public static function init()
    {
        // Register endpoint
        add_action('init', function () {
            add_rewrite_endpoint(Consts::QUERY_KEY, EP_NONE);
        });

        // Handle endpoint
        add_action('template_redirect', function () {
            $var = get_query_var(Consts::QUERY_KEY);

            if ($var) {
                // Decode the key string
                $decoded = json_decode(base64_decode($var), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    wp_die('Invalid key format.', 'TOFU Key Error', ['response' => 400]);
                }

                // Check required parameters
                if (!isset($decoded['key']) || !isset($decoded['action'])) {
                    wp_die('Missing required parameters.', 'TOFU Key Error', ['response' => 400]);
                }

                // Get the form by key
                $form = \TofuPlugin\Helpers\Form::get($decoded['key']);
                if (!$form) {
                    wp_die('Form not found.', 'TOFU Form Error', ['response' => 404]);
                }

                $form->action($decoded['action']);
                exit;
            }
        });
    }

}
