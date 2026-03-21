<?php
/**
 * Uninstall handler for WordPress Groups.
 *
 * Fired when the plugin is deleted via the WordPress admin.
 * Drops custom tables, removes custom roles, clears cron hooks,
 * and deletes plugin options.
 *
 * @package Groups
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/*
 * 1. Drop custom database tables.
 */
$base_prefix = $wpdb->base_prefix;
$wpdb->query( "DROP TABLE IF EXISTS {$base_prefix}groups_analytics_daily" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$base_prefix}groups_activity_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

/*
 * 2. Remove custom roles from all sites in the network.
 */
$custom_roles = [ 'organizer', 'co_organizer', 'member' ];

if ( is_multisite() ) {
	$site_ids = get_sites( [
		'fields' => 'ids',
		'number' => 0,
	] );

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );

		foreach ( $custom_roles as $role ) {
			remove_role( $role );
		}

		// Delete per-site options.
		delete_option( 'groups_aggregation_batch_offset' );

		restore_current_blog();
	}
} else {
	foreach ( $custom_roles as $role ) {
		remove_role( $role );
	}

	delete_option( 'groups_aggregation_batch_offset' );
}

/*
 * 3. Clear scheduled cron hooks.
 */
$cron_hooks = [
	'groups_daily_aggregation',
	'groups_dormancy_check',
	'groups_generate_recurring_events',
	'groups_event_reminder_24h',
	'groups_event_reminder_1h',
];

foreach ( $cron_hooks as $hook ) {
	wp_unschedule_hook( $hook );
}

/*
 * 4. Delete network-level options.
 */
delete_site_option( 'groups_db_version' );
delete_site_option( 'groups_slack_webhook_url' );
