=== TOBIUO (Template-Oriented Builder of Items, URLs & Organization) ===
Contributors: lionheartgroup
Tags: post-types, permalinks, custom-post-type, taxonomy, rewrite
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.0.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.txt

TOBIUO registers post types, taxonomies and custom permalink structures configured entirely in theme code.

== Description ==

Template-Oriented Builder of Items, URLs & Organization (TOBIUO) registers a site's custom post types and taxonomies, and gives each post type its own permalink structure — for example `/case/%case_category%/%postname%/` — with optional date and author archives below the post type archive.

Everything is configured in the theme's PHP code, on `init`. Nothing is stored in the database and there is no settings screen, so the content structure is reviewed, versioned and deployed together with the templates that display it. A read-only screen under Tools shows what the theme registered, example URLs, and whether the stored rewrite rules are up to date.

* Post types and taxonomies are registered in the right order whatever order the theme declares them in.
* Permalink structures may use `%postname%`, `%post_id%`, the date tags, `%author%` and any attached taxonomy (`%{taxonomy}%`, with parent terms as `parent/child`).
* A post requested through a wrong term path is redirected to its permalink.
* `wp_get_archives( [ 'post_type' => … ] )` links to the post type's own date archives.
* WordPress's own posts get an archive (`/news/`) from theme code too. The theme can also state the permalink structure it expects for them (`/news/%postname%/`); the structure itself stays in Settings -> Permalinks, and the Tools screen warns when it differs.

TOBIUO replaces the Custom Post Type Permalinks plugin. While that plugin is active, TOBIUO still registers the post types and taxonomies but leaves their URLs alone, and shows an admin notice.

GitHub and documentation for this plugin can be found at:

[https://github.com/lionheart-group/wp-template-oriented-plugins/tree/master/plugins/tobiuo-content-structure](https://github.com/lionheart-group/wp-template-oriented-plugins/tree/master/plugins/tobiuo-content-structure)

== Installation ==

1. From the WP admin panel, click "Plugins" -> "Add new".
2. In the browser input box, type "Template-Oriented Builder of Items, URLs & Organization".
3. Select the "Template-Oriented Builder of Items, URLs & Organization" plugin and click "Install".
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

In your theme's code. Tools -> Content Structure (TOBIUO) shows them, read-only.

= Tools -> Content Structure (TOBIUO) says the permalink structure differs from the theme. =

The theme expects a permalink structure for posts (for example `/news/%postname%/`), and Settings -> Permalinks has another one. TOBIUO does not change that setting: choose Custom Structure on Settings -> Permalinks, enter the expected structure shown on the Tools screen, and save.

= I changed a permalink structure and get 404s. =

Open Settings -> Permalinks. Opening the screen regenerates the rewrite rules; there is no need to save. Tools -> Content Structure (TOBIUO) shows whether any rule is missing.

== Screenshots ==



== Changelog ==

= 0.0.1 =
* Initial release.
