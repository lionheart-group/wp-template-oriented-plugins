<?php

namespace TobiuoPlugin\Init;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Rewrite rules on activation and deactivation.
 *
 * Both hooks run in a request where the rules cannot be generated right:
 * on activation the theme's `init` callback has already run without TOBIUO,
 * so nothing is registered yet; on deactivation TOBIUO is still loaded. So
 * neither generates rules — both delete core's stored rules, and core
 * regenerates them on the next request, with or without TOBIUO's.
 */
class Activation
{
    /**
     * Register the hooks. Call once from the plugin's main file.
     */
    public static function register(): void
    {
        register_activation_hook(TOBIUO_PLUGIN_FILE, [static::class, 'resetRewriteRules']);
        register_deactivation_hook(TOBIUO_PLUGIN_FILE, [static::class, 'resetRewriteRules']);
    }

    /**
     * Have core regenerate the rewrite rules on the next request.
     *
     * The same first step as flush_rewrite_rules(), without regenerating in
     * this request. `.htaccess` is not touched: the block WordPress writes
     * there does not depend on the rules.
     */
    public static function resetRewriteRules(): void
    {
        delete_option('rewrite_rules');
    }
}
