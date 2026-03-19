<?php
/**
 * Tests for the Activity_Log_Table query builder.
 *
 * @package Groups\Tests\Database
 */

namespace Groups\Tests\Database;

use Groups\Database\Activity_Log_Table;
use Groups\Database\Schema;
use WP_UnitTestCase;

/**
 * @group database
 */
class Test_Activity_Log_Table extends WP_UnitTestCase {

	/**
	 * Ensure tables exist before each test.
	 */
	public function set_up() {
		parent::set_up();

		Schema::create_tables();
	}

	/**
	 * Clean up the activity log table after each test.
	 */
	public function tear_down() {
		global $wpdb;

		$table = $wpdb->base_prefix . 'groups_activity_log';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$table}" );

		parent::tear_down();
	}

	/**
	 * Test inserting a log entry and querying it back.
	 */
	public function test_insert_and_query() {
		$id = Activity_Log_Table::insert( 2, 1, 'member_joined', 0 );
		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );

		$rows = Activity_Log_Table::query( [ 'blog_id' => 2 ] );
		$this->assertCount( 1, $rows );
		$this->assertEquals( 2, $rows[0]->blog_id );
		$this->assertEquals( 1, $rows[0]->user_id );
		$this->assertSame( 'member_joined', $rows[0]->action );
		$this->assertEquals( 0, $rows[0]->object_id );
	}

	/**
	 * Test filtering by action type.
	 */
	public function test_filter_by_action() {
		Activity_Log_Table::insert( 2, 1, 'member_joined', 0 );
		Activity_Log_Table::insert( 2, 1, 'event_created', 10 );
		Activity_Log_Table::insert( 2, 2, 'member_joined', 0 );

		$rows = Activity_Log_Table::query( [ 'action' => 'member_joined' ] );
		$this->assertCount( 2, $rows );

		foreach ( $rows as $row ) {
			$this->assertSame( 'member_joined', $row->action );
		}
	}

	/**
	 * Test filtering by user ID.
	 */
	public function test_filter_by_user() {
		Activity_Log_Table::insert( 2, 1, 'member_joined', 0 );
		Activity_Log_Table::insert( 2, 2, 'member_joined', 0 );
		Activity_Log_Table::insert( 2, 1, 'event_created', 10 );

		$rows = Activity_Log_Table::query( [ 'user_id' => 1 ] );
		$this->assertCount( 2, $rows );
	}

	/**
	 * Test meta JSON encoding and decoding.
	 */
	public function test_meta_json_encoding_decoding() {
		$meta = [
			'previous_status' => 'meetup-pending',
			'new_status'      => 'meetup-active',
			'reason'          => 'Approved by deputy',
		];

		$id = Activity_Log_Table::insert( 2, 1, 'status_changed', 5, $meta );
		$this->assertIsInt( $id );

		$rows = Activity_Log_Table::query( [ 'blog_id' => 2 ] );
		$this->assertCount( 1, $rows );

		// Meta should be decoded back to an associative array.
		$this->assertIsArray( $rows[0]->meta );
		$this->assertSame( 'meetup-pending', $rows[0]->meta['previous_status'] );
		$this->assertSame( 'meetup-active', $rows[0]->meta['new_status'] );
		$this->assertSame( 'Approved by deputy', $rows[0]->meta['reason'] );
	}

	/**
	 * Test that empty meta is returned as an empty array.
	 */
	public function test_empty_meta_returns_array() {
		Activity_Log_Table::insert( 2, 1, 'member_joined', 0 );

		$rows = Activity_Log_Table::query( [ 'blog_id' => 2 ] );
		$this->assertSame( [], $rows[0]->meta );
	}

	/**
	 * Test get_recent convenience method.
	 */
	public function test_get_recent() {
		// Insert entries for blog 2.
		for ( $i = 1; $i <= 5; $i++ ) {
			Activity_Log_Table::insert( 2, $i, 'member_joined', 0 );
		}

		// Insert entries for blog 3 (should not appear).
		Activity_Log_Table::insert( 3, 1, 'member_joined', 0 );

		$rows = Activity_Log_Table::get_recent( 2, 3 );
		$this->assertCount( 3, $rows );

		foreach ( $rows as $row ) {
			$this->assertEquals( 2, $row->blog_id );
		}
	}

	/**
	 * Test get_recent uses default limit of 20.
	 */
	public function test_get_recent_default_limit() {
		for ( $i = 1; $i <= 25; $i++ ) {
			Activity_Log_Table::insert( 2, 1, 'member_joined', 0 );
		}

		$rows = Activity_Log_Table::get_recent( 2 );
		$this->assertCount( 20, $rows );
	}

	/**
	 * Test date range filtering on activity log.
	 */
	public function test_date_range_filtering() {
		global $wpdb;

		$table = $wpdb->base_prefix . 'groups_activity_log';

		// Insert rows with specific timestamps.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			[
				'blog_id'    => 2,
				'user_id'    => 1,
				'action'     => 'event_created',
				'object_id'  => 10,
				'created_at' => '2026-01-10 12:00:00',
			],
			[ '%d', '%d', '%s', '%d', '%s' ]
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			[
				'blog_id'    => 2,
				'user_id'    => 1,
				'action'     => 'event_created',
				'object_id'  => 11,
				'created_at' => '2026-01-20 12:00:00',
			],
			[ '%d', '%d', '%s', '%d', '%s' ]
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			[
				'blog_id'    => 2,
				'user_id'    => 1,
				'action'     => 'event_created',
				'object_id'  => 12,
				'created_at' => '2026-02-05 12:00:00',
			],
			[ '%d', '%d', '%s', '%d', '%s' ]
		);

		$rows = Activity_Log_Table::query(
			[
				'date_from' => '2026-01-15',
				'date_to'   => '2026-01-31',
			]
		);

		$this->assertCount( 1, $rows );
		$this->assertEquals( 11, $rows[0]->object_id );
	}

	/**
	 * Test pagination with limit and offset.
	 */
	public function test_pagination() {
		for ( $i = 1; $i <= 10; $i++ ) {
			Activity_Log_Table::insert( 2, 1, 'member_joined', $i );
		}

		$page1 = Activity_Log_Table::query( [ 'limit' => 3, 'offset' => 0 ] );
		$page2 = Activity_Log_Table::query( [ 'limit' => 3, 'offset' => 3 ] );

		$this->assertCount( 3, $page1 );
		$this->assertCount( 3, $page2 );

		// Ensure different rows.
		$ids_page1 = array_map( fn( $r ) => $r->id, $page1 );
		$ids_page2 = array_map( fn( $r ) => $r->id, $page2 );
		$this->assertEmpty( array_intersect( $ids_page1, $ids_page2 ) );
	}
}
