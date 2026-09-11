=== TelosGym Schedule ===
Contributors: telosgym
Tags: schedule, gym, fitness, classes, timetable
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display your TelosGym class schedule on your WordPress site with a shortcode or block.

== Description ==

TelosGym Schedule shows your studio's live, up-to-date class timetable directly on your WordPress site — no iframe, no manual updates.

[TelosGym](https://telosgym.com) is class-scheduling and studio-management software for gyms, studios, and independent trainers. If you already run classes through TelosGym, this plugin displays that same live schedule on your own WordPress site.

= Features =

* `[telos_schedule]` shortcode for the classic editor or any page builder
* A native block for the block editor, with day-count and accent-colour controls
* Reads your schedule live, straight from your browser to TelosGym — nothing is cached or proxied through your WordPress server
* Simple colour and font settings to match your site
* Free — this plugin only displays your schedule; booking happens on your TelosGym-hosted booking page (link-out only, no payments here)

= How it works =

Each embed shows a "Book" link per class that sends visitors to your studio's TelosGym booking page — this plugin itself never handles bookings or payments.

== Installation ==

1. Install and activate the plugin.
2. In your TelosGym staff dashboard, go to Settings → External embeds, and create a new embed key using this WordPress site's URL as the allowed origin.
3. In WordPress, go to Settings → TelosGym Schedule and paste that key in. Click "Test connection" to confirm it works.
4. Add the `[telos_schedule]` shortcode to any page, or add the "TelosGym Schedule" block from the block inserter.

== Frequently Asked Questions ==

= What is TelosGym? =

TelosGym is class-scheduling and studio-management software. This plugin is a display-only integration for studios that already use it.

= Where do I get an embed key? =

From your TelosGym staff dashboard: Settings → External embeds → Create key, using your WordPress site's URL as the allowed origin.

= Can visitors book a class directly on my WordPress site? =

No — each class links out to your TelosGym-hosted booking page. This plugin is display/discovery only.

= Why does the schedule need my site's exact URL? =

Your embed key only works from the exact origin (domain) you registered it for. This stops anyone else from using your key on a different site.

= Does this work with multisite? =

Not in this version — each site needs its own activation and its own embed key.

== Screenshots ==

1. Settings page — enter your embed key and test the connection.
2. The shortcode rendering a live schedule on the front end.
3. The block editor with the TelosGym Schedule block's Inspector controls.

== Changelog ==

= 1.0.0 =
* Initial release: shortcode, Gutenberg block, settings page with key validation, colour/font options.
