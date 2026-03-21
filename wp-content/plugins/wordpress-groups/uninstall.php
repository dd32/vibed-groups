<?php
/**
 * Plugin uninstall handler.
 *
 * Cleans up all data created by the WordPress Groups plugin:
 * - Custom database tables (groups_analytics_daily, groups_activity_log)
 * - Custom roles (organizer, co_organizer, member)
 * - Scheduled cron events
 * - Network options (groups_db_version)
 *
 * @package Groups
 */

// Abort if not called by WordPress during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Drop custom database tables.
 */
function groups_uninstall_drop_tables() {
	global $wpdb;

	$base_prefix = $wpdb->base_prefix;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$base_prefix}groups_analytics_daily" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$base_prefix}groups_activity_log" );
}

/**
 * Remove custom roles from all sites in the network.
 */
function groups_uninstall_remove_roles() {
	$role_slugs = [ 'organizer', 'co_organizer', 'member' ];

	if ( is_multisite() ) {
		$site_ids = get_sites( [
			'fields' => 'ids',
			'number' => 0,
		] );

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );

			foreach ( $role_slugs as $role ) {
				remove_role( $role );
			}

			restore_current_blog();
		}
	} else {
		foreach ( $role_slugs as $role ) {
			remove_role( $role );
		}
	}
}

/**
 * Unschedule all plugin cron hooks.
 */
function groups_uninstall_clear_cron() {
	$cron_hooks = [
		'groups_daily_aggregation',
		'groups_dormancy_check',
		'groups_generate_recurring_events',
		'groups_event_reminder_24h',
		'groups_event_reminder_1h',
	];

	foreach ( $cron_hooks as $hook ) {
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}

		// Also clear any remaining events for this hook.
		wp_unschedule_hook( $hook );
	}
}

/**
 * Delete network options created by the plugin.
 */
function groups_uninstall_delete_options() {
	delete_site_option( 'groups_db_version' );
}

// Run all cleanup routines.
groups_uninstall_drop_tables();
groups_uninstall_remove_roles();
groups_uninstall_clear_cron();
groups_uninstall_delete_options();
