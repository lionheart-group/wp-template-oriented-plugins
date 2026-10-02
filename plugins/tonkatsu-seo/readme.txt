=== TONKATSU (Template-Oriented No-database Knowledge-graph & Tag Setup Utility) ===
Contributors: lionheartgroup
Tags: seo, meta, open graph, json-ld, sitemap
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.0.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.txt

TONKATSU outputs SEO metadata, structured data and sitemap adjustments configured entirely in theme code.

== Description ==

Template-Oriented No-database Knowledge-graph & Tag Setup Utility (TONKATSU) handles the SEO output of a WordPress site — title, meta description, canonical, robots, Open Graph, Twitter Card and JSON-LD — and adjusts WordPress core's XML sitemaps.

Everything is configured in the theme's PHP code, on `init`. Nothing is stored in the database and there is no settings screen, so the SEO configuration is reviewed, versioned and deployed together with the templates that render the pages. A read-only screen under Tools shows what the theme registered and what each page resolves to.

Redirects (exact paths, path prefixes and regular expressions, including 410 Gone) are registered in the theme too, so moved URLs are kept with the code that moved them.

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

= Does TONKATSU generate its own sitemap? =

No. It adjusts WordPress core's sitemap at /wp-sitemap.xml.

== Screenshots ==



== Changelog ==

= 0.0.1 =
* Initial release.
