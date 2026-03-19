<?php
/**
 * Tests for the RSVP REST API controller.
 *
 * @package Groups\Tests\REST
 */


use Groups\Models\Membership;
use Groups\Models\Rsvp;
use Groups\Post_Types\Event;
use Groups\REST\Rsvp_Controller;
use WP_REST_Server;

/**
 * @coversDefaultClass \Groups\REST\Rsvp_Controller
 * @group rest-api
 */
class Test_Rsvp_Controller extends WP_UnitTestCase {

	/**
	 * REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	private WP_REST_Server $server;

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private int $admin_id;

	/**
	 * Subscriber user ID (member role).
	 *
	 * @var int
	 */
	private int $subscriber_id;

	/**
	 * Organizer user ID.
	 *
	 * @var int
	 */
	private int $organizer_id;

	/**
	 * Second subscriber for waitlist tests.
	 *
	 * @var int
	 */
	private int $subscriber2_id;

	/**
	 * Set up the test suite — register CPT, statuses, and roles once.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		$event_cpt = new Event();
		$event_cpt->register_post_type();
		$event_cpt->register_post_statuses();

		if ( ! get_role( 'organizer' ) ) {
			add_role( 'organizer', 'Organizer', [ 'read' => true ] );
		}

		if ( ! get_role( 'co_organizer' ) ) {
			add_role( 'co_organizer', 'Co-Organizer', [ 'read' => true ] );
		}
	}

	/**
	 * Set up each test.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$this->server = $wp_rest_server = new WP_REST_Server();

		$controller = new Rsvp_Controller();
		$controller->register_routes();

		$this->admin_id       = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->subscriber_id  = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$this->organizer_id   = self::factory()->user->create( [ 'role' => 'organizer' ] );
		$this->subscriber2_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
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
	 * Helper to create an event post with meta.
	 *
	 * @param array $args Optional post args.
	 * @param array $meta Optional meta values.
	 * @return int Post ID.
	 */
	private function create_event( array $args = [], array $meta = [] ): int {
		$defaults = [
			'post_type'   => Event::POST_TYPE,
			'post_title'  => 'Test Event',
			'post_status' => 'event-scheduled',
			'post_author' => $this->admin_id,
		];

		$post_id = self::factory()->post->create( array_merge( $defaults, $args ) );

		$default_meta = [
			'_event_start_date'      => '2026-06-15 18:00:00',
			'_event_end_date'        => '2026-06-15 20:00:00',
			'_event_timezone'        => 'Australia/Melbourne',
			'_event_attendee_limit'  => 0,
			'_event_waitlist_enabled' => false,
		];

		foreach ( array_merge( $default_meta, $meta ) as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return $post_id;
	}

	/**
	 * @covers ::get_items
	 */
	public function test_list_rsvps_returns_attendees(): void {
		$event_id = $this->create_event();

		// Create RSVPs directly via the model.
		Rsvp::create( $event_id, $this->subscriber_id );
		Rsvp::create( $event_id, $this->subscriber2_id );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/events/' . $event_id . '/rsvps' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 2, $data );

		// Verify response contains expected fields.
		$first = $data[0];
		$this->assertArrayHasKey( 'id', $first );
		$this->assertArrayHasKey( 'user_id', $first );
		$this->assertArrayHasKey( 'display_name', $first );
		$this->assertArrayHasKey( 'avatar_url', $first );
		$this->assertArrayHasKey( 'status', $first );
		$this->assertSame( 'attending', $first['status'] );
	}

	/**
	 * @covers ::create_item
	 */
	public function test_rsvp_creates_comment_with_correct_meta(): void {
		wp_set_current_user( $this->subscriber_id );

		$event_id = $this->create_event();

		$request = new WP_REST_Request( 'POST', '/groups/v1/events/' . $event_id . '/rsvp' );
		$request->set_body_params( [
			'guests' => 2,
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $event_id, $data['event_id'] );
		$this->assertSame( $this->subscriber_id, $data['user_id'] );
		$this->assertSame( 'attending', $data['status'] );
		$this->assertSame( 2, $data['guests'] );

		// Verify comment was actually created with correct type.
		$comment = get_comment( $data['id'] );
		$this->assertSame( Rsvp::COMMENT_TYPE, $comment->comment_type );
		$this->assertSame( 'attending', get_comment_meta( $data['id'], '_rsvp_status', true ) );
	}

	/**
	 * @covers ::create_item_permissions_check
	 */
	public function test_anonymous_cannot_rsvp(): void {
		wp_set_current_user( 0 );

		$event_id = $this->create_event();

		$request  = new WP_REST_Request( 'POST', '/groups/v1/events/' . $event_id . '/rsvp' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * @covers ::delete_item
	 */
	public function test_cancel_rsvp_works(): void {
		wp_set_current_user( $this->subscriber_id );

		$event_id = $this->create_event();
		Rsvp::create( $event_id, $this->subscriber_id );

		$request  = new WP_REST_Request( 'DELETE', '/groups/v1/events/' . $event_id . '/rsvp' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'not_attending', $data['status'] );
	}

	/**
	 * @covers ::delete_item
	 */
	public function test_waitlist_promotion_on_cancel(): void {
		$event_id = $this->create_event( [], [
			'_event_attendee_limit'   => 1,
			'_event_waitlist_enabled' => true,
		] );

		// First user gets attending, second gets waitlisted.
		$rsvp1_id = Rsvp::create( $event_id, $this->subscriber_id );
		$rsvp2_id = Rsvp::create( $event_id, $this->subscriber2_id );

		// Verify second user is waitlisted.
		$this->assertSame( 'waitlisted', get_comment_meta( $rsvp2_id, '_rsvp_status', true ) );

		// Cancel first user's RSVP via REST.
		wp_set_current_user( $this->subscriber_id );
		$request  = new WP_REST_Request( 'DELETE', '/groups/v1/events/' . $event_id . '/rsvp' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		// Verify second user was promoted to attending.
		$promoted_status = get_comment_meta( $rsvp2_id, '_rsvp_status', true );
		$this->assertSame( 'attending', $promoted_status, 'Waitlisted user should be promoted to attending after cancellation.' );
	}

	/**
	 * @covers ::mark_attendance_permissions_check
	 */
	public function test_mark_attendance_requires_organizer_role(): void {
		wp_set_current_user( $this->subscriber_id );

		$event_id = $this->create_event();

		$request = new WP_REST_Request( 'POST', '/groups/v1/events/' . $event_id . '/attendance' );
		$request->set_body_params( [
			'user_id'  => $this->subscriber2_id,
			'attended' => true,
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status(), 'Subscribers should not be able to mark attendance.' );
	}

	/**
	 * @covers ::create_item_permissions_check
	 * @covers ::mark_attendance_permissions_check
	 */
	public function test_subscriber_can_rsvp_but_not_mark_attendance(): void {
		$event_id = $this->create_event();

		// Subscriber can RSVP.
		wp_set_current_user( $this->subscriber_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/events/' . $event_id . '/rsvp' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 201, $response->get_status(), 'Subscriber should be able to RSVP.' );

		// Subscriber cannot mark attendance.
		$request = new WP_REST_Request( 'POST', '/groups/v1/events/' . $event_id . '/attendance' );
		$request->set_body_params( [
			'user_id'  => $this->subscriber_id,
			'attended' => true,
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status(), 'Subscriber should not be able to mark attendance.' );
	}

	/**
	 * @covers ::create_item
	 */
	public function test_duplicate_rsvp_returns_error(): void {
		wp_set_current_user( $this->subscriber_id );

		$event_id = $this->create_event();

		// First RSVP succeeds.
		$request  = new WP_REST_Request( 'POST', '/groups/v1/events/' . $event_id . '/rsvp' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		// Second RSVP should fail with 409 Conflict.
		$request  = new WP_REST_Request( 'POST', '/groups/v1/events/' . $event_id . '/rsvp' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'duplicate_rsvp', $response->get_data()['code'] );
	}

	/**
	 * @covers ::mark_attendance
	 */
	public function test_organizer_can_mark_attendance(): void {
		wp_set_current_user( $this->organizer_id );

		$event_id = $this->create_event();
		Rsvp::create( $event_id, $this->subscriber_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/events/' . $event_id . '/attendance' );
		$request->set_body_params( [
			'user_id'  => $this->subscriber_id,
			'attended' => true,
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['attendance_confirmed'] );
	}

	/**
	 * @covers ::update_item
	 */
	public function test_update_rsvp_guests(): void {
		wp_set_current_user( $this->subscriber_id );

		$event_id = $this->create_event();
		Rsvp::create( $event_id, $this->subscriber_id, 0 );

		$request = new WP_REST_Request( 'PUT', '/groups/v1/events/' . $event_id . '/rsvp' );
		$request->set_body_params( [
			'guests' => 3,
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 3, $response->get_data()['guests'] );
	}

	/**
	 * @covers ::get_items
	 */
	public function test_list_rsvps_filtered_by_status(): void {
		$event_id = $this->create_event( [], [
			'_event_attendee_limit'   => 1,
			'_event_waitlist_enabled' => true,
		] );

		Rsvp::create( $event_id, $this->subscriber_id );
		Rsvp::create( $event_id, $this->subscriber2_id );

		// Filter for attending only.
		$request = new WP_REST_Request( 'GET', '/groups/v1/events/' . $event_id . '/rsvps' );
		$request->set_param( 'status', 'attending' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data() );
		$this->assertSame( 'attending', $response->get_data()[0]['status'] );

		// Filter for waitlisted only.
		$request = new WP_REST_Request( 'GET', '/groups/v1/events/' . $event_id . '/rsvps' );
		$request->set_param( 'status', 'waitlisted' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data() );
		$this->assertSame( 'waitlisted', $response->get_data()[0]['status'] );
	}

	/**
	 * @covers ::delete_item
	 */
	public function test_cancel_nonexistent_rsvp_returns_404(): void {
		wp_set_current_user( $this->subscriber_id );

		$event_id = $this->create_event();

		$request  = new WP_REST_Request( 'DELETE', '/groups/v1/events/' . $event_id . '/rsvp' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * @covers ::update_item_permissions_check
	 */
	public function test_anonymous_cannot_update_rsvp(): void {
		wp_set_current_user( 0 );

		$event_id = $this->create_event();

		$request = new WP_REST_Request( 'PUT', '/groups/v1/events/' . $event_id . '/rsvp' );
		$request->set_body_params( [
			'guests' => 1,
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * @covers ::delete_item_permissions_check
	 */
	public function test_anonymous_cannot_cancel_rsvp(): void {
		wp_set_current_user( 0 );

		$event_id = $this->create_event();

		$request  = new WP_REST_Request( 'DELETE', '/groups/v1/events/' . $event_id . '/rsvp' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * @covers ::create_item_permissions_check
	 */
	public function test_rsvp_to_nonexistent_event_returns_404(): void {
		wp_set_current_user( $this->subscriber_id );

		$request  = new WP_REST_Request( 'POST', '/groups/v1/events/999999/rsvp' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * @covers ::create_item_permissions_check
	 */
	public function test_banned_user_cannot_rsvp(): void {
		wp_set_current_user( $this->subscriber_id );

		$event_id = $this->create_event();

		// Ban the subscriber from the current site.
		update_user_meta( $this->subscriber_id, '_groups_banned_' . get_current_blog_id(), 1 );

		$request  = new WP_REST_Request( 'POST', '/groups/v1/events/' . $event_id . '/rsvp' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'rest_user_banned', $response->get_data()['code'] );

		// Clean up.
		delete_user_meta( $this->subscriber_id, '_groups_banned_' . get_current_blog_id() );
	}

	/**
	 * @covers ::mark_attendance
	 */
	public function test_mark_no_show(): void {
		wp_set_current_user( $this->organizer_id );

		$event_id = $this->create_event();
		Rsvp::create( $event_id, $this->subscriber_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/events/' . $event_id . '/attendance' );
		$request->set_body_params( [
			'user_id'  => $this->subscriber_id,
			'attended' => false,
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'no_show', $data['status'] );
		$this->assertFalse( $data['attendance_confirmed'] );
	}
}
