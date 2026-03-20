<?php
/**
 * Tests for the Application_Tracker list table.
 *
 * @package Groups\Tests
 */

use Groups\Admin\Application_Tracker;

/**
 * @coversDefaultClass \Groups\Admin\Application_Tracker
 */
class Test_Application_Tracker extends WP_UnitTestCase {

	/**
	 * Tracker instance.
	 *
	 * @var Application_Tracker
	 */
	private Application_Tracker $tracker;

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Register the wp_meetup post type for testing.
		register_post_type( 'wp_meetup', [
			'public' => false,
		] );

		// Register all meetup statuses.
		$statuses = [
			'meetup-pending',
			'meetup-vetting',
			'meetup-feedback',
			'meetup-orientation',
			'meetup-scheduling',
			'meetup-active',
			'meetup-dormant',
			'meetup-suspended',
			'meetup-removed',
			'meetup-declined',
		];

		foreach ( $statuses as $status ) {
			register_post_status( $status, [
				'public' => true,
			] );
		}

		$this->tracker = new Application_Tracker();
	}

	/**
	 * Helper to create a wp_meetup post with a given status.
	 *
	 * @param string $status Initial post status.
	 * @param array  $meta   Optional post meta.
	 * @return int Post ID.
	 */
	private function create_meetup_post( string $status = 'meetup-pending', array $meta = [] ): int {
		$post_id = wp_insert_post( [
			'post_type'   => 'wp_meetup',
			'post_title'  => 'Test Group',
			'post_status' => $status,
		] );

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return $post_id;
	}

	/**
	 * @covers ::register_menu_page
	 */
	public function test_menu_page_is_registered(): void {
		// Use an existing super admin (user 1 is super admin in test suite).
		wp_set_current_user( 1 );

		// Fire the hook.
		$this->tracker->register_menu_page();

		global $menu;

		$found = false;

		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && 'group-applications' === $item[2] ) {
					$found = true;
					break;
				}
			}
		}

		$this->assertTrue( $found, 'The group-applications menu page should be registered.' );
	}

	/**
	 * @covers ::get_columns
	 */
	public function test_list_table_returns_correct_columns(): void {
		$columns = $this->tracker->get_columns();

		$this->assertArrayHasKey( 'cb', $columns );
		$this->assertArrayHasKey( 'group_name', $columns );
		$this->assertArrayHasKey( 'city_country', $columns );
		$this->assertArrayHasKey( 'organizer', $columns );
		$this->assertArrayHasKey( 'status', $columns );
		$this->assertArrayHasKey( 'applied_date', $columns );
		$this->assertArrayHasKey( 'deputy', $columns );
		$this->assertCount( 7, $columns );
	}

	/**
	 * @covers ::get_sortable_columns
	 */
	public function test_sortable_columns(): void {
		$sortable = $this->tracker->get_sortable_columns();

		$this->assertArrayHasKey( 'group_name', $sortable );
		$this->assertArrayHasKey( 'applied_date', $sortable );
		$this->assertArrayHasKey( 'status', $sortable );
	}

	/**
	 * @covers ::get_bulk_actions
	 */
	public function test_bulk_actions(): void {
		$actions = $this->tracker->get_bulk_actions();

		$this->assertArrayHasKey( 'approve', $actions );
		$this->assertArrayHasKey( 'decline', $actions );
		$this->assertArrayHasKey( 'request_feedback', $actions );
	}

	/**
	 * @covers ::prepare_items
	 */
	public function test_status_filter_works(): void {
		$this->create_meetup_post( 'meetup-pending' );
		$this->create_meetup_post( 'meetup-vetting' );
		$this->create_meetup_post( 'meetup-vetting' );

		// Simulate status filter via $_REQUEST.
		$_REQUEST['meetup_status'] = 'meetup-vetting';

		$this->tracker->prepare_items();

		$this->assertCount( 2, $this->tracker->items, 'Status filter should return only vetting posts.' );

		foreach ( $this->tracker->items as $item ) {
			$this->assertSame( 'meetup-vetting', $item->post_status );
		}

		unset( $_REQUEST['meetup_status'] );
	}

	/**
	 * @covers ::prepare_items
	 */
	public function test_prepare_items_returns_all_without_filter(): void {
		$this->create_meetup_post( 'meetup-pending' );
		$this->create_meetup_post( 'meetup-active' );

		$this->tracker->prepare_items();

		$this->assertCount( 2, $this->tracker->items );
	}

	/**
	 * @covers ::add_note
	 * @covers ::get_notes
	 */
	public function test_add_and_get_notes(): void {
		$post_id = $this->create_meetup_post();

		$result = Application_Tracker::add_note( $post_id, 'Test note content', 1 );

		$this->assertTrue( $result );

		$notes = Application_Tracker::get_notes( $post_id );

		$this->assertCount( 1, $notes );
		$this->assertSame( 'Test note content', $notes[0]['note'] );
		$this->assertSame( 1, $notes[0]['user_id'] );
		$this->assertArrayHasKey( 'date', $notes[0] );
	}

	/**
	 * @covers ::add_note
	 */
	public function test_add_note_rejects_non_meetup_post(): void {
		$post_id = self::factory()->post->create( [ 'post_type' => 'post' ] );

		$result = Application_Tracker::add_note( $post_id, 'Should fail' );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::get_notes
	 */
	public function test_get_notes_returns_empty_array_for_no_notes(): void {
		$post_id = $this->create_meetup_post();

		$notes = Application_Tracker::get_notes( $post_id );

		$this->assertIsArray( $notes );
		$this->assertEmpty( $notes );
	}

	/**
	 * @covers ::get_statuses
	 */
	public function test_get_statuses_returns_all_filter_statuses(): void {
		$statuses = Application_Tracker::get_statuses();

		$this->assertArrayHasKey( 'meetup-pending', $statuses );
		$this->assertArrayHasKey( 'meetup-vetting', $statuses );
		$this->assertArrayHasKey( 'meetup-feedback', $statuses );
		$this->assertArrayHasKey( 'meetup-orientation', $statuses );
		$this->assertArrayHasKey( 'meetup-scheduling', $statuses );
		$this->assertArrayHasKey( 'meetup-active', $statuses );
		$this->assertArrayHasKey( 'meetup-dormant', $statuses );
	}

	/**
	 * @covers ::current_user_can_access
	 */
	public function test_super_admin_can_access(): void {
		// User 1 is super admin in multisite test suite.
		wp_set_current_user( 1 );

		$this->assertTrue( Application_Tracker::current_user_can_access() );
	}

	/**
	 * @covers ::current_user_can_access
	 */
	public function test_subscriber_cannot_access(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$this->assertFalse( Application_Tracker::current_user_can_access() );
	}

	/**
	 * @covers ::column_status
	 */
	public function test_column_status_renders_label(): void {
		$post_id = $this->create_meetup_post( 'meetup-pending' );
		$post    = get_post( $post_id );

		$output = $this->tracker->column_status( $post );

		$this->assertSame( 'Pending', $output );
	}

	/**
	 * @covers ::column_city_country
	 */
	public function test_column_city_country_renders_meta(): void {
		$post_id = $this->create_meetup_post( 'meetup-pending', [
			'_meetup_city'    => 'Melbourne',
			'_meetup_country' => 'Australia',
		] );
		$post = get_post( $post_id );

		$output = $this->tracker->column_city_country( $post );

		$this->assertSame( 'Melbourne, Australia', $output );
	}
}
