<?php
/**
 * Tests for the Analytics_Table query builder.
 *
 * @package Groups\Tests\Database
 */


use Groups\Database\Analytics_Table;
use Groups\Database\Schema;

/**
 * @group database
 */
class Test_Analytics_Table extends WP_UnitTestCase {

	/**
	 * Ensure tables exist before each test.
	 */
	public function set_up() {
		parent::set_up();

		Schema::create_tables();
	}

	/**
	 * Clean up the analytics table after each test.
	 */
	public function tear_down() {
		global $wpdb;

		$table = $wpdb->base_prefix . 'groups_analytics_daily';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$table}" );

		parent::tear_down();
	}

	/**
	 * Test inserting a metric and querying it back.
	 */
	public function test_insert_and_query() {
		$result = Analytics_Table::insert( '2026-01-15', 2, 'members', 42 );
		$this->assertNotFalse( $result );

		$rows = Analytics_Table::query( [ 'blog_id' => 2 ] );
		$this->assertCount( 1, $rows );
		$this->assertSame( '2026-01-15', $rows[0]->date );
		$this->assertEquals( 2, $rows[0]->blog_id );
		$this->assertSame( 'members', $rows[0]->metric );
		$this->assertEquals( 42, $rows[0]->value );
	}

	/**
	 * Test that inserting the same date/blog/metric updates the value.
	 */
	public function test_insert_upsert() {
		Analytics_Table::insert( '2026-01-15', 2, 'members', 42 );
		Analytics_Table::insert( '2026-01-15', 2, 'members', 50 );

		$rows = Analytics_Table::query( [ 'blog_id' => 2, 'metric' => 'members' ] );
		$this->assertCount( 1, $rows );
		$this->assertEquals( 50, $rows[0]->value );
	}

	/**
	 * Test date range filtering.
	 */
	public function test_date_range_filtering() {
		Analytics_Table::insert( '2026-01-10', 2, 'events_held', 1 );
		Analytics_Table::insert( '2026-01-15', 2, 'events_held', 2 );
		Analytics_Table::insert( '2026-01-20', 2, 'events_held', 3 );
		Analytics_Table::insert( '2026-01-25', 2, 'events_held', 4 );

		$rows = Analytics_Table::query(
			[
				'date_from' => '2026-01-12',
				'date_to'   => '2026-01-22',
			]
		);

		$this->assertCount( 2, $rows );
		$this->assertSame( '2026-01-15', $rows[0]->date );
		$this->assertSame( '2026-01-20', $rows[1]->date );
	}

	/**
	 * Test metric filtering.
	 */
	public function test_metric_filtering() {
		Analytics_Table::insert( '2026-01-15', 2, 'members', 42 );
		Analytics_Table::insert( '2026-01-15', 2, 'events_held', 5 );
		Analytics_Table::insert( '2026-01-15', 2, 'rsvps_total', 30 );

		$rows = Analytics_Table::query( [ 'metric' => 'events_held' ] );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'events_held', $rows[0]->metric );
	}

	/**
	 * Test summary aggregation for a single group.
	 */
	public function test_get_summary() {
		Analytics_Table::insert( '2026-01-10', 2, 'members', 10 );
		Analytics_Table::insert( '2026-01-11', 2, 'members', 12 );
		Analytics_Table::insert( '2026-01-10', 2, 'events_held', 1 );
		Analytics_Table::insert( '2026-01-11', 2, 'events_held', 2 );

		// Data for a different group that should NOT be included.
		Analytics_Table::insert( '2026-01-10', 3, 'members', 100 );

		$summary = Analytics_Table::get_summary( 2, '2026-01-01', '2026-01-31' );

		$this->assertArrayHasKey( 'members', $summary );
		$this->assertArrayHasKey( 'events_held', $summary );
		$this->assertSame( 22, $summary['members'] );
		$this->assertSame( 3, $summary['events_held'] );
	}

	/**
	 * Test that summary returns an empty array when no data matches.
	 */
	public function test_get_summary_empty() {
		$summary = Analytics_Table::get_summary( 999, '2026-01-01', '2026-01-31' );
		$this->assertSame( [], $summary );
	}

	/**
	 * Test network summary aggregation across all groups.
	 */
	public function test_get_network_summary() {
		Analytics_Table::insert( '2026-01-10', 2, 'members', 10 );
		Analytics_Table::insert( '2026-01-10', 3, 'members', 20 );
		Analytics_Table::insert( '2026-01-11', 2, 'events_held', 1 );
		Analytics_Table::insert( '2026-01-11', 3, 'events_held', 3 );

		// Outside the date range — should be excluded.
		Analytics_Table::insert( '2026-02-15', 2, 'members', 999 );

		$summary = Analytics_Table::get_network_summary( '2026-01-01', '2026-01-31' );

		$this->assertArrayHasKey( 'members', $summary );
		$this->assertArrayHasKey( 'events_held', $summary );
		$this->assertSame( 30, $summary['members'] );
		$this->assertSame( 4, $summary['events_held'] );
	}

	/**
	 * Test network summary returns empty when no data matches.
	 */
	public function test_get_network_summary_empty() {
		$summary = Analytics_Table::get_network_summary( '2030-01-01', '2030-01-31' );
		$this->assertSame( [], $summary );
	}

	/**
	 * Test query with no filters returns all rows.
	 */
	public function test_query_no_filters() {
		Analytics_Table::insert( '2026-01-10', 2, 'members', 10 );
		Analytics_Table::insert( '2026-01-11', 3, 'events_held', 5 );

		$rows = Analytics_Table::query();
		$this->assertCount( 2, $rows );
	}
}
