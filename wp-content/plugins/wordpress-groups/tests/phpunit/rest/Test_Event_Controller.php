<?php
/**
 * Tests for the Event REST API controller.
 *
 * @package Groups\Tests\REST
 */

use Groups\Post_Types\Event;
use Groups\REST\Event_Controller;

/**
 * @coversDefaultClass \Groups\REST\Event_Controller
 * @group rest-api
 */
class Test_Event_Controller extends WP_UnitTestCase {

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
	 * Subscriber user ID.
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
	 * Co-organizer user ID.
	 *
	 * @var int
	 */
	private int $co_organizer_id;

	/**
	 * Set up the test suite — register CPT and statuses once.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		$event_cpt = new Event();
		$event_cpt->register_post_type();
		$event_cpt->register_post_statuses();

		// Register custom roles for organizer / co-organizer.
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
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		$this->admin_id         = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->subscriber_id    = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$this->organizer_id     = self::factory()->user->create( [ 'role' => 'organizer' ] );
		$this->co_organizer_id  = self::factory()->user->create( [ 'role' => 'co_organizer' ] );
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
			'_event_start_utc' => '2026-06-15 18:00:00',
			'_event_end_utc'   => '2026-06-15 20:00:00',
			'_event_timezone'   => 'Australia/Melbourne',
		];

		foreach ( array_merge( $default_meta, $meta ) as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return $post_id;
	}

	/**
	 * @covers ::get_items
	 */
	public function test_get_events_returns_events(): void {
		$this->create_event( [ 'post_title' => 'Event One' ] );
		$this->create_event( [ 'post_title' => 'Event Two' ] );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/events' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( 2, (int) $response->get_headers()['X-WP-Total'] );
	}

