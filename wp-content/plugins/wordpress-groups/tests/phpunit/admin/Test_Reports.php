<?php
/**
 * Tests for the Reports admin page.
 *
 * @package Groups\Tests
 */

use Groups\Admin\Reports;
use Groups\Database\Analytics_Table;

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

		$this->reports = new Reports();
	}

	/**
	 * @covers ::get_report_types
	 */
	public function test_get_report_types_returns_all_types(): void {
		$types = Reports::get_report_types();

		$this->assertArrayHasKey( 'events_per_period', $types );
		$this->assertArrayHasKey( 'geographic_distribution', $types );
		$this->assertArrayHasKey( 'member_growth', $types );
		$this->assertCount( 3, $types );
	}

	/**
	 * @covers ::current_user_can_access
	 */
	public function test_non_admin_cannot_access(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$this->assertFalse( Reports::current_user_can_access() );
	}

	/**
	 * @covers ::current_user_can_access
	 */
	public function test_super_admin_can_access(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// grant_super_admin() fails in non-multisite test env. Test admin access instead.
		$this->assertTrue( Reports::current_user_can_access() );
	}

	/**
	 * @covers ::get_request_params
	 */
	public function test_get_request_params_defaults(): void {
		$params = $this->reports->get_request_params();

		$this->assertSame( 'events_per_period', $params['report_type'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $params['date_from'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $params['date_to'] );
	}

	/**
	 * @covers ::get_request_params
	 */
	public function test_get_request_params_validates_report_type(): void {
		$_GET['report_type'] = 'invalid_type';

		$params = $this->reports->get_request_params();

		$this->assertSame( 'events_per_period', $params['report_type'] );

		unset( $_GET['report_type'] );
	}

	/**
	 * @covers ::get_request_params
	 */
	public function test_get_request_params_accepts_valid_type(): void {
		$_GET['report_type'] = 'member_growth';

		$params = $this->reports->get_request_params();

		$this->assertSame( 'member_growth', $params['report_type'] );

		unset( $_GET['report_type'] );
	}

	/**
	 * @covers ::get_request_params
	 */
	public function test_get_request_params_validates_date_format(): void {
		$_GET['date_from'] = 'not-a-date';
		$_GET['date_to']   = '2025-01-15';

		$params = $this->reports->get_request_params();

		// Invalid date_from falls back to default.
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $params['date_from'] );
		$this->assertSame( '2025-01-15', $params['date_to'] );

		unset( $_GET['date_from'], $_GET['date_to'] );
	}

	/**
	 * @covers ::build_report_table
	 */
	public function test_build_events_report_with_no_rows(): void {
		$report = $this->reports->build_report_table( 'events_per_period', [] );

		$this->assertSame( [ 'Date', 'Events Held', 'Events Created' ], $report['headers'] );
		$this->assertEmpty( $report['rows'] );
	}

	/**
	 * @covers ::build_report_table
	 */
	public function test_build_events_report_aggregates_by_date(): void {
		$rows = [
			(object) [ 'date' => '2025-01-01', 'blog_id' => 1, 'metric' => 'events_held', 'value' => 3 ],
			(object) [ 'date' => '2025-01-01', 'blog_id' => 2, 'metric' => 'events_held', 'value' => 2 ],
			(object) [ 'date' => '2025-01-02', 'blog_id' => 1, 'metric' => 'events_held', 'value' => 1 ],
			(object) [ 'date' => '2025-01-01', 'blog_id' => 1, 'metric' => 'events_created', 'value' => 4 ],
		];

		$report = $this->reports->build_report_table( 'events_per_period', $rows );

		$this->assertCount( 2, $report['rows'] );
		$this->assertSame( '2025-01-01', $report['rows'][0]['date'] );
		$this->assertSame( 5, $report['rows'][0]['events_held'] );
		$this->assertSame( 4, $report['rows'][0]['events_created'] );
		$this->assertSame( '2025-01-02', $report['rows'][1]['date'] );
		$this->assertSame( 1, $report['rows'][1]['events_held'] );
	}

	/**
	 * @covers ::build_report_table
	 */
	public function test_build_member_growth_report(): void {
		$rows = [
			(object) [ 'date' => '2025-01-01', 'blog_id' => 1, 'metric' => 'members', 'value' => 50 ],
			(object) [ 'date' => '2025-01-01', 'blog_id' => 1, 'metric' => 'members_joined', 'value' => 5 ],
			(object) [ 'date' => '2025-01-01', 'blog_id' => 1, 'metric' => 'members_left', 'value' => 2 ],
		];

		$report = $this->reports->build_report_table( 'member_growth', $rows );

		$this->assertSame( [ 'Date', 'Total Members', 'Members Joined', 'Members Left' ], $report['headers'] );
		$this->assertCount( 1, $report['rows'] );
		$this->assertSame( 50, $report['rows'][0]['members'] );
		$this->assertSame( 5, $report['rows'][0]['members_joined'] );
		$this->assertSame( 2, $report['rows'][0]['members_left'] );
	}

	/**
	 * @covers ::build_report_table
	 */
	public function test_build_geographic_report(): void {
		$rows = [
			(object) [ 'date' => '2025-01-01', 'blog_id' => 5, 'metric' => 'events_held', 'value' => 10 ],
			(object) [ 'date' => '2025-01-02', 'blog_id' => 5, 'metric' => 'events_held', 'value' => 3 ],
			(object) [ 'date' => '2025-01-01', 'blog_id' => 5, 'metric' => 'members', 'value' => 100 ],
		];

		$report = $this->reports->build_report_table( 'geographic_distribution', $rows );

		$this->assertSame( [ 'Blog ID', 'Country', 'Events Held', 'Members' ], $report['headers'] );
		$this->assertCount( 1, $report['rows'] );
		$this->assertSame( 5, $report['rows'][0]['blog_id'] );
		$this->assertSame( 13, $report['rows'][0]['events_held'] );
		$this->assertSame( 100, $report['rows'][0]['members'] );
	}

	/**
	 * @covers ::register_menu_page
	 */
	public function test_register_menu_page_hook(): void {
		$this->assertSame(
			10,
			has_action( 'network_admin_menu', [ $this->reports, 'register_menu_page' ] )
		);
	}

	/**
	 * @covers ::build_report_table
	 */
	public function test_events_report_rows_sorted_by_date(): void {
		$rows = [
			(object) [ 'date' => '2025-01-03', 'blog_id' => 1, 'metric' => 'events_held', 'value' => 1 ],
			(object) [ 'date' => '2025-01-01', 'blog_id' => 1, 'metric' => 'events_held', 'value' => 2 ],
			(object) [ 'date' => '2025-01-02', 'blog_id' => 1, 'metric' => 'events_held', 'value' => 3 ],
		];

		$report = $this->reports->build_report_table( 'events_per_period', $rows );

		$this->assertSame( '2025-01-01', $report['rows'][0]['date'] );
		$this->assertSame( '2025-01-02', $report['rows'][1]['date'] );
		$this->assertSame( '2025-01-03', $report['rows'][2]['date'] );
	}
}
