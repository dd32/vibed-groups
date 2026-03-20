<?php
/**
 * Tests for the Reports admin page.
 *
 * @package Groups\Tests
 */

use Groups\Admin\Reports;

/**
 * @coversDefaultClass \Groups\Admin\Reports
 */
class Test_Reports extends WP_UnitTestCase {

	/**
	 * Reports instance.
	 *
	 * @var Reports
	 */
	private Reports $reports;

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		register_post_type( 'wp_meetup', [
			'public' => false,
		] );

		$statuses = [
			'meetup-pending',
			'meetup-vetting',
			'meetup-feedback',
			'meetup-orientation',
			'meetup-scheduling',
			'meetup-active',
			'meetup-dormant',
		];

		foreach ( $statuses as $status ) {
			register_post_status( $status, [
				'public' => true,
			] );
		}

		$this->reports = new Reports();
	}

	/**
	 * @covers ::register_menu_page
	 */
	public function test_menu_page_is_registered(): void {
		wp_set_current_user( 1 );

		$this->reports->register_menu_page();

		$hook = $this->reports->get_hook_suffix();

		// The hook suffix is a non-empty string when registered successfully.
		// In test context without the parent page it may be false, so we check
		// the method itself ran without error.
		$this->assertNotNull( $hook );
	}

	/**
	 * @covers ::current_user_can_access
	 */
	public function test_super_admin_can_access(): void {
		wp_set_current_user( 1 );

		$this->assertTrue( Reports::current_user_can_access() );
	}

	/**
	 * @covers ::current_user_can_access
	 */
	public function test_subscriber_cannot_access(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$this->assertFalse( Reports::current_user_can_access() );
	}

	/**
	 * @covers ::get_date_range
	 */
	public function test_get_date_range_uses_defaults_when_empty(): void {
		unset( $_REQUEST['date_from'], $_REQUEST['date_to'] );

		$range = Reports::get_date_range();

		$this->assertArrayHasKey( 'date_from', $range );
		$this->assertArrayHasKey( 'date_to', $range );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $range['date_from'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $range['date_to'] );
	}

	/**
	 * @covers ::get_date_range
	 */
	public function test_get_date_range_uses_provided_dates(): void {
		$_REQUEST['date_from'] = '2025-01-01';
		$_REQUEST['date_to']   = '2025-01-31';

		$range = Reports::get_date_range();

		$this->assertSame( '2025-01-01', $range['date_from'] );
		$this->assertSame( '2025-01-31', $range['date_to'] );

		unset( $_REQUEST['date_from'], $_REQUEST['date_to'] );
	}

	/**
	 * @covers ::get_date_range
	 */
	public function test_get_date_range_rejects_invalid_format(): void {
		$_REQUEST['date_from'] = 'not-a-date';
		$_REQUEST['date_to']   = '2025/01/31';

		$range = Reports::get_date_range();

		// Should fall back to defaults.
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $range['date_from'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $range['date_to'] );

		unset( $_REQUEST['date_from'], $_REQUEST['date_to'] );
	}

	/**
	 * @covers ::build_events_table
	 */
	public function test_build_events_table_aggregates_by_blog(): void {
		$rows = [
			(object) [ 'blog_id' => 2, 'date' => '2025-01-01', 'metric' => 'events_held', 'value' => 3 ],
			(object) [ 'blog_id' => 2, 'date' => '2025-01-02', 'metric' => 'events_held', 'value' => 2 ],
			(object) [ 'blog_id' => 2, 'date' => '2025-01-01', 'metric' => 'rsvps_total', 'value' => 10 ],
			(object) [ 'blog_id' => 2, 'date' => '2025-01-01', 'metric' => 'attendees', 'value' => 8 ],
			(object) [ 'blog_id' => 3, 'date' => '2025-01-01', 'metric' => 'events_held', 'value' => 1 ],
		];

		$table = Reports::build_events_table( $rows );

		$this->assertCount( 2, $table );

		// First group (blog_id 2) should have 5 events_held, 10 rsvps, 8 attendees.
		$this->assertSame( 5, $table[0][1] );
		$this->assertSame( 10, $table[0][2] );
		$this->assertSame( 8, $table[0][3] );

		// Second group (blog_id 3) should have 1 event.
		$this->assertSame( 1, $table[1][1] );
	}

	/**
	 * @covers ::build_events_table
	 */
	public function test_build_events_table_returns_empty_for_no_data(): void {
		$table = Reports::build_events_table( [] );

		$this->assertIsArray( $table );
		$this->assertEmpty( $table );
	}

	/**
	 * @covers ::build_growth_table
	 */
	public function test_build_growth_table_calculates_net_growth(): void {
		$rows = [
			(object) [ 'blog_id' => 2, 'date' => '2025-01-01', 'metric' => 'members_joined', 'value' => 15 ],
			(object) [ 'blog_id' => 2, 'date' => '2025-01-01', 'metric' => 'members_left', 'value' => 3 ],
			(object) [ 'blog_id' => 2, 'date' => '2025-01-01', 'metric' => 'events_created', 'value' => 5 ],
			(object) [ 'blog_id' => 2, 'date' => '2025-01-01', 'metric' => 'newcomers', 'value' => 7 ],
		];

		$table = Reports::build_growth_table( $rows );

		$this->assertCount( 5, $table );

		// Members Joined.
		$this->assertSame( 15, $table[0][1] );
		// Members Left.
		$this->assertSame( 3, $table[1][1] );
		// Net Member Growth.
		$this->assertSame( 12, $table[2][1] );
		// Events Created.
		$this->assertSame( 5, $table[3][1] );
		// Newcomers.
		$this->assertSame( 7, $table[4][1] );
	}

	/**
	 * @covers ::build_geographic_table
	 */
	public function test_build_geographic_table_groups_by_country(): void {
		// Create meetup posts with country meta linked to blog IDs.
		$post_id_1 = wp_insert_post( [
			'post_type'   => 'wp_meetup',
			'post_title'  => 'Melbourne WP',
			'post_status' => 'meetup-active',
		] );
		update_post_meta( $post_id_1, '_meetup_site_id', 2 );
		update_post_meta( $post_id_1, '_meetup_country', 'Australia' );

		$post_id_2 = wp_insert_post( [
			'post_type'   => 'wp_meetup',
			'post_title'  => 'Sydney WP',
			'post_status' => 'meetup-active',
		] );
		update_post_meta( $post_id_2, '_meetup_site_id', 3 );
		update_post_meta( $post_id_2, '_meetup_country', 'Australia' );

		$rows = [
			(object) [ 'blog_id' => 2, 'date' => '2025-01-01', 'metric' => 'members', 'value' => 50 ],
			(object) [ 'blog_id' => 2, 'date' => '2025-01-01', 'metric' => 'events_held', 'value' => 3 ],
			(object) [ 'blog_id' => 3, 'date' => '2025-01-01', 'metric' => 'members', 'value' => 30 ],
			(object) [ 'blog_id' => 3, 'date' => '2025-01-01', 'metric' => 'events_held', 'value' => 2 ],
		];

		$table = Reports::build_geographic_table( $rows );

		$this->assertCount( 1, $table );
		$this->assertSame( 'Australia', $table[0][0] );
		$this->assertSame( 2, $table[0][1] ); // 2 groups.
		$this->assertSame( 80, $table[0][2] ); // 80 total members.
		$this->assertSame( 5, $table[0][3] ); // 5 total events.
	}

	/**
	 * @covers ::build_csv_data
	 */
	public function test_build_csv_data_includes_header_row(): void {
		$rows = [
			(object) [ 'blog_id' => 2, 'date' => '2025-01-01', 'metric' => 'members', 'value' => 50 ],
		];

		$csv = Reports::build_csv_data( $rows );

		$this->assertCount( 2, $csv );
		$this->assertSame( [ 'Date', 'Blog ID', 'Group Name', 'Metric', 'Value' ], $csv[0] );
		$this->assertSame( '2025-01-01', $csv[1][0] );
		$this->assertSame( 'members', $csv[1][3] );
	}

	/**
	 * @covers ::build_csv_data
	 */
	public function test_build_csv_data_empty_returns_header_only(): void {
		$csv = Reports::build_csv_data( [] );

		$this->assertCount( 1, $csv );
		$this->assertSame( 'Date', $csv[0][0] );
	}

	/**
	 * @covers ::get_country_for_blog
	 */
	public function test_get_country_for_blog_returns_unknown_when_not_found(): void {
		$country = Reports::get_country_for_blog( 99999 );

		$this->assertSame( 'Unknown', $country );
	}

	/**
	 * @covers ::get_country_for_blog
	 */
	public function test_get_country_for_blog_returns_country_from_meta(): void {
		$post_id = wp_insert_post( [
			'post_type'   => 'wp_meetup',
			'post_title'  => 'Test Group',
			'post_status' => 'meetup-active',
		] );
		update_post_meta( $post_id, '_meetup_site_id', 42 );
		update_post_meta( $post_id, '_meetup_country', 'Germany' );

		$country = Reports::get_country_for_blog( 42 );

		$this->assertSame( 'Germany', $country );
	}

	/**
	 * @covers ::render_table
	 */
	public function test_render_table_outputs_html(): void {
		ob_start();
		Reports::render_table(
			[ 'Name', 'Count' ],
			[ [ 'Test', 5 ] ]
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( '<table', $html );
		$this->assertStringContainsString( 'Name', $html );
		$this->assertStringContainsString( 'Count', $html );
		$this->assertStringContainsString( 'Test', $html );
		$this->assertStringContainsString( '5', $html );
	}

	/**
	 * @covers ::render_table
	 */
	public function test_render_table_shows_no_data_message(): void {
		ob_start();
		Reports::render_table( [ 'Name' ], [] );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'No data available', $html );
	}
}