	/**
	 * @covers ::get_item
	 */
	public function test_get_single_event_returns_event_with_meta(): void {
		$post_id = $this->create_event(
			[ 'post_title' => 'Single Event' ],
			[
				'_event_start_utc'  => '2026-07-01 10:00:00',
				'_event_end_utc'    => '2026-07-01 12:00:00',
				'_event_timezone'    => 'America/New_York',
				'_event_online_link' => 'https://meet.example.com/abc',
			]
		);

		$request  = new WP_REST_Request( 'GET', '/groups/v1/events/' . $post_id );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $post_id, $data['id'] );
		$this->assertSame( 'Single Event', $data['title'] );
		$this->assertSame( '2026-07-01 10:00:00', $data['meta']['start_date'] );
		$this->assertSame( '2026-07-01 12:00:00', $data['meta']['end_date'] );
		$this->assertSame( 'America/New_York', $data['meta']['timezone'] );
		$this->assertSame( 'https://meet.example.com/abc', $data['meta']['online_link'] );
	}

	/**
	 * @covers ::get_item_permissions_check
	 */
	public function test_get_nonexistent_event_returns_404(): void {
		$request  = new WP_REST_Request( 'GET', '/groups/v1/events/999999' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * @covers ::create_item
	 */
	public function test_create_event_as_organizer(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/events' );
		$request->set_body_params( [
			'title'   => 'New Event',
			'content' => 'Event description here.',
			'status'  => 'event-scheduled',
			'meta'    => [
				'start_date' => '2026-08-01 14:00:00',
				'end_date'   => '2026-08-01 16:00:00',
				'timezone'   => 'Europe/London',
			],
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'New Event', $data['title'] );
		$this->assertSame( 'event-scheduled', $data['status'] );
		$this->assertSame( '2026-08-01 14:00:00', $data['meta']['start_date'] );
		$this->assertSame( 'Europe/London', $data['meta']['timezone'] );

		// Verify Location header is set.
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'Location', $headers );
	}

	/**
	 * @covers ::create_item_permissions_check
	 */
	public function test_create_event_rejected_for_subscribers(): void {
		wp_set_current_user( $this->subscriber_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/events' );
		$request->set_body_params( [
			'title' => 'Unauthorized Event',
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * @covers ::create_item_permissions_check
	 */
	public function test_create_event_rejected_for_anonymous(): void {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/groups/v1/events' );
		$request->set_body_params( [
			'title' => 'Anonymous Event',
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * @covers ::update_item
	 */
	public function test_update_event(): void {
		wp_set_current_user( $this->admin_id );

		$post_id = $this->create_event( [ 'post_title' => 'Original Title' ] );

		$request = new WP_REST_Request( 'PUT', '/groups/v1/events/' . $post_id );
		$request->set_body_params( [
			'title' => 'Updated Title',
			'meta'  => [
				'start_date' => '2026-09-01 09:00:00',
			],
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'Updated Title', $data['title'] );
		$this->assertSame( '2026-09-01 09:00:00', $data['meta']['start_date'] );
	}

	/**
	 * @covers ::delete_item
	 */
	public function test_delete_event_sets_status_to_cancelled(): void {
		wp_set_current_user( $this->admin_id );

		$post_id = $this->create_event( [ 'post_status' => 'event-scheduled' ] );

		$request  = new WP_REST_Request( 'DELETE', '/groups/v1/events/' . $post_id );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'event-cancelled', $data['status'] );

		// Verify the post status was actually changed in the database.
		$post = get_post( $post_id );
		$this->assertSame( 'event-cancelled', $post->post_status );
	}

	/**
	 * @covers ::get_items
	 */
	public function test_date_range_filtering(): void {
		$this->create_event(
			[ 'post_title' => 'January Event' ],
			[ '_event_start_utc' => '2026-01-15 18:00:00' ]
		);

		$this->create_event(
			[ 'post_title' => 'June Event' ],
			[ '_event_start_utc' => '2026-06-15 18:00:00' ]
		);

		$this->create_event(
			[ 'post_title' => 'December Event' ],
			[ '_event_start_utc' => '2026-12-15 18:00:00' ]
		);

		// Filter: only events in first half of year.
		$request = new WP_REST_Request( 'GET', '/groups/v1/events' );
		$request->set_param( 'after', '2026-01-01' );
		$request->set_param( 'before', '2026-06-30' );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data   = $response->get_data();
		$titles = array_column( $data, 'title' );

		$this->assertContains( 'January Event', $titles );
		$this->assertContains( 'June Event', $titles );
		$this->assertNotContains( 'December Event', $titles );
	}

	/**
	 * @covers ::get_items
	 */
	public function test_date_range_after_only(): void {
		$this->create_event(
			[ 'post_title' => 'Early Event' ],
			[ '_event_start_utc' => '2025-06-01 18:00:00' ]
		);

		$this->create_event(
			[ 'post_title' => 'Late Event' ],
			[ '_event_start_utc' => '2027-06-01 18:00:00' ]
		);

		$request = new WP_REST_Request( 'GET', '/groups/v1/events' );
		$request->set_param( 'after', '2026-01-01' );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data   = $response->get_data();
		$titles = array_column( $data, 'title' );

		$this->assertNotContains( 'Early Event', $titles );
		$this->assertContains( 'Late Event', $titles );
	}

	/**
	 * @covers ::get_item_schema
	 */
	public function test_schema_includes_meta_properties(): void {
		$controller = new Event_Controller();
		$schema     = $controller->get_item_schema();

		$this->assertArrayHasKey( 'meta', $schema['properties'] );
		$this->assertArrayHasKey( 'start_date', $schema['properties']['meta']['properties'] );
		$this->assertArrayHasKey( 'end_date', $schema['properties']['meta']['properties'] );
		$this->assertArrayHasKey( 'timezone', $schema['properties']['meta']['properties'] );
		$this->assertArrayHasKey( 'venue_id', $schema['properties']['meta']['properties'] );
		$this->assertArrayHasKey( 'online_link', $schema['properties']['meta']['properties'] );
		$this->assertArrayHasKey( 'attendee_limit', $schema['properties']['meta']['properties'] );
		$this->assertArrayHasKey( 'waitlist_enabled', $schema['properties']['meta']['properties'] );
	}

	/**
	 * @covers ::delete_item_permissions_check
	 */
	public function test_delete_event_rejected_for_subscribers(): void {
		wp_set_current_user( $this->subscriber_id );

		$post_id = $this->create_event();

		$request  = new WP_REST_Request( 'DELETE', '/groups/v1/events/' . $post_id );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * @covers ::create_item
	 */
	public function test_create_event_defaults_to_draft_status(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/events' );
		$request->set_body_params( [
			'title' => 'Draft Event',
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'event-draft', $data['status'] );
	}

	/**
	 * @covers ::create_item
	 */
	public function test_create_event_rejects_invalid_status(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/events' );
		$request->set_body_params( [
			'title'  => 'Bad Status Event',
			'status' => 'invalid-status',
		] );

		$response = $this->server->dispatch( $request );

		// The schema defines an enum for status, so the REST API rejects invalid values at validation time.
		$this->assertSame( 400, $response->get_status() );
	}

	// =========================================================================
	// Security tests
	// =========================================================================

	/**
	 * Test that user A cannot update user B's event (IDOR prevention).
	 *
	 * A co-organizer should only be able to update their own events.
	 *
	 * @covers ::update_item_permissions_check
	 */
	public function test_co_organizer_cannot_update_other_users_event(): void {
		// Create an event owned by the admin.
		$post_id = $this->create_event( [
			'post_title'  => 'Admin Event',
			'post_author' => $this->admin_id,
		] );

		// Authenticate as a co-organizer (who is NOT the author).
		wp_set_current_user( $this->co_organizer_id );

		$request = new WP_REST_Request( 'PUT', '/groups/v1/events/' . $post_id );
		$request->set_body_params( [
			'title' => 'Hijacked Title',
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status(), 'Co-organizer should not be able to update another user\'s event.' );

		// Verify the title was NOT changed.
		$post = get_post( $post_id );
		$this->assertSame( 'Admin Event', $post->post_title );
	}

	/**
	 * Test that user A cannot delete user B's event (IDOR prevention).
	 *
	 * @covers ::delete_item_permissions_check
	 */
	public function test_co_organizer_cannot_delete_other_users_event(): void {
		$post_id = $this->create_event( [
			'post_title'  => 'Admin Event',
			'post_author' => $this->admin_id,
		] );

		wp_set_current_user( $this->co_organizer_id );

		$request  = new WP_REST_Request( 'DELETE', '/groups/v1/events/' . $post_id );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status(), 'Co-organizer should not be able to cancel another user\'s event.' );
	}

	/**
	 * Test that anonymous users cannot see draft events via GET single.
	 *
	 * @covers ::get_item_permissions_check
	 */
	public function test_anonymous_cannot_see_draft_event(): void {
		wp_set_current_user( 0 );

		$post_id = $this->create_event( [
			'post_status' => 'event-draft',
			'post_title'  => 'Secret Draft',
		] );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/events/' . $post_id );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status(), 'Anonymous users should not be able to see draft events.' );
	}

	/**
	 * Test that anonymous users cannot see draft events in listings.
	 *
	 * @covers ::get_items
	 */
	public function test_anonymous_cannot_see_draft_events_in_list(): void {
		wp_set_current_user( 0 );

		$this->create_event( [
			'post_status' => 'event-draft',
			'post_title'  => 'Hidden Draft',
		] );

		$this->create_event( [
			'post_status' => 'event-scheduled',
			'post_title'  => 'Visible Event',
		] );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/events' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data   = $response->get_data();
		$titles = array_column( $data, 'title' );

		$this->assertNotContains( 'Hidden Draft', $titles, 'Draft events should not appear in anonymous listings.' );
		$this->assertContains( 'Visible Event', $titles );
	}

	/**
	 * Test that subscribers cannot create events.
	 *
	 * Subscribers do not have the organizer/co-organizer/admin role.
	 *
	 * @covers ::create_item_permissions_check
	 */
	public function test_subscribers_cannot_create_events(): void {
		wp_set_current_user( $this->subscriber_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/events' );
		$request->set_body_params( [
			'title' => 'Subscriber Event',
			'meta'  => [
				'start_date' => '2026-08-01 14:00:00',
				'end_date'   => '2026-08-01 16:00:00',
				'timezone'   => 'UTC',
			],
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status(), 'Subscribers should not be allowed to create events.' );
	}

	/**
	 * Test that co-organizers CAN create events.
	 *
	 * @covers ::create_item_permissions_check
	 * @covers ::create_item
	 */
	public function test_co_organizer_can_create_event(): void {
		wp_set_current_user( $this->co_organizer_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/events' );
		$request->set_body_params( [
			'title'  => 'Co-Org Event',
			'status' => 'event-scheduled',
			'meta'   => [
				'start_date' => '2026-08-01 14:00:00',
				'end_date'   => '2026-08-01 16:00:00',
				'timezone'   => 'America/New_York',
			],
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 201, $response->get_status(), 'Co-organizers should be allowed to create events.' );
		$this->assertSame( 'Co-Org Event', $response->get_data()['title'] );
	}

	/**
	 * Test that co-organizers CAN update their own events.
	 *
	 * @covers ::update_item_permissions_check
	 * @covers ::update_item
	 */
	public function test_co_organizer_can_update_own_event(): void {
		$post_id = $this->create_event( [
			'post_title'  => 'My Event',
			'post_author' => $this->co_organizer_id,
		] );

		wp_set_current_user( $this->co_organizer_id );

		$request = new WP_REST_Request( 'PUT', '/groups/v1/events/' . $post_id );
		$request->set_body_params( [
			'title' => 'My Updated Event',
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Co-organizers should be able to update their own events.' );
		$this->assertSame( 'My Updated Event', $response->get_data()['title'] );
	}

	/**
	 * Test that organizers CAN create events.
	 *
	 * @covers ::create_item_permissions_check
	 * @covers ::create_item
	 */
	public function test_organizer_can_create_event(): void {
		wp_set_current_user( $this->organizer_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/events' );
		$request->set_body_params( [
			'title'  => 'Organizer Event',
			'status' => 'event-scheduled',
			'meta'   => [
				'start_date' => '2026-10-01 14:00:00',
				'end_date'   => '2026-10-01 16:00:00',
				'timezone'   => 'Europe/London',
			],
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 201, $response->get_status(), 'Organizers should be allowed to create events.' );
	}

	/**
	 * Test that organizers can update ANY event on the site.
	 *
	 * @covers ::update_item_permissions_check
	 */
	public function test_organizer_can_update_any_event(): void {
		$post_id = $this->create_event( [
			'post_title'  => 'Admin Event',
			'post_author' => $this->admin_id,
		] );

		wp_set_current_user( $this->organizer_id );

		$request = new WP_REST_Request( 'PUT', '/groups/v1/events/' . $post_id );
		$request->set_body_params( [
			'title' => 'Organizer Updated',
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Organizers should be able to update any event.' );
	}

	/**
	 * Test that invalid date format is rejected.
	 *
	 * @covers ::create_item
	 */
	public function test_invalid_date_format_rejected(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/events' );
		$request->set_body_params( [
			'title' => 'Bad Date Event',
			'meta'  => [
				'start_date' => 'not-a-date',
				'end_date'   => '2026-08-01 16:00:00',
				'timezone'   => 'UTC',
			],
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status(), 'Invalid date format should be rejected.' );
		$this->assertSame( 'rest_invalid_date_format', $response->get_data()['code'] );
	}

	/**
	 * Test that invalid date format is rejected on update too.
	 *
	 * @covers ::update_item
	 */
	public function test_invalid_date_format_rejected_on_update(): void {
		wp_set_current_user( $this->admin_id );

		$post_id = $this->create_event();

		$request = new WP_REST_Request( 'PUT', '/groups/v1/events/' . $post_id );
		$request->set_body_params( [
			'meta' => [
				'start_date' => '15/06/2026 18:00',
			],
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status(), 'Invalid date format should be rejected on update.' );
	}

	/**
	 * Test that invalid timezone is rejected.
	 *
	 * @covers ::create_item
	 */
	public function test_invalid_timezone_rejected(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'POST', '/groups/v1/events' );
		$request->set_body_params( [
			'title' => 'Bad TZ Event',
			'meta'  => [
				'start_date' => '2026-08-01 14:00:00',
				'end_date'   => '2026-08-01 16:00:00',
				'timezone'   => 'Not/A_Real_Timezone',
			],
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status(), 'Invalid timezone should be rejected.' );
		$this->assertSame( 'rest_invalid_timezone', $response->get_data()['code'] );
	}

	/**
	 * Test that invalid timezone is rejected on update.
	 *
	 * @covers ::update_item
	 */
	public function test_invalid_timezone_rejected_on_update(): void {
		wp_set_current_user( $this->admin_id );

		$post_id = $this->create_event();

		$request = new WP_REST_Request( 'PUT', '/groups/v1/events/' . $post_id );
		$request->set_body_params( [
			'meta' => [
				'timezone' => 'Fake/Zone',
			],
		] );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status(), 'Invalid timezone should be rejected on update.' );
	}

	/**
	 * Test that the event author can view their own draft event.
	 *
	 * @covers ::get_item_permissions_check
	 */
	public function test_author_can_see_own_draft_event(): void {
		$post_id = $this->create_event( [
			'post_status' => 'event-draft',
			'post_author' => $this->co_organizer_id,
		] );

		wp_set_current_user( $this->co_organizer_id );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/events/' . $post_id );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Event author should be able to see their own draft event.' );
	}

	/**
	 * Test that subscribers cannot see cancelled events in list.
	 *
	 * @covers ::get_items
	 */
	public function test_subscriber_cannot_see_cancelled_events_in_list(): void {
		wp_set_current_user( $this->subscriber_id );

		$this->create_event( [
			'post_status' => 'event-cancelled',
			'post_title'  => 'Cancelled Event',
		] );

		$this->create_event( [
			'post_status' => 'event-scheduled',
			'post_title'  => 'Active Event',
		] );

		$request  = new WP_REST_Request( 'GET', '/groups/v1/events' );
		$response = $this->server->dispatch( $request );

		$titles = array_column( $response->get_data(), 'title' );

		$this->assertNotContains( 'Cancelled Event', $titles, 'Cancelled events should not appear in subscriber listings.' );
		$this->assertContains( 'Active Event', $titles );
	}
}
