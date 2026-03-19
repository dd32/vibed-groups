<?php
/**
 * Tests for the database schema creation.
 *
 * @package Groups\Tests\Database
 */


use Groups\Database\Schema;

/**
 * @group database
 */
class Test_Schema extends WP_UnitTestCase {

	/**
	 * Test that both custom tables are created after calling create_tables().
	 */
	public function test_create_tables() {
		global $wpdb;

		Schema::create_tables();

		$analytics_table = $wpdb->base_prefix . 'groups_analytics_daily';
		$activity_table  = $wpdb->base_prefix . 'groups_activity_log';

		// Verify tables exist by querying SHOW TABLES.
		$tables = $wpdb->get_col( 'SHOW TABLES' );

		$this->assertContains( $analytics_table, $tables, 'groups_analytics_daily table should exist.' );
		$this->assertContains( $activity_table, $tables, 'groups_activity_log table should exist.' );
	}

	/**
	 * Test that the analytics_daily table has the expected columns.
	 */
	public function test_analytics_daily_columns() {
		global $wpdb;

		Schema::create_tables();

		$table   = $wpdb->base_prefix . 'groups_analytics_daily';
		$columns = $wpdb->get_results( "DESCRIBE {$table}" );
		$col_names = wp_list_pluck( $columns, 'Field' );

		$expected = array( 'id', 'date', 'blog_id', 'metric', 'value' );
		foreach ( $expected as $col ) {
			$this->assertContains( $col, $col_names, "Column {$col} should exist in groups_analytics_daily." );
		}
	}

	/**
	 * Test that the activity_log table has the expected columns.
	 */
	public function test_activity_log_columns() {
		global $wpdb;

		Schema::create_tables();

		$table   = $wpdb->base_prefix . 'groups_activity_log';
		$columns = $wpdb->get_results( "DESCRIBE {$table}" );
		$col_names = wp_list_pluck( $columns, 'Field' );

		$expected = array( 'id', 'blog_id', 'user_id', 'action', 'object_id', 'meta', 'created_at' );
		foreach ( $expected as $col ) {
			$this->assertContains( $col, $col_names, "Column {$col} should exist in groups_activity_log." );
		}
	}

	/**
	 * Test that the schema version is stored after table creation.
	 */
	public function test_schema_version_stored() {
		Schema::create_tables();

		$version = Schema::get_schema_version();
		$this->assertSame( Schema::VERSION, $version, 'Schema version should be stored as a network option.' );
	}

	/**
	 * Test get_schema_version returns 0 when not set.
	 */
	public function test_schema_version_default() {
		delete_site_option( Schema::VERSION_OPTION );

		$this->assertSame( 0, Schema::get_schema_version(), 'Schema version should default to 0.' );
	}

	/**
	 * Test update_schema_version stores the correct value.
	 */
	public function test_update_schema_version() {
		Schema::update_schema_version( 5 );

		$this->assertSame( 5, Schema::get_schema_version() );
	}

	/**
	 * Test that create_tables is idempotent (can be called multiple times).
	 */
	public function test_create_tables_idempotent() {
		Schema::create_tables();
		Schema::create_tables();

		global $wpdb;
		$tables = $wpdb->get_col( 'SHOW TABLES' );

		$analytics_table = $wpdb->base_prefix . 'groups_analytics_daily';
		$activity_table  = $wpdb->base_prefix . 'groups_activity_log';

		$this->assertContains( $analytics_table, $tables );
		$this->assertContains( $activity_table, $tables );
	}
}
