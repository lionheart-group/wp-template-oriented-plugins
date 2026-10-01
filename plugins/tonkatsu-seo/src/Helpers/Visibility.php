<?php

namespace TonkatsuPlugin\Helpers;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Site-wide search engine visibility (Settings → Reading).
 *
 * When "Discourage search engines from indexing this site" is checked, core
 * prints `noindex, nofollow` on every page and disables the sitemaps,
 * whatever TONKATSU resolves. Admin screens use this to show the value that is
 * actually output, and to warn before a site goes live with it still on.
 */
class Visibility
{
    /**
     * @return bool True when "Discourage search engines" is checked.
     */
    public static function searchEnginesDiscouraged(): bool
    {
        return (string) get_option('blog_public') === '0';
    }
}
