<?php
/**
 * Database schema for network-wide custom tables.
 *
 * @package Groups\Database
 */

namespace Groups\Database;

/**
 * Manages creation and versioning of the two network-wide custom tables:
 * - groups_analytics_daily
 * - groups_activity_log
 */
class Schema {

	/**
	 * Current schema version.
	 */
	const VERSION = 1;

	/**
	 * Option key for storing the schema version (network option).
	 */
	const VERSION_OPTION = 'groups_db_version';

	/**
	 * Create or update both custom tables using dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$base_prefix     = $wpdb->base_prefix;

		$sql = self::get_schema( $base_prefix, $charset_collate );

		dbDelta( $sql );

		self::update_schema_version( self::VERSION );
	}

	/**
	 * Return the full SQL schema for both tables.
	 *
	 * @param string $base_prefix     The network base prefix.
	 * @param string $charset_collate The charset collate string.
	 * @return string SQL statements separated by semicolons.
	 */
	public static function get_schema( $base_prefix, $charset_collate ) {
		$analytics_table = "{$base_prefix}groups_analytics_daily";
		$activity_table  = "{$base_prefix}groups_activity_log";

		return "CREATE TABLE {$analytics_table} (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	date DATE NOT NULL,
	blog_id BIGINT UNSIGNED NOT NULL,
	metric VARCHAR(50) NOT NULL,
	value BIGINT NOT NULL DEFAULT 0,
	PRIMARY KEY  (id),
	UNIQUE KEY date_blog_metric (date, blog_id, metric),
	KEY blog_id (blog_id)
) {$charset_collate};
CREATE TABLE {$activity_table} (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	blog_id BIGINT UNSIGNED NOT NULL,
	user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
	action VARCHAR(50) NOT NULL,
	object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
	meta LONGTEXT,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY blog_id_created (blog_id, created_at),
	KEY action_created (action, created_at)
) {$charset_collate};";
	}

	/**
	 * Get the stored schema version.
	 *
	 * @return int The stored version, or 0 if not set.
	 */
	public static function get_schema_version() {
		return (int) get_site_option( self::VERSION_OPTION, 0 );
	}

	/**
	 * Update the stored schema version.
	 *
	 * @param int $version The version number to store.
	 */
	public static function update_schema_version( $version ) {
		update_site_option( self::VERSION_OPTION, (int) $version );
	}
}
