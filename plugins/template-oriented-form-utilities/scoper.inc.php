<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

/**
 * PHP-Scoper configuration for Template-Oriented Form Utilities
 *
 * This configuration prefixes all vendor namespaces to avoid conflicts
 * with other WordPress plugins that may use the same dependencies.
 */
return [
    // The prefix to apply to all namespaces
    'prefix' => 'TofuVendor',

    // Output directory (relative to the project root)
    'output-dir' => 'build',

    // List of files/directories to scope
    'finders' => [
        // Only include production dependencies
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile/')
            ->exclude([
                'doc',
                'docs',
                'test',
                'Test',
                'tests',
                'Tests',
            ])
            ->in('vendor/monolog'),
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile/')
            ->exclude([
                'doc',
                'docs',
                'test',
                'Test',
                'tests',
                'Tests',
            ])
            ->in('vendor/wixel'),
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile/')
            ->in('vendor/psr/log'),
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->in('src'),
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->in('migrations'),
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->in('assets'),
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->in('languages'),
        Finder::create()->append([
            'template-oriented-form-utilities.php',
            'index.php',
        ]),
    ],

    // List of symbols (classes, functions, constants) that should not be prefixed
    'exclude-namespaces' => [
        // WordPress core
        'WP',
        'WP_*',

        // Our own plugin namespace (already unique)
        'TofuPlugin',
    ],

    'exclude-classes' => [
        // WordPress classes
        'WP_Error',
        'WP_Post',
        'WP_Query',
        'WP_User',
        'WP_Term',
        'WP_Comment',
        'WP_Widget',
        'WP_Customize_Control',
        'WP_REST_Controller',
        'WP_REST_Request',
        'WP_REST_Response',
        'wpdb',
        'Walker',
        'Walker_Nav_Menu',
        'WP_List_Table',
        'PHPMailer',

        // GUMP (validation library)
        'GUMP',
    ],

    'exclude-functions' => [
        // WordPress core functions
        'wp_*',
        'get_*',
        'add_*',
        'remove_*',
        'do_action',
        'apply_filters',
        'register_*',
        'unregister_*',
        'is_*',
        'esc_*',
        'sanitize_*',
        'absint',
        'dbDelta',
        'plugin_dir_path',
        'plugin_dir_url',
        'plugin_basename',
        'plugins_url',
        'home_url',
        'site_url',
        'admin_url',
        'content_url',
        'includes_url',
        'current_time',
        'current_user_can',
        '__',
        '_e',
        '_n',
        '_x',
        '_ex',
        '_nx',
        'esc_attr__',
        'esc_attr_e',
        'esc_attr_x',
        'esc_html__',
        'esc_html_e',
        'esc_html_x',
        'load_plugin_textdomain',
        'check_ajax_referer',
        'wp_create_nonce',
        'wp_verify_nonce',
        'wp_send_json',
        'wp_send_json_success',
        'wp_send_json_error',
        'wp_die',
        'wp_redirect',
        'wp_safe_redirect',
        'wp_mail',
        'wp_upload_dir',
        'maybe_serialize',
        'maybe_unserialize',
        'set_transient',
        'get_transient',
        'delete_transient',
        'update_option',
        'get_option',
        'delete_option',
        'add_action',
        'add_filter',
        'remove_action',
        'remove_filter',
        'has_action',
        'has_filter',
        'did_action',
        'doing_action',
        'doing_filter',
        'shortcode_atts',
        'add_shortcode',
        'remove_shortcode',
        'do_shortcode',
        'has_shortcode',
        'wp_enqueue_script',
        'wp_enqueue_style',
        'wp_register_script',
        'wp_register_style',
        'wp_dequeue_script',
        'wp_dequeue_style',
        'wp_localize_script',
        'wp_add_inline_script',
        'wp_add_inline_style',

        // PHP built-in functions (some commonly used ones)
        'defined',
        'define',
        'constant',
        'class_exists',
        'function_exists',
        'interface_exists',
        'trait_exists',
    ],

    'exclude-constants' => [
        // WordPress constants
        'ABSPATH',
        'WP_CONTENT_DIR',
        'WP_PLUGIN_DIR',
        'WPINC',
        'WP_DEBUG',
        'DOING_AJAX',
        'DOING_CRON',
        'DOING_AUTOSAVE',
        'MULTISITE',
        'UPLOAD_ERR_OK',
        'UPLOAD_ERR_INI_SIZE',
        'UPLOAD_ERR_FORM_SIZE',
        'UPLOAD_ERR_PARTIAL',
        'UPLOAD_ERR_NO_FILE',
        'UPLOAD_ERR_NO_TMP_DIR',
        'UPLOAD_ERR_CANT_WRITE',
        'UPLOAD_ERR_EXTENSION',

        // Plugin constants
        'TOFU_VERSION',
        'TOFU_PLUGIN_DIR',
        'TOFU_PLUGIN_FILE',
    ],

    // Patchers allow modifying scoped files
    'patchers' => [
        /**
         * Remove namespace from main plugin file and index.php
         * These files should not have a namespace declaration
         */
        static function (string $filePath, string $prefix, string $contents): string {
            $baseName = basename($filePath);

            // Remove namespace declaration from main plugin file and index.php
            if ($baseName === 'template-oriented-form-utilities.php' || $baseName === 'index.php') {
                $contents = preg_replace(
                    '/^namespace\s+' . preg_quote($prefix, '/') . ';\s*\n/m',
                    '',
                    $contents
                );
            }

            // GUMP uses global namespace and should not be prefixed in use statements
            if (str_contains($filePath, 'src/')) {
                $contents = preg_replace(
                    '/use\s+' . preg_quote($prefix, '/') . '\\\\GUMP;/',
                    'use GUMP;',
                    $contents
                );
            }

            return $contents;
        },
    ],
];
