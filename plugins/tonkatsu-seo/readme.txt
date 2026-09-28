=== TORO (Template-Oriented Rank Optimizer) ===
Contributors: lionheartgroup
Tags: seo, meta, open graph, json-ld, sitemap
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.txt

Template-Oriented Rank Optimizer outputs SEO metadata, structured data and sitemap adjustments configured entirely in theme code.

== Description ==

Template-Oriented Rank Optimizer (TORO) handles the SEO output of a WordPress site — title, meta description, canonical, robots, Open Graph, Twitter Card and JSON-LD — and adjusts WordPress core's XML sitemaps.

Everything is configured in the theme's PHP code, on `init`. Nothing is stored in the database and there is no settings screen, so the SEO configuration is reviewed, versioned and deployed together with the templates that render the pages. A read-only screen under Tools shows what the theme registered and what each page resolves to.

Per-post values that editors maintain (for example in custom fields) are supplied through the `toro_post_values` filter.

TORO stands down while Rank Math, Yoast SEO, All in One SEO or SEOPress is active, and shows an admin notice instead.

GitHub and documentation for this plugin can be found at:

[https://github.com/lionheart-group/template-oriented-rank-optimizer](https://github.com/lionheart-group/template-oriented-rank-optimizer)

== Installation ==

1. From the WP admin panel, click "Plugins" -> "Add new".
2. In the browser input box, type "Template-Oriented Rank Optimizer".
3. Select the "Template-Oriented Rank Optimizer" plugin and click "Install".
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

In your theme's code. Tools -> SEO (TORO) shows them, read-only.

= Does TORO generate its own sitemap? =

No. It adjusts WordPress core's sitemap at /wp-sitemap.xml.

== Screenshots ==



== Changelog ==

= 0.1.0 =
* Initial release.
