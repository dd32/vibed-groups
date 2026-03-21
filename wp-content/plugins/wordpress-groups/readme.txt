=== WordPress Groups ===
Contributors: wordpressdotorg
Tags: community, groups, events, meetup, wordpress
Requires at least: 6.7
Tested up to: 6.7
Requires PHP: 8.3
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A community groups platform for organising WordPress meetups and events on events.wordpress.org.

== Description ==

WordPress Groups provides the tools needed to run a WordPress community groups programme. It powers the events.wordpress.org platform, enabling community organisers to create and manage local WordPress meetup groups, schedule events, and grow their communities.

**Key features:**

* Group management with membership roles (organiser, co-organiser, member).
* Event scheduling with RSVP tracking and recurring event support.
* Venue management with geocoding and map integration.
* Analytics dashboard tracking group health and activity trends.
* Dormancy detection to identify inactive groups.
* Slack integration for event notifications.
* iCal export for calendar subscriptions.
* REST API for headless and block-based front ends.
* Organiser onboarding and application workflow.
* Email notifications for event reminders and announcements.

This plugin is designed for use on WordPress multisite networks and is not intended for general-purpose installations.

== Installation ==

1. Ensure you are running a WordPress multisite network with PHP 8.3 or later.
2. Upload the `wordpress-groups` folder to the `/wp-content/plugins/` directory.
3. Network-activate the plugin through the **Network Admin > Plugins** screen.
4. The required database tables are created automatically on activation.
5. Configure the plugin settings via the admin dashboard.

== Frequently Asked Questions ==

= Is this plugin available on WordPress.org? =

Not yet. WordPress Groups is currently developed as part of the WordPress.org Meta Team infrastructure.

= Does it work on single-site installations? =

The plugin is designed for multisite networks. Single-site usage is not officially supported.

= How do I report a bug or request a feature? =

Please open an issue on the project's GitHub repository.

= What REST API endpoints are available? =

The plugin registers endpoints under the `groups/v1` namespace for events, memberships, RSVPs, venues, the group directory, and user preferences. A health-check endpoint is also available for administrators.

== Screenshots ==

1. Group directory listing page.
2. Single event view with RSVP form.
3. Analytics dashboard showing group activity.
4. Organiser application workflow screen.

== Changelog ==

= 0.1.0 =
* Initial scaffold of the WordPress Groups plugin.
* Event and venue post types with REST API controllers.
* Membership model with roles and RSVP tracking.
* Analytics aggregation and dormancy detection.
* Recurring event generation via cron.
* Slack and Official Events API integrations.
* Organiser onboarding and application workflow.
* Block editor blocks for event display.
* iCal calendar export.
* Email notification scheduler.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
