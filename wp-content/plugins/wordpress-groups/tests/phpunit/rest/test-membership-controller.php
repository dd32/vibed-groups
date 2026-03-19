<?php
/**
 * Tests for the Membership REST API controller.
 *
 * @package Groups\Tests\REST
 */

namespace Groups\Tests\REST;

use Groups\Models\Membership;
use Groups\REST\Membership_Controller;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * @coversDefaultClass \Groups\REST\Membership_Controller
 * @group rest-api
 * @group multisite
 */
class Test_Membership_Controller extends WP_UnitTestCase {

	/**
	 * REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	private WP_REST_Server $server;

	/**
	 * Test blog ID (a secondary site in the multisite network).
	 *
	 * @var int
	 */
	private int $blog_id;

	/**
	 * A regular user ID for join/leave operations.
	 *
	 * @var int
	 */
	private int $user_id;

	/**
	 * An organizer user ID.
	 *
	 * @var int
	 */
	private int $organizer_id;

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite tests require a multisite installation.' );
		}

		global $wp_rest_server;
		$this->server = $wp_rest_server = new WP_REST_Server();

		$this->blog_id = self::factory()->blog->create();

		// Register custom roles on the test site.
		switch_to_blog( $this->blog_id );
		Membership::register_roles();

		$controller = new Membership_Controller();
		$controller->register_routes();

		restore_current_blog();

		// Create users.
		$this->user_id      = self::factory()->user->create( [ 'display_name' => 'Test User' ] );
		$this->organizer_id = self::factory()->user->create( [ 'display_name' => 'Organizer' ] );

		// Set up organizer on the test site.
		Membership::join( $this->organizer_id, $this->blog_id );
		switch_to_blog( $this->blog_id );
		$user = new \WP_User( $this->organizer_id );
		$user->set_role( 'organizer' );
		restore_current_blog();
	}

	/**
	 * Tear down each test.
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Helper to dispatch a request on the test blog.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	private function dispatch_on_blog( WP_REST_Request $request ) {
		switch_to_blog( $this->blog_id );
		$response = $this->server->dispatch( $request );
		restore_current_blog();

		return $response;
	}

	/**
	 * @covers ::get_items
	 */
	public function test_list_returns_members_with_roles(): void {
		// Add a regular member.
		Membership::join( $this->user_id, $this->blog_id );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/members' );
		$response = $this->dispatch_on_blog( $request );

		$this->assertSame( 200, $response->get_status() );

		$data     = $response->get_data();
		$user_ids = array_column( $data, 'user_id' );

		$this->assertContains( $this->user_id, $user_ids );
		$this->assertContains( $this->organizer_id, $user_ids );

		// Check that role information is present.
		$member_entry = null;
		$org_entry    = null;

		foreach ( $data as $entry ) {
			if ( $entry['user_id'] === $this->user_id ) {
				$member_entry = $entry;
			}
			if ( $entry['user_id'] === $this->organizer_id ) {
				$org_entry = $entry;
			}
		}

		$this->assertNotNull( $member_entry );
		$this->assertSame( 'member', $member_entry['role'] );
		$this->assertArrayHasKey( 'display_name', $member_entry );
		$this->assertArrayHasKey( 'avatar_url', $member_entry );
		$this->assertArrayHasKey( 'joined_date', $member_entry );

		$this->assertNotNull( $org_entry );
		$this->assertSame( 'organizer', $org_entry['role'] );
	}

	/**
	 * @covers ::join_item
	 */
	public function test_join_adds_user_to_site(): void {
		wp_set_current_user( $this->user_id );

		$request  = new WP_REST_Request( 'POST', '/groups/v1/members/join' );
		$response = $this->dispatch_on_blog( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $this->user_id, $data['user_id'] );
		$this->assertSame( 'member', $data['role'] );

		// Verify user is actually a member now.
		$this->assertTrue( is_user_member_of_blog( $this->user_id, $this->blog_id ) );
	}

	/**
	 * @covers ::leave_item
	 */
	public function test_leave_removes_user(): void {
		// First join.
		Membership::join( $this->user_id, $this->blog_id );
		$this->assertTrue( is_user_member_of_blog( $this->user_id, $this->blog_id ) );

		wp_set_current_user( $this->user_id );

		$request  = new WP_REST_Request( 'DELETE', '/groups/v1/members/leave' );
		$response = $this->dispatch_on_blog( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $this->user_id, $data['user_id'] );

		// Verify user is no longer a member.
		$this->assertFalse( is_user_member_of_blog( $this->user_id, $this->blog_id ) );
	}

	/**
	 * @covers ::join_item_permissions_check
	 */
	public function test_anonymous_cannot_join(): void {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'POST', '/groups/v1/members/join' );
		$response = $this->dispatch_on_blog( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * @covers ::join_item_permissions_check
	 */
	public function test_banned_user_cannot_join(): void {
		// Ban the user.
		Membership::ban( $this->user_id, $this->blog_id, $this->organizer_id );

		wp_set_current_user( $this->user_id );

		$request  = new WP_REST_Request( 'POST', '/groups/v1/members/join' );
		$response = $this->dispatch_on_blog( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'rest_user_banned', $response->get_data()['code'] );
	}

	/**
	 * @covers ::join_item_permissions_check
	 */
	public function test_already_a_member_cannot_join_again(): void {
		Membership::join( $this->user_id, $this->blog_id );

		wp_set_current_user( $this->user_id );

		$request  = new WP_REST_Request( 'POST', '/groups/v1/members/join' );
		$response = $this->dispatch_on_blog( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_already_member', $response->get_data()['code'] );
	}

	/**
	 * @covers ::leave_item_permissions_check
	 */
	public function test_anonymous_cannot_leave(): void {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'DELETE', '/groups/v1/members/leave' );
		$response = $this->dispatch_on_blog( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * @covers ::leave_item_permissions_check
	 */
	public function test_non_member_cannot_leave(): void {
		wp_set_current_user( $this->user_id );

		$request  = new WP_REST_Request( 'DELETE', '/groups/v1/members/leave' );
		$response = $this->dispatch_on_blog( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_not_a_member', $response->get_data()['code'] );
	}

	/**
	 * @covers ::get_items
	 */
	public function test_list_members_is_public(): void {
		// Anonymous user.
		wp_set_current_user( 0 );

		Membership::join( $this->user_id, $this->blog_id );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/members' );
		$response = $this->dispatch_on_blog( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $response->get_data() );
	}

	/**
	 * @covers ::get_items
	 */
	public function test_list_members_filtered_by_role(): void {
		Membership::join( $this->user_id, $this->blog_id );

		$request = new WP_REST_Request( 'GET', '/groups/v1/members' );
		$request->set_param( 'role', 'organizer' );

		$response = $this->dispatch_on_blog( $request );

		$this->assertSame( 200, $response->get_status() );

		$data     = $response->get_data();
		$user_ids = array_column( $data, 'user_id' );

		$this->assertContains( $this->organizer_id, $user_ids );
		$this->assertNotContains( $this->user_id, $user_ids );
	}

	/**
	 * @covers ::get_item_schema
	 */
	public function test_schema_includes_expected_properties(): void {
		$controller = new Membership_Controller();
		$schema     = $controller->get_item_schema();

		$this->assertArrayHasKey( 'user_id', $schema['properties'] );
		$this->assertArrayHasKey( 'display_name', $schema['properties'] );
		$this->assertArrayHasKey( 'avatar_url', $schema['properties'] );
		$this->assertArrayHasKey( 'role', $schema['properties'] );
		$this->assertArrayHasKey( 'joined_date', $schema['properties'] );
	}

	/**
	 * @covers ::get_items
	 */
	public function test_joined_date_uses_group_meta_not_user_registered(): void {
		wp_set_current_user( $this->user_id );

		$request  = new WP_REST_Request( 'POST', '/groups/v1/members/join' );
		$response = $this->dispatch_on_blog( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertNotNull( $data['joined_date'], 'joined_date should not be null after joining.' );

		// The joined_date should NOT equal user_registered (account creation date).
		$user            = get_userdata( $this->user_id );
		$registered_date = mysql_to_rfc3339( $user->user_registered );
		$meta_value      = get_user_meta( $this->user_id, "_groups_joined_{$this->blog_id}", true );

		$this->assertNotEmpty( $meta_value, 'Join should have stored a _groups_joined meta.' );
		$this->assertSame( mysql_to_rfc3339( $meta_value ), $data['joined_date'] );
	}

	/**
	 * @covers ::get_items
	 */
	public function test_joined_date_falls_back_to_user_registered_for_legacy_members(): void {
		// Simulate a legacy member by adding them to the blog directly (no meta).
		add_user_to_blog( $this->blog_id, $this->user_id, 'member' );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/members' );
		$response = $this->dispatch_on_blog( $request );

		$data         = $response->get_data();
		$member_entry = null;

		foreach ( $data as $entry ) {
			if ( $entry['user_id'] === $this->user_id ) {
				$member_entry = $entry;
				break;
			}
		}

		$this->assertNotNull( $member_entry );

		$user            = get_userdata( $this->user_id );
		$registered_date = mysql_to_rfc3339( $user->user_registered );

		$this->assertSame( $registered_date, $member_entry['joined_date'] );
	}

	/**
	 * @covers ::join_item
	 */
	public function test_join_response_includes_expected_fields(): void {
		wp_set_current_user( $this->user_id );

		$request  = new WP_REST_Request( 'POST', '/groups/v1/members/join' );
		$response = $this->dispatch_on_blog( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'user_id', $data );
		$this->assertArrayHasKey( 'display_name', $data );
		$this->assertArrayHasKey( 'avatar_url', $data );
		$this->assertArrayHasKey( 'role', $data );
		$this->assertArrayHasKey( 'joined_date', $data );
		$this->assertSame( 'Test User', $data['display_name'] );
	}
}
