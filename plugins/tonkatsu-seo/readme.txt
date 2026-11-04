=== TONKATSU (Template-Oriented No-database Knowledge-graph & Tag Setup Utility) ===
Contributors: lionheartgroup
Tags: seo, meta, open graph, json-ld, sitemap
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.0.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.txt

TONKATSU outputs SEO metadata, structured data and sitemap adjustments configured entirely in theme code.

== Description ==

Template-Oriented No-database Knowledge-graph & Tag Setup Utility (TONKATSU) handles the SEO output of a WordPress site — title, meta description, canonical, robots, Open Graph, Twitter Card and JSON-LD — and adjusts WordPress core's XML sitemaps. Static pages outside WordPress, such as landing pages, can be added to the sitemap too.

Everything is configured in the theme's PHP code, on `init`. No configuration is stored in the database and there is no settings screen, so the SEO configuration is reviewed, versioned and deployed together with the templates that render the pages. A read-only screen under Tools shows what the theme registered and what each page resolves to.

Redirects (exact paths, path prefixes and regular expressions, including 410 Gone) are registered in the theme too, so moved URLs are kept with the code that moved them. Optionally, TONKATSU logs the redirects it answers — the only data it ever stores in the database.

Per-post values that editors maintain (for example in custom fields) are supplied through the `tonkatsu_post_values` filter.

TONKATSU stands down while Rank Math, Yoast SEO, All in One SEO or SEOPress is active, and shows an admin notice instead.

GitHub and documentation for this plugin can be found at:

[https://github.com/lionheart-group/wp-template-oriented-plugins/tree/master/plugins/tonkatsu-seo](https://github.com/lionheart-group/wp-template-oriented-plugins/tree/master/plugins/tonkatsu-seo)

== Installation ==

1. From the WP admin panel, click "Plugins" -> "Add new".
2. In the browser input box, type "Template-Oriented No-database Knowledge-graph & Tag Setup Utility".
3. Select the "Template-Oriented No-database Knowledge-graph & Tag Setup Utility" plugin and click "Install".
4. Activate the plugin.

OR…

1. Download the plugin from this page.
2. Save the .zip file to a location on your computer.
3. Open the WP admin panel, and click "Plugins" -> "Add new".
4. Click "upload".. then browse to the .zip file downloaded from this page.
5. Click "Install".. and then "Activate plugin".

Then register the configuration in your theme — see the documentation.

== Frequently Asked Questions ==

= Where are the settings? =

In your theme's code. Tools -> SEO (TONKATSU) shows them, read-only.

= What does TONKATSU store in the database? =

No settings. With the redirect log turned on in the theme (SiteConfig `logRedirects`), it stores a hit count per redirect and one row per redirect answered — the requested URL, the target, the status, the referrer without its query string and whether the visitor looked like a bot — in two tables of its own, plus their schema version in the `tonkatsu_db_version` option. No IP addresses or user agents are stored, and rows are deleted after 90 days by default. Deleting the plugin removes the tables and the option.

= Does TONKATSU generate its own sitemap? =

No. It adjusts WordPress core's sitemap at /wp-sitemap.xml. Static pages outside WordPress written in the theme are added to it as one more sitemap, /wp-sitemap-static-1.xml.

== Screenshots ==



== Changelog ==

= 0.0.4 =
* Added: OrganizationConfig takes `type` (a schema.org subtype such as `Corporation`), `alternateName`, `address` (printed as a PostalAddress), `telephone`, `faxNumber` and `foundingDate`. Properties left at their defaults are not printed. See docs/settings/organizationconfig.md.
* Changed: the JSON-LD BreadcrumbList now covers posts of every post type and puts the post type's archive before posts and term archives (Home > News > Post, Home > Cases > Category). The archive is named by the title registered with `Seo::registerArchive()`, else, for posts, by the posts page's title, else by the post type's label.

= 0.0.3 =
* Added: static pages outside WordPress, such as landing pages, can be listed in the sitemap with SitemapConfig's `extraUrls`. They get a sitemap of their own, /wp-sitemap-static-1.xml, listed in /wp-sitemap.xml. lastmod is the written value, else the modification time of the file the URL points to. URLs on another host or scheme, or outside the home URL, are left out. Tools -> SEO (TONKATSU) shows each URL and warns when no file was found. See docs/settings/sitemapconfig.md.

= 0.0.2 =
* Added: redirects registered in theme code with `Seo::registerRedirects()` — exact, prefix and (when declared) regex matches, with 301, 302, 307, 308 and 410. Listed on Tools -> SEO (TONKATSU), which warns about redirects that hide a registered page and about chained redirects. They stand down while another SEO plugin is active. See docs/settings/redirectconfig.md.
* Added: an opt-in redirect log (`SiteConfig::$logRedirects`). It keeps hit counts per redirect and each hit for `redirectLogDays` days (default 90) in two tables, without IP addresses or user agents, and lists them on the Tools page. Uninstalling the plugin removes the tables. Settings are still never stored in the database.
* Requires WordPress 6.5.

= 0.0.1 =
* Initial release.
