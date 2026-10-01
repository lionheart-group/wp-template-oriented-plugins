<?php

namespace TobiuoPlugin;

final class Consts
{
    /**
     * Admin page slug (Tools → Content Structure (TOBIUO)).
     */
    public const ADMIN_PAGE_SLUG = 'tobiuo-content-structure';

    /**
     * Capability required to view the admin page when the
     * `tobiuo_admin_page_capability` filter returns nothing usable.
     */
    public const DEFAULT_CAPABILITY = 'manage_options';

    /**
     * `init` priority at which every registered taxonomy and post type is
     * handed to core.
     *
     * Late on purpose: themes register configs at the default priority, in
     * whatever order their files load, and TOBIUO registers all taxonomies
     * before all post types afterwards. Core's own taxonomies and post types
     * (created at priority 0) already exist by then.
     */
    public const REGISTER_PRIORITY = 99;

    /**
     * `template_redirect` priority of the canonical redirect.
     *
     * Before core's redirect_canonical() (10), so a request for a wrong
     * term path goes straight to the permalink in one hop.
     */
    public const REDIRECT_PRIORITY = 9;

    /**
     * Prefix of the rewrite tag that stands in for `%{taxonomy}%` in a
     * permastruct.
     *
     * Private on purpose: core's own `%{taxonomy}%` tag (query
     * `{taxonomy}=`) also drives the taxonomy's term archives, so it must
     * not be overwritten, and putting the term into the query would make a
     * single post look like a term archive as well.
     */
    public const TERM_TAG_PREFIX = 'tobiuo_term_';

    /**
     * Prefix of the rewrite tag that stands in for `%post_id%` when a
     * structure has no `%postname%`.
     *
     * Core's `%post_id%` tag queries `p=` alone, which WP_Query looks up
     * among posts of type `post` only; this tag adds the post type.
     */
    public const POST_ID_TAG_PREFIX = 'tobiuo_post_id_';

    /**
     * Date archive prefix used when a structure could otherwise produce
     * post URLs that look like date archives (see Rewrite::dateFront()).
     */
    public const DATE_FRONT = '/date';

    /**
     * Plugins TOBIUO hands the permalinks to, keyed by a constant each defines.
     *
     * @var array<string, string>
     */
    public const CONFLICTING_PLUGINS = [
        'CPTP_VERSION' => 'Custom Post Type Permalinks',
    ];

    /**
     * Same, keyed by a class each defines.
     *
     * Checked as well as the constant: Custom Post Type Permalinks defines
     * its constant from the plugin header, its class unconditionally.
     *
     * @var array<string, string>
     */
    public const CONFLICTING_CLASSES = [
        'CPTP' => 'Custom Post Type Permalinks',
    ];
}
